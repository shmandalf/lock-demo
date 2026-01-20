<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Services\RedisSemaphore make(string $key, int $maxConcurrent = 2, int $timeout = 30)
 * @method static bool acquire(string $key, int $maxConcurrent = 2, int $timeout = 30)
 * @method static void release(string $key, string $identifier)
 * @method static int getCount(string $key)
 * 
 * @see \App\Services\SemaphoreManager
 */
class Semaphore extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'semaphore';
    }
}
