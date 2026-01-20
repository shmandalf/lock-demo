<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class MonitorQueue extends Command
{
    protected $signature = 'queue:monitor';
    protected $description = 'Monitor queue and semaphore status';

    public function handle()
    {
        while (true) {
            $this->info('=== Queue Monitor === ' . now()->toDateTimeString());
            
            // Статистика очереди
            $pending = Redis::llen('queues:default');
            $failed = Redis::llen('failed');
            
            // Статистика блокировок
            $locks = [];
            for ($i = 0; $i < 5; $i++) {
                $lockName = "laravel_database_task_semaphore_{$i}";
                if (Cache::has($lockName)) {
                    $locks[] = [
                        'name' => $lockName,
                        'ttl' => Redis::ttl($lockName),
                    ];
                }
            }
            
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Pending Jobs', $pending],
                    ['Failed Jobs', $failed],
                    ['Active Locks', count($locks)],
                ]
            );
            
            if (!empty($locks)) {
                $this->info('Active Locks:');
                foreach ($locks as $lock) {
                    $this->line("  {$lock['name']} - TTL: {$lock['ttl']}s");
                }
            }
            
            sleep(2);
        }
    }
}
