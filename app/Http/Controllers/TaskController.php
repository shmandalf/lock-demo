<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'count' => 'required|integer|min:1|max:100',
            'delay' => 'integer|min:0|max:60',
            'max_concurrent' => 'integer|min:1|max:10',
        ]);

        $count = $validated['count'];
        $delay = $validated['delay'] ?? 0;
        $maxConcurrent = $validated['max_concurrent'] ?? 2;

        $taskIds = [];

        for ($i = 0; $i < $count; $i++) {
            $taskId = 'task-' . Str::random(6) . '-' . time();

            // Small random delay
            $randomDelay = $delay + ($i * 0.1);

            // Pass maxConcurrent into the Job constructor
            ProcessTask::dispatch($taskId, 1, $maxConcurrent)
                ->delay(now()->addSeconds($randomDelay));

            $taskIds[] = $taskId;
        }

        return response()->json([
            'success' => true,
            'task_ids' => $taskIds,
            'count' => $count,
            'max_concurrent' => $maxConcurrent,
            'message' => "{$count} task(s) queued with max concurrent = {$maxConcurrent}"
        ]);
    }

    public function clear()
    {
        try {
            // Clear queue
            $pendingCount = Redis::llen('queues:default');
            Redis::del('queues:default');

            // Release all available locks
            $clearedLocks = [];
            $lockPatterns = [
                'laravel_database_task_semaphore',
                'task_semaphore',
            ];

            foreach ($lockPatterns as $pattern) {
                for ($i = 0; $i < 5; $i++) {
                    $lockKey = "{$pattern}_{$i}";
                    if (Cache::forget($lockKey)) {
                        $clearedLocks[] = $lockKey;
                    }
                }

                if (Cache::forget($pattern)) {
                    $clearedLocks[] = $pattern;
                }
            }

            // Delete all semaphores
            $semaphoreKeys = array_merge(Redis::keys('semaphore:*'), Redis::keys('semaphore-legacy:'));
            $clearedSemaphores = [];

            foreach ($semaphoreKeys as $key) {
                if (Redis::del($key)) {
                    $clearedSemaphores[] = $key;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Queue, locks and semaphores cleared',
                'cleared' => [
                    'pending_jobs' => $pendingCount,
                    'locks' => $clearedLocks,
                    'semaphores' => $clearedSemaphores,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error clearing queue: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function health()
    {
        try {
            // Redis
            Redis::ping();
            $redisStatus = 'ok';
        } catch (\Exception $e) {
            $redisStatus = 'error: ' . $e->getMessage();
        }

        try {
            // Soketi/WebSocket
            $host = config('broadcasting.connections.pusher.options.host');
            $port = config('broadcasting.connections.pusher.options.port');

            $fp = @fsockopen($host, $port, $errno, $errstr, 3);
            if ($fp) {
                fclose($fp);
                $websocketStatus = 'ok';
            } else {
                $websocketStatus = "error: {$errstr}";
            }
        } catch (\Exception $e) {
            $websocketStatus = 'error: ' . $e->getMessage();
        }

        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toDateTimeString(),
            'services' => [
                'redis' => $redisStatus,
                'websocket' => $websocketStatus,
                'queue' => 'active',
            ],
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            ],
        ]);
    }
}
