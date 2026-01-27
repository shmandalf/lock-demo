<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for SemaphoreFactory
 *
 * @method static \App\Contracts\SemaphoreInterface create(string $key, int $maxConcurrent, int $timeout)
 * Create a new semaphore instance
 *
 * @see \App\Services\Semaphores\SemaphoreFactory
 */
class Semaphore extends Facade
{
    /**
     * Get the registered name of the component
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return \App\Services\Semaphores\SemaphoreFactory::class;
    }
}
