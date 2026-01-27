<?php

namespace App\Services\Semaphores;

use App\Contracts\SemaphoreInterface;

class SemaphoreFactory
{
    /**
     * Class name to use for semaphore creation
     *
     * @var string
     */
    private string $className;

    /**
     * Initialize the factory with configured class
     *
     * @param string $className Fully qualified class name
     */
    public function __construct(string $className)
    {
        $this->className = $className;
    }

    /**
     * Create a semaphore instance using configured implementation
     *
     * @param string $key Unique identifier for the semaphore
     * @param int $maxConcurrent Maximum number of concurrent acquisitions
     * @param int $timeout Timeout in seconds for automatic release
     * @return SemaphoreInterface
     * @throws \InvalidArgumentException If parameters are invalid
     */
    public function create(string $key, int $maxConcurrent, int $timeout): SemaphoreInterface
    {
        // Validate arguments for reliability
        if (empty(trim($key))) {
            throw new \InvalidArgumentException("Semaphore key cannot be empty");
        }

        if ($maxConcurrent < 1) {
            throw new \InvalidArgumentException("Max concurrent must be at least 1");
        }

        if ($timeout < 1) {
            throw new \InvalidArgumentException("Timeout must be at least 1 second");
        }

        // Create and return the semaphore instance
        return new $this->className($key, $maxConcurrent, $timeout);
    }
}
