<?php

namespace App\Services\Semaphores\DTO;

use DateTimeImmutable;

/**
 * Data Transfer Object for semaphore statistics
 */
class Stats
{
    public function __construct(
        public readonly string $key,
        public readonly int $maxConcurrent,
        public readonly int $currentCount,
        public readonly int $available,
        public readonly int $timeout,
        public readonly int $ttl,
        public readonly bool $isFull,
        public readonly string $identifier,
        public readonly bool $isAcquiredByMe,
        public readonly DateTimeImmutable $createdAt,
        public readonly string $driver,
        public readonly array $metadata = []
    ) {}

    /**
     * Convert DTO to array
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'max_concurrent' => $this->maxConcurrent,
            'current_count' => $this->currentCount,
            'available' => $this->available,
            'timeout' => $this->timeout,
            'ttl' => $this->ttl,
            'is_full' => $this->isFull,
            'identifier' => $this->identifier,
            'is_acquired_by_me' => $this->isAcquiredByMe,
            'created_at' => $this->createdAt->format(DateTimeImmutable::ATOM),
            'driver' => $this->driver,
            'metadata' => $this->metadata,
        ];
    }

    /**
     * Check if there are available slots
     */
    public function hasAvailableSlots(): bool
    {
        return $this->available > 0;
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentage(): float
    {
        if ($this->maxConcurrent === 0) {
            return 0.0;
        }

        return round(($this->currentCount / $this->maxConcurrent) * 100, 2);
    }
}
