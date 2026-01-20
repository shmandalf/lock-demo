<?php

namespace App\Services;

class SemaphoreManager
{
    public function make(string $key, int $maxConcurrent = 2, int $timeout = 30): RedisSemaphore
    {
        return new RedisSemaphore($key, $maxConcurrent, $timeout);
    }

    public function acquire(string $key, int $maxConcurrent = 2, int $timeout = 30): bool
    {
        $semaphore = $this->make($key, $maxConcurrent, $timeout);
        return $semaphore->acquire();
    }

    public function getCount(string $key): int
    {
        try {
            return Redis::zcard("semaphore:{$key}");
        } catch (\Exception $e) {
            return 0;
        }
    }
}
