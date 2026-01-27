<?php

namespace App\Contracts;

use App\Services\Semaphores\DTO\Stats;

/**
 * Semaphore interface for managing concurrent access to resources
 *
 * A semaphore limits the number of simultaneous processes that can
 * execute a specific operation or access a resource.
 */
interface SemaphoreInterface
{
    /**
     * Acquire the semaphore (blocking call)
     *
     * Blocks execution until a semaphore slot becomes available
     * or the timeout expires.
     *
     * @param int $timeout Maximum wait time in seconds.
     *                     Minimum 1 second.
     * @return bool true - semaphore acquired, false - failed to acquire
     */
    public function acquire(int $timeout): bool;

    /**
     * Release the acquired semaphore
     *
     * @return bool true - semaphore successfully released,
     *              false - semaphore was not acquired or an error occurred
     * @throws \LogicException When attempting to release a non-acquired semaphore
     */
    public function release(): bool;

    /**
     * Check if the semaphore is currently acquired by this process
     *
     * @return bool true - semaphore acquired by current identifier,
     *              false - semaphore not acquired or acquired by another process
     */
    public function isAcquiredByMe(): bool;

    /**
     * Get the current number of acquired semaphore slots
     *
     * @return int Number of occupied slots (0..maxConcurrent)
     */
    public function getCurrentCount(): int;

    /**
     * Get complete semaphore statistics
     *
     * @return Stats
     * @throws \RuntimeException On errors retrieving statistics
     */
    public function getStats(): Stats;

    /**
     * Get the maximum number of simultaneous acquisitions
     *
     * @return int Maximum number of processes that can
     *             simultaneously hold the semaphore
     */
    public function getMaxConcurrent(): int;

    /**
     * Get the semaphore timeout in seconds
     *
     * @return int Time in seconds after which acquisition is automatically
     *             released due to inactivity
     */
    public function getTimeout(): int;
}
