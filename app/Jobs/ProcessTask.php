<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Traits\BroadcastsTaskStatus;
use Illuminate\Support\Facades\Log;

class ProcessTask implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    use BroadcastsTaskStatus;

    public string $taskId;
    public int $currentAttempt = 1;

    protected string $semaphoreKey = 'task_processing';
    protected int $semaphoreMaxConcurrent = 2; // Значение по умолчанию
    protected int $semaphoreTimeout = 10;
    protected int $semaphoreAcquireTimeout = 3;

    public $tries = 9;
    public $backoff = [2, 5, 10];

    public function __construct(string $taskId, int $initialAttempt = 1, int $maxConcurrent = null)
    {
        $this->taskId = $taskId;
        $this->currentAttempt = $initialAttempt;

        if ($maxConcurrent !== null) {
            $this->semaphoreMaxConcurrent = max(1, min(10, $maxConcurrent));

            // Обновляем ключ семафора с указанием лимита (опционально)
            $this->semaphoreKey = "task_processing:max:{$this->semaphoreMaxConcurrent}";
        }

        // ВАЖНО: Отправляем событие сразу при создании задачи!
        $this->broadcast('queued', 'Задача добавлена в очередь', $initialAttempt);

        // Логируем установленное значение
        Log::info("Task {$this->taskId} created", [
            'max_concurrent' => $this->semaphoreMaxConcurrent,
            'semaphore_key' => $this->semaphoreKey,
        ]);
    }

    public function handle(): void
    {
        $currentAttempt = $this->getCurrentAttempt();

        Log::info("Task {$this->taskId} started", [
            'job_attempt' => $currentAttempt,
            'property_attempt' => $this->currentAttempt,
            'queue_attempts' => $this->job ? $this->job->attempts() : 'unknown',
            'max_attempts' => $this->tries,
            'max_concurrent' => $this->semaphoreMaxConcurrent,
        ]);

        $semaphore = $this->createSemaphore(
            $this->semaphoreKey,
            $this->semaphoreMaxConcurrent,
            $this->semaphoreTimeout
        );

        try {
            $this->broadcast('processing', 'Начинаем обработку', $currentAttempt);
            sleep(rand(1, 2));

            $this->broadcast('checking_lock', 'Проверка семафора', $currentAttempt);

            if ($this->acquireSemaphore($semaphore, $this->semaphoreAcquireTimeout)) {
                $this->handleAcquiredSemaphore($semaphore, $currentAttempt);
            } else {
                $this->handleSemaphoreUnavailable($semaphore, $currentAttempt);
            }
        } finally {
            $this->releaseSemaphore($semaphore);
        }
    }

    /**
     * Обработка когда семафор захвачен
     */
    private function handleAcquiredSemaphore($semaphore, int $attempt): void
    {
        // Семафор захвачен
        $stats = $this->getSemaphoreStats($semaphore);
        $currentCount = $stats['current_count'] ?? 0;

        $this->broadcast(
            'lock_acquired',
            "Семафор захвачен! ({$currentCount}/{$this->semaphoreMaxConcurrent} слотов занято)",
            $attempt
        );

        sleep(rand(1, 2));

        // Обработка с прогрессом
        $this->processWithProgress($attempt);

        // Завершено успешно
        $this->broadcast('completed', 'Задача завершена!', $attempt);
    }

    /**
     * Handle unavailable semaphore (retry logic)
     */
    private function handleSemaphoreUnavailable($semaphore, int $currentAttempt): void
    {
        $stats = $this->getSemaphoreStats($semaphore);
        $currentCount = $stats['current_count'] ?? 0;
        $remainingAttempts = $this->tries - $currentAttempt;

        // Lock failed
        $this->broadcast(
            'lock_failed',
            "Не удалось захватить семафор ({$currentCount}/{$this->semaphoreMaxConcurrent}). " .
                "Осталось попыток: {$remainingAttempts}",
            $currentAttempt
        );

        Log::warning("Task {$this->taskId} failed to acquire semaphore, attempt {$currentAttempt}");

        if ($currentAttempt < $this->tries) {
            $nextAttempt = $currentAttempt + 1;
            Log::info("Task {$this->taskId} will retry with new job (attempt {$nextAttempt})");

            // При ретрае тоже отправляем событие!
            $this->broadcast('queued', 'Повторная попытка', $nextAttempt);

            // При ретрае передаем то же значение maxConcurrent
            self::dispatch($this->taskId, $nextAttempt, $this->semaphoreMaxConcurrent)
                ->delay(now()->addSeconds($this->backoff[$currentAttempt - 1] ?? 2));
        } else {
            // Failed
            $this->broadcast('failed', 'Задача завершилась с ошибкой после всех попыток', $currentAttempt);
            throw new \Exception("Semaphore unavailable after {$this->tries} attempts");
        }
    }

    /**
     * Process with progress updates
     */
    private function processWithProgress(int $attempt): void
    {
        $steps = 4;
        $stepTime = 1;

        for ($i = 1; $i <= $steps; $i++) {
            sleep($stepTime + rand(0, 1));
            // Progress
            $this->broadcastWithProgress($i, $steps, "Обработка: {$i}/{$steps} шагов", $attempt);
        }
    }

    protected function getCurrentAttempt(): int
    {
        return $this->currentAttempt;
    }

    public function failed(\Throwable $exception): void
    {
        $currentAttempt = $this->getCurrentAttempt();
        Log::error("Task {$this->taskId} failed permanently after {$currentAttempt} attempts: " . $exception->getMessage());

        // Failed при ошибке
        $this->broadcast('failed', 'Задача завершилась с ошибкой', $currentAttempt);
    }
}
