<?php

namespace App\Contracts;

interface SemaphoreInterface
{
    public function acquire(int $timeout = 0): bool;
    public function tryAcquire(int $timeout = 0): bool;
    public function release(): bool;
    public function isAcquiredByMe(): bool;
    public function getStats(): array;
    public function getKey(): string;
    public function getMaxConcurrent(): int;
    public function getTimeout(): int;
}
