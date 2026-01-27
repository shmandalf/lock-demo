<?php

namespace App\Services\Semaphores;

use App\Contracts\SemaphoreInterface;
use App\Services\Semaphores\DTO\Stats;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use DateTimeImmutable;

class LegacySemaphore implements SemaphoreInterface
{
    /**
     * Redis key prefix for semaphore storage
     */
    private const KEY_PREFIX = 'legacy-semaphore:';

    /**
     * Maximum number of concurrent acquisitions
     */
    private int $maxConcurrent;

    /**
     * Timeout in seconds for automatic release
     */
    private int $timeout;

    /**
     * Unique identifier for this process instance
     */
    private string $identifier;

    /**
     * Full Redis key including prefix
     */
    private string $redisKey;

    /**
     * Index of the acquired slot (null if not acquired)
     */
    private ?int $slotIndex = null;

    /**
     * Flag indicating if semaphore is currently acquired
     */
    private bool $isAcquired = false;

    /**
     * Create a new Legacy semaphore instance
     *
     * @param string $key Semaphore identifier (without prefix)
     * @param int $maxConcurrent Maximum number of concurrent acquisitions
     * @param int $timeout Timeout in seconds for automatic release
     */
    public function __construct(string $key, int $maxConcurrent = 3, int $timeout = 30)
    {
        $this->redisKey = self::KEY_PREFIX . $key;
        $this->maxConcurrent = $maxConcurrent;
        $this->timeout = $timeout;
        $this->identifier = $this->generateIdentifier();
    }

    /**
     * Acquire semaphore with timeout
     */
    public function acquire(int $timeout): bool
    {
        if ($timeout < 1) {
            throw new \InvalidArgumentException("Timeout must be at least 1 second");
        }

        $startTime = microtime(true);
        $attempt = 0;

        while ((microtime(true) - $startTime) < $timeout) {
            $attempt++;

            if ($this->acquireInternal()) {
                Log::debug("Legacy semaphore acquired on attempt {$attempt}");
                return true;
            }

            // Wait before retry
            // TODO: use exponential back-off? or add an ability to apply a custom strategy?
            usleep(min(500000, 100000 * $attempt));
        }

        Log::debug("Legacy semaphore timeout after {$timeout}s, {$attempt} attempts");
        return false;
    }

