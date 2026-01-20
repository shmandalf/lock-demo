<?php

namespace App\Traits;

use App\Contracts\SemaphoreInterface;
use App\Services\SemaphoreManager;
use Illuminate\Support\Facades\Log;

trait UsesSemaphore
{
    /**
     * Create semaphore instance with custom parameters
     */
    protected function createSemaphore(
        string $key = 'task_processing',
        int $maxConcurrent = 2,
        int $timeout = 10
    ): SemaphoreInterface {
        return app(SemaphoreManager::class)->make($key, $maxConcurrent, $timeout);
    }

    /**
     * Try to acquire semaphore with timeout
     */
    protected function acquireSemaphore(
        SemaphoreInterface $semaphore,
        int $timeout = 3
    ): bool {
        return $semaphore->tryAcquire($timeout);
    }

    /**
     * Release semaphore with logging
     */
    protected function releaseSemaphore(
        SemaphoreInterface $semaphore,
        string $reason = 'normal_release'
    ): void {
        try {
            if ($semaphore->isAcquiredByMe()) {
                $released = $semaphore->release();
                Log::info("Semaphore released (reason: {$reason}), success: " . ($released ? 'yes' : 'no'));
            } else {
                Log::warning("Tried to release semaphore but not acquired (reason: {$reason})");
            }
        } catch (\Exception $e) {
            Log::error("Failed to release semaphore: " . $e->getMessage());
        }
    }

    /**
     * Get semaphore stats
     */
    protected function getSemaphoreStats(SemaphoreInterface $semaphore): array
    {
        return $semaphore->getStats();
    }

    /**
     * Helper method for common pattern: acquire -> process -> release
     */
    protected function withSemaphore(
        callable $processCallback,
        string $key = 'task_processing',
        int $maxConcurrent = 2,
        int $timeout = 10,
        int $acquireTimeout = 3
    ): mixed {
        $semaphore = $this->createSemaphore($key, $maxConcurrent, $timeout);
        $acquired = false;

        try {
            if ($this->acquireSemaphore($semaphore, $acquireTimeout)) {
                $acquired = true;
                return $processCallback($semaphore);
            }

            throw new \Exception("Failed to acquire semaphore '{$key}'");
        } finally {
            if ($acquired) {
                $this->releaseSemaphore($semaphore, 'with_semaphore');
            }
        }
    }
}
