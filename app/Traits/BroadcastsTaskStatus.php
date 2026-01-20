<?php

namespace App\Traits;

use App\Events\TaskStatusChanged;
use Illuminate\Support\Facades\Log;

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
                array_merge(['message' => $message], $extraData)
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
            $message ?: "Обработка: {$currentStep}/{$totalSteps} шагов",
            $attempt,
            [
                'progress' => $progress,
                'step' => $currentStep,
                'total_steps' => $totalSteps,
            ]
        );
    }

    /**
     * Get current attempt (must be implemented by job)
     */
    abstract protected function getCurrentAttempt(): int;
}
