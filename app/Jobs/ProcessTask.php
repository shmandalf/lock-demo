<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Traits\BroadcastsTaskStatus;
use Illuminate\Support\Facades\Log;
use App\Facades\Semaphore;
use App\Contracts\SemaphoreInterface;

class ProcessTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;
    use BroadcastsTaskStatus;

    /**
     * Base semaphore key name for task processing
     *
     * Combined with {@see self::$maxConcurrent} to create unique keys
     * for jobs with different concurrency limits.
     * Example: 'task_processing:3' for maxConcurrent=3
     */
    private const SEMAPHORE_KEY = 'task_processing';

    /**
     * Semaphore TTL in seconds (automatic release)
     */
    private const SEMAPHORE_TTL = 5; // 4-5 seconds for processing max

    /**
     * Maximum time to wait for semaphore acquisition in seconds
     */
    private const SEMAPHORE_ACQUIRE_TIMEOUT = 3; // wait 3 seconds before retrying

    /**
     * Task identifier
     */
    public string $taskId;

    /**
     * Current retry attempt number
     */
    public int $currentAttempt = 1;

    /**
     * Maximum number of concurrent tasks allowed
     */
    protected int $maxConcurrent;

    /**
     * Maximum number of retry attempts
     */
    public $tries = 9;

    /**
     * Backoff intervals between retries (in seconds)
     */
    public $backoff = [2, 5, 10];

    /**
     * Create a new job instance
     *
     * @param string $taskId Unique task identifier
     * @param int $initialAttempt Initial attempt number (default: 1)
     * @param int $maxConcurrent Maximum concurrent task processing (default: 2)
     */
    public function __construct(string $taskId, int $initialAttempt = 1, int $maxConcurrent = 2)
    {
        $this->taskId = $taskId;
        $this->currentAttempt = $initialAttempt;
        $this->maxConcurrent = $maxConcurrent;

        // IMPORTANT: Broadcast status immediately when job is created
        $this->broadcast('queued', 'Task added to queue', $initialAttempt);

        // Log creation details
        Log::info("Task {$this->taskId} created", [
            'max_concurrent' => $this->maxConcurrent,
        ]);
    }

    /**
     * Execute the job
     */
    public function handle(): void
    {
        $currentAttempt = $this->getCurrentAttempt();

        Log::info("Task {$this->taskId} started processing", [
            'job_attempt' => $currentAttempt,
            'property_attempt' => $this->currentAttempt,
            'queue_attempts' => $this->job ? $this->job->attempts() : 'unknown',
            'max_attempts' => $this->tries,
            'max_concurrent' => $this->maxConcurrent,
        ]);

        // Semaphore key based on the number of $maxConcurrent, so Jobs with different $maxConcurrent would be separated
        // This is just for demo purposes
        $semaphoreKey = self::SEMAPHORE_KEY . ':' . $this->maxConcurrent;

        // Create semaphore instance for coordination
        $semaphore = Semaphore::create(
            $semaphoreKey,
            $this->maxConcurrent,
            self::SEMAPHORE_TTL
        );

        try {
            $this->broadcast('processing', 'Starting processing', $currentAttempt);
            sleep(rand(1, 2));

            $this->broadcast('checking_lock', 'Checking semaphore availability', $currentAttempt);

            // Attempt to acquire semaphore
            if ($semaphore->acquire(self::SEMAPHORE_ACQUIRE_TIMEOUT)) {
                $this->handleAcquiredSemaphore($semaphore, $currentAttempt);
            } else {
                $this->handleSemaphoreUnavailable($semaphore, $currentAttempt);
            }
        } finally {
            // Always ensure semaphore is released
            $semaphore->release();
        }
    }

    /**
     * Handle successful semaphore acquisition
     *
     * @param SemaphoreInterface $semaphore Acquired semaphore instance
     * @param int $attempt Current attempt number
     */
    private function handleAcquiredSemaphore(SemaphoreInterface $semaphore, int $attempt): void
    {
        // Get semaphore statistics
        $stats = $semaphore->getStats();
        $currentCount = $stats->currentCount;

        $this->broadcast(
            'lock_acquired',
            "Semaphore acquired! ({$currentCount}/{$this->maxConcurrent} slots occupied)",
            $attempt
        );

        // Process with progress updates
        $this->processWithProgress($attempt);

        // Task completed successfully
        $this->broadcast('completed', 'Task completed successfully!', $attempt);
    }

    /**
     * Handle unavailable semaphore (retry logic)
     *
     * @param SemaphoreInterface $semaphore Semaphore instance
     * @param int $currentAttempt Current attempt number
     */
    private function handleSemaphoreUnavailable(SemaphoreInterface $semaphore, int $currentAttempt): void
    {
        // Get semaphore statistics for logging
        $stats = $semaphore->getStats();
        $currentCount = $stats->currentCount;
        $remainingAttempts = $this->tries - $currentAttempt;

        // Broadcast lock failure
        $this->broadcast(
            'lock_failed',
            "Failed to acquire semaphore ({$currentCount}/{$this->maxConcurrent} occupied). " .
                "Remaining attempts: {$remainingAttempts}",
            $currentAttempt
        );

        Log::warning("Task {$this->taskId} failed to acquire semaphore, attempt {$currentAttempt}");

        if ($currentAttempt < $this->tries) {
            $nextAttempt = $currentAttempt + 1;
            Log::info("Task {$this->taskId} will retry with new job (attempt {$nextAttempt})");

            // Broadcast queued status for retry
            $this->broadcast('queued', 'Retrying task', $nextAttempt);

            // Dispatch new job with same maxConcurrent value
            self::dispatch($this->taskId, $nextAttempt, $this->maxConcurrent)
                ->delay(now()->addSeconds($this->backoff[$currentAttempt - 1] ?? 2));
        } else {
            // Final failure after all attempts
            $this->broadcast('failed', 'Task failed after all retry attempts', $currentAttempt);
            throw new \Exception("Semaphore unavailable after {$this->tries} attempts");
        }
    }

    /**
     * Process task with progress updates
     *
     * @param int $attempt Current attempt number
     */
    private function processWithProgress(int $attempt): void
    {
        $steps = 4;
        $stepTime = 1;

        for ($i = 1; $i <= $steps; $i++) {
            sleep($stepTime + rand(0, 1));

            // Broadcast progress update
            $this->broadcastWithProgress($i, $steps, "Processing: {$i}/{$steps} steps", $attempt);
        }
    }

    /**
     * Get current attempt number
     *
     * @return int Current attempt
     */
    protected function getCurrentAttempt(): int
    {
        return $this->currentAttempt;
    }

    /**
     * Handle job failure
     *
     * @param \Throwable $exception Exception that caused failure
     */
    public function failed(\Throwable $exception): void
    {
        $currentAttempt = $this->getCurrentAttempt();

        Log::error("Task {$this->taskId} failed permanently after {$currentAttempt} attempts: " .
            $exception->getMessage());

        // Broadcast final failure status
        $this->broadcast('failed', 'Task failed with error', $currentAttempt);
    }
}
