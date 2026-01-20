<?php

namespace App\Http\Controllers;

use App\Events\TaskStatusChanged;
use App\Jobs\ProcessTask;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function create(Request $request)
    {
        $count = $request->input('count', 1);
        $delay = $request->input('delay', 0);

        $taskIds = [];

        for ($i = 0; $i < $count; $i++) {
            $taskId = 'task-' . Str::random(6) . '-' . time();

            // Добавляем случайную задержку
            $randomDelay = $delay + ($i * 0.1);

            ProcessTask::dispatch($taskId)
                ->delay(now()->addSeconds($randomDelay));

            $taskIds[] = $taskId;
        }

        return response()->json([
            'success' => true,
            'task_ids' => $taskIds,
            'count' => $count,
            'message' => "{$count} task(s) queued"
        ]);
    }

    public function stats()
    {
        try {
            // Статистика семафоров
            $semaphoreStats = [];

            // Получаем все ключи семафоров
            $semaphoreKeys = Redis::keys('semaphore:*');

            foreach ($semaphoreKeys as $key) {
                $holders = Redis::zrange($key, 0, -1, 'WITHSCORES');
                $count = count($holders) / 2; // Каждый holder имеет score

                $semaphoreStats[] = [
                    'key' => $key,
                    'current' => $count,
                    'holders' => array_chunk($holders, 2),
                    'ttl' => Redis::ttl($key),
                ];
            }

            // Статистика Redis блокировок
            $lockStats = [];
            $lockPatterns = ['laravel_database_task_semaphore', 'task_semaphore'];

            foreach ($lockPatterns as $pattern) {
                for ($i = 0; $i < 5; $i++) {
                    $lockKey = "{$pattern}_{$i}";
                    if (Cache::has($lockKey)) {
                        $lockStats[] = [
                            'type' => 'lock',
                            'key' => $lockKey,
                            'ttl' => Redis::ttl($lockKey),
                        ];
                    }
                }
            }

            return response()->json([
                'semaphores' => $semaphoreStats,
                'locks' => $lockStats,
                'semaphore_explanation' => [
                    'type' => 'Redis-based counting semaphore',
                    'max_concurrent' => 2,
                    'purpose' => 'Limit concurrent task processing',
                    'implementation' => 'Redis sorted set with timestamps',
                ],
            ]);
        } catch (\Exception $e) {
            // TODO
        }
    }

    public function clear()
    {
        try {
            // Очищаем очередь
            $pendingCount = Redis::llen('queues:default');
            Redis::del('queues:default');

            // Освобождаем все возможные блокировки
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

                // Также очищаем без номера
                if (Cache::forget($pattern)) {
                    $clearedLocks[] = $pattern;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Queue and locks cleared',
                'cleared' => [
                    'pending_jobs' => $pendingCount,
                    'locks' => $clearedLocks,
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
            // Проверяем Redis
            Redis::ping();
            $redisStatus = 'ok';
        } catch (\Exception $e) {
            $redisStatus = 'error: ' . $e->getMessage();
        }

        try {
            // Проверяем Soketi/WebSocket
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