    /**
     * Internal method for semaphore acquisition
     *
     * @return bool true if semaphore was acquired, false otherwise
     */
    private function acquireInternal(): bool
    {
        try {
            // Check TTL for all slots and renew if needed
            for ($slot = 0; $slot < $this->maxConcurrent; ++$slot) {
                $slotKey = $this->getSlotKey($slot);
                if (Redis::ttl($slotKey) == -1) { // Key exists but has no TTL
                    Redis::expire($slotKey, $this->timeout);
                }
            }

            // Find and acquire an available slot
            for ($slot = 0; $slot < $this->maxConcurrent; ++$slot) {
                $slotKey = $this->getSlotKey($slot);

                // Use SET with NX and EX for atomic operation
                if (Redis::set($slotKey, $this->identifier, 'NX', 'EX', $this->timeout)) {
                    $this->slotIndex = $slot;
                    $this->isAcquired = true;

                    Log::debug("Legacy semaphore slot acquired: {$slotKey} by {$this->identifier}");
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            Log::error("Legacy semaphore acquire error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Release the acquired semaphore
     *
     * @return bool true if successfully released, false otherwise
     */
    public function release(): bool
    {
        if (!$this->isAcquired || $this->slotIndex === null) {
            Log::warning("Legacy semaphore not acquired or already released");
            return false;
        }

        try {
            $slotKey = $this->getSlotKey($this->slotIndex);

            // Verify we're releasing our own slot
            $currentValue = Redis::get($slotKey);
            if ($currentValue === $this->identifier) {
                $deleted = Redis::del($slotKey);
                if ($deleted) {
                    Log::info("Legacy semaphore released: {$slotKey}");
                    $this->resetState();
                    return true;
                }
            } else {
                Log::warning("Legacy semaphore identifier mismatch on release");
            }

            $this->resetState();
            return false;
        } catch (\Exception $e) {
            Log::error("Legacy semaphore release error: " . $e->getMessage());
            $this->resetState();
            return false;
        }
    }

    /**
     * Check if the semaphore is currently acquired by this process
     *
     * @return bool true if acquired by current identifier, false otherwise
     */
    public function isAcquiredByMe(): bool
    {
        if (!$this->isAcquired || $this->slotIndex === null) {
            return false;
        }

        try {
            $slotKey = $this->getSlotKey($this->slotIndex);
            $currentValue = Redis::get($slotKey);
            return $currentValue === $this->identifier;
        } catch (\Exception $e) {
            Log::error("Legacy semaphore check error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get semaphore statistics as DTO
     *
     * @return Stats Semaphore statistics DTO
     */
    public function getStats(): Stats
    {
        try {
            $currentCount = $this->getCurrentCount();
            $occupiedSlots = $this->getOccupiedSlotNumbers();

            return new Stats(
                key: str_replace(self::KEY_PREFIX, '', $this->redisKey),
                maxConcurrent: $this->maxConcurrent,
                currentCount: $currentCount,
                available: $this->maxConcurrent - $currentCount,
                timeout: $this->timeout,
                ttl: $this->getMinTtl(),
                isFull: $currentCount >= $this->maxConcurrent,
                identifier: $this->identifier,
                isAcquiredByMe: $this->isAcquiredByMe(),
                createdAt: new DateTimeImmutable(),
                driver: 'legacy',
                metadata: [
                    'occupied_slots' => $occupiedSlots,
                    'my_slot_index' => $this->slotIndex,
                    'slot_count' => $this->maxConcurrent,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Legacy semaphore stats error: " . $e->getMessage());
            throw new \RuntimeException("Failed to get semaphore statistics: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get current number of acquired semaphore slots
     *
     * @return int Number of currently occupied slots
     */
    public function getCurrentCount(): int
    {
        try {
            $occupiedCount = 0;

            for ($slot = 0; $slot < $this->maxConcurrent; ++$slot) {
                $slotKey = $this->getSlotKey($slot);
                if (Redis::exists($slotKey)) {
                    $occupiedCount++;
                }
            }

            return $occupiedCount;
        } catch (\Exception $e) {
            Log::error("Legacy semaphore count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get maximum number of concurrent acquisitions
     *
     * @return int Maximum concurrent slots
     */
    public function getMaxConcurrent(): int
    {
        return $this->maxConcurrent;
    }

    /**
     * Get semaphore timeout in seconds
     *
     * @return int Timeout value
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Get unique identifier for this semaphore instance
     *
     * @return string Process identifier
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Clear all semaphore entries (use with caution)
     *
     * @return bool true if cleared successfully, false otherwise
     */
    public function clear(): bool
    {
        try {
            $deleted = 0;

            for ($slot = 0; $slot < $this->maxConcurrent; ++$slot) {
                $slotKey = $this->getSlotKey($slot);
                $deleted += Redis::del($slotKey);
            }

            Log::warning("Legacy semaphore cleared: {$this->redisKey}, deleted: {$deleted} slots");
            $this->resetState();
            return $deleted > 0;
        } catch (\Exception $e) {
            Log::error("Legacy semaphore clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate unique identifier for this process instance
     *
     * @return string Unique identifier
     */
    private function generateIdentifier(): string
    {
        $hostname = gethostname();
        $pid = getmypid();
        $random = Str::random(8);

        return "{$hostname}:{$pid}:{$random}:" . microtime(true);
    }

    /**
     * Get Redis key for specific slot
     *
     * @param int $slot Slot index (0-based)
     * @return string Redis key for the slot
     */
    private function getSlotKey(int $slot): string
    {
        return $this->redisKey . $slot;
    }

    /**
     * Get occupied slot numbers
     *
     * @return array List of occupied slot indices
     */
    private function getOccupiedSlotNumbers(): array
    {
        $occupied = [];

        for ($slot = 0; $slot < $this->maxConcurrent; ++$slot) {
            $slotKey = $this->getSlotKey($slot);
            if (Redis::exists($slotKey)) {
                $occupied[] = $slot;
            }
        }

        return $occupied;
    }

    /**
     * Get minimum TTL among all slots
     *
     * @return int Minimum TTL in seconds, 0 if no slots occupied
     */
    private function getMinTtl(): int
    {
        $minTtl = 0;
        $found = false;

        for ($slot = 0; $slot < $this->maxConcurrent; ++$slot) {
            $slotKey = $this->getSlotKey($slot);
            $ttl = Redis::ttl($slotKey);

            if ($ttl > 0) {
                if (!$found || $ttl < $minTtl) {
                    $minTtl = $ttl;
                    $found = true;
                }
            }
        }

        return $found ? $minTtl : 0;
    }

    /**
     * Reset internal state
     */
    private function resetState(): void
    {
        $this->slotIndex = null;
        $this->isAcquired = false;
    }
}
