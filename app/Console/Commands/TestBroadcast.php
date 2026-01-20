<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Events\TaskStatusChanged;
use Illuminate\Support\Str;

class TestBroadcast extends Command
{
    protected $signature = 'broadcast:test';
    protected $description = 'Test broadcasting system';

    public function handle()
    {
        $this->info('=== Testing Broadcasting System ===');

        // 1. Проверяем конфигурацию
        $this->info('1. Checking configuration...');
        $driver = config('broadcasting.default');
        $this->info("   Driver: {$driver}");

        $pusherConfig = config('broadcasting.connections.pusher');
        $this->info("   Pusher Host: " . ($pusherConfig['options']['host'] ?? 'not set'));
        $this->info("   Pusher Port: " . ($pusherConfig['options']['port'] ?? 'not set'));

        // 2. Тестируем broadcast
        $this->info('2. Testing broadcast...');

        $taskId = 'test-' . Str::random(6) . '-' . time();

        try {
            $this->info("   Sending event for task: {$taskId}");

            broadcast(new TaskStatusChanged(
                $taskId,
                'test',
                1,
                ['message' => 'Test broadcast from command']
            ));

            $this->info('   ✅ Broadcast sent successfully!');
            $this->info("   Task ID: {$taskId}");
        } catch (\Exception $e) {
            $this->error('   ❌ Broadcast failed: ' . $e->getMessage());
            $this->error('   Trace: ' . $e->getTraceAsString());
        }

        // 3. Проверяем Soketi
        $this->info('3. Checking Soketi connection...');
        $host = $pusherConfig['options']['host'] ?? 'soketi';
        $port = $pusherConfig['options']['port'] ?? 6001;

        $fp = @fsockopen($host, $port, $errno, $errstr, 3);
        if ($fp) {
            $this->info("   ✅ Soketi is reachable at {$host}:{$port}");
            fclose($fp);
        } else {
            $this->error("   ❌ Cannot connect to Soketi at {$host}:{$port}");
            $this->error("   Error: {$errstr} (code: {$errno})");
        }
    }
}
