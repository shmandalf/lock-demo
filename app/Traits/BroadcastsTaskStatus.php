<?php

namespace App\Traits;

use App\Events\TaskStatusChanged;
use Illuminate\Support\Facades\Log;

/**
 * Trait for broadcasting task status changes via websocket
 *
 * This trait is designed to be used in Job classes that implement task processing.
 * It expects the following properties to be available in the using class:
 *
 * @property string $taskId Task identifier (required)
 * @property int $maxConcurrent Maximum concurrent tasks (required)
 * @property int $currentAttempt Current retry attempt (optional, defaults to 1)
 *
 * @see \App\Jobs\ProcessTask Example Job using this trait
 */
trait BroadcastsTaskStatus
{
    /**
     * Broadcast task status change
     */
    protected function broadcast(string $status, string $message = '', ?int $attempt = null, array $extraData = []): void
    {
        try {
            if ($attempt === null) {
                $attempt = $this->getCurrentAttempt();
            }

            event(new TaskStatusChanged(
                $this->taskId,
                $status,
                $attempt,
                array_merge(['message' => $message, 'max_concurrent' => $this->maxConcurrent], $extraData)
            ));
        } catch (\Exception $e) {
            Log::warning("Broadcast failed for task {$this->taskId}: " . $e->getMessage());
        }
    }

    /**
     * Broadcast with progress
     */
    protected function broadcastWithProgress(int $currentStep, int $totalSteps, string $message = '', ?int $attempt = null): void
    {
        $progress = ($currentStep / $totalSteps) * 100;
        $this->broadcast(
            'processing_progress',
            $message ?: "Processing: {$currentStep}/{$totalSteps} steps",
            $attempt,
            [
                'progress' => $progress,
                'step' => $currentStep,
                'total_steps' => $totalSteps,
                'max_concurrent' => $this->maxConcurrent,
            ]
        );
    }

    /**
     * Get current attempt (must be implemented by job)
     */
    abstract protected function getCurrentAttempt(): int;
}
