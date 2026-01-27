<?php

namespace App\Services\Semaphores;

use App\Contracts\SemaphoreInterface;
use App\Services\Semaphores\DTO\Stats;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use DateTimeImmutable;

class RedisSemaphore implements SemaphoreInterface
{
    /**
     * Redis key prefix for semaphore storage
     */
    private const KEY_PREFIX = 'semaphore:';

    /**
     * Maximum number of concurrent acquisitions
     */
    private int $maxConcurrent;

    /**
     * TTL in seconds for automatic release
     */
    private int $ttl;

    /**
     * Unique identifier for this process instance
     */
    private string $identifier;

    /**
     * Full Redis key including prefix
     */
    private string $redisKey;

    /**
     * Create a new Redis semaphore instance
     *
     * @param string $key Semaphore identifier (without prefix)
     * @param int $maxConcurrent Maximum number of concurrent acquisitions
     * @param int $ttl Timeout in seconds for automatic release
     */
    public function __construct(string $key, int $maxConcurrent = 3, int $ttl = 30)
    {
        $this->redisKey = self::KEY_PREFIX . $key;
        $this->maxConcurrent = $maxConcurrent;
        $this->ttl = $ttl;
        $this->identifier = $this->generateIdentifier();
    }

    /**
     * Acquire semaphore with timeout
     *
     * @param int $acquireTimeout Maximum wait time in seconds
     *                            Any value is accepted, developer's responsibility
     */
    public function acquire(int $acquireTimeout): bool
    {
        // No validation - developer's responsibility to provide sensible timeout
        if ($acquireTimeout <= 0) {
            // Zero or negative timeout means non-blocking attempt
            return $this->acquireInternal();
        }

        $startTime = microtime(true);
        $attempt = 0;

        while ((microtime(true) - $startTime) < $acquireTimeout) {
            $attempt++;

            if ($this->acquireInternal()) {
                Log::debug("Acquired on attempt {$attempt}");
                return true;
            }

            // Wait before retry
            usleep(min(500000, 100000 * $attempt));
        }

        return false;
    }

    /**
     * Internal method for semaphore acquisition using Lua script
     *
     * @return bool true if semaphore was acquired, false otherwise
     */
    private function acquireInternal(): bool
    {
        try {
            $currentTime = microtime(true);

            // Lua script for atomic operations
            $lua = <<<'LUA'
                local key = KEYS[1]
                local identifier = ARGV[1]
                local current_time = tonumber(ARGV[2])
                local timeout = tonumber(ARGV[3])
                local max_concurrent = tonumber(ARGV[4])

                -- Remove expired entries
                redis.call('ZREMRANGEBYSCORE', key, 0, current_time - timeout)

                -- Get current count
                local count = redis.call('ZCARD', key)

                -- Add self if there is available slot
                if count < max_concurrent then
                    redis.call('ZADD', key, current_time, identifier)
                    redis.call('EXPIRE', key, timeout)
                    return 1
                end

                return 0
LUA;

            $result = Redis::eval(
                $lua,
                1,
                $this->redisKey,
                $this->identifier,
                $currentTime,
                $this->ttl,
                $this->maxConcurrent
            );

            if ($result === 1) {
                $currentCount = $this->getCurrentCount();
                Log::debug("Semaphore {$this->redisKey} acquired by {$this->identifier}, count: {$currentCount}/{$this->maxConcurrent}");
                return true;
            } else {
                Log::debug("Semaphore {$this->redisKey} is full, count: " . $this->getCurrentCount() . "/{$this->maxConcurrent}");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Semaphore acquire error: " . $e->getMessage());
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
        try {
            // Lua script for atomic removal
            $lua = <<<'LUA'
                local key = KEYS[1]
                local identifier = ARGV[1]

                -- Remove the identifier
                local removed = redis.call('ZREM', key, identifier)

                -- Delete key if semaphore is empty
                if redis.call('ZCARD', key) == 0 then
                    redis.call('DEL', key)
                end

                return removed
LUA;

            $removed = Redis::eval($lua, 1, $this->redisKey, $this->identifier);

            if ($removed) {
                Log::info("Semaphore {$this->redisKey} released by {$this->identifier}");
                return true;
            } else {
                Log::warning("Semaphore {$this->redisKey}: identifier {$this->identifier} not found for release");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Semaphore release error: " . $e->getMessage());
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
        try {
            $currentTime = microtime(true);

            // Lua script for atomic check
            $lua = <<<'LUA'
                local key = KEYS[1]
                local identifier = ARGV[1]
                local current_time = tonumber(ARGV[2])
                local ttl = tonumber(ARGV[3])

                -- Get timestamp when identifier was added
                local score = redis.call('ZSCORE', key, identifier)

                if score == false then
                    return 0
                end

                -- Check if expired
                if (current_time - tonumber(score)) > ttl then
                    redis.call('ZREM', key, identifier)
                    return 0
                end

                return 1
LUA;

            $result = Redis::eval($lua, 1, $this->redisKey, $this->identifier, $currentTime, $this->ttl);
            return $result === 1;
        } catch (\Exception $e) {
            Log::error("Semaphore check acquired error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get semaphore statistics as DTO
     *
     * @return Stats Semaphore statistics DTO
     * @throws \RuntimeException If statistics cannot be retrieved
     */
    public function getStats(): Stats
    {
        try {
            $currentTime = microtime(true);

            // Lua script for atomic statistics retrieval
            $lua = <<<'LUA'
                local key = KEYS[1]
                local current_time = tonumber(ARGV[1])
                local ttl = tonumber(ARGV[2])

                -- Clean up expired entries
                redis.call('ZREMRANGEBYSCORE', key, 0, current_time - ttl)

                local count = redis.call('ZCARD', key)
                local ttl = redis.call('TTL', key)

                return {count, ttl}
LUA;

            $result = Redis::eval($lua, 1, $this->redisKey, $currentTime, $this->ttl);
            $count = $result[0] ?? 0;
            $ttl = $result[1] ?? 0;

            // Extract original key without prefix for DTO
            $originalKey = str_replace(self::KEY_PREFIX, '', $this->redisKey);

            return new Stats(
                key: $originalKey,
                maxConcurrent: $this->maxConcurrent,
                currentCount: $count,
                available: $this->maxConcurrent - $count,
                ttl: $ttl > 0 ? $ttl : 0,
                isFull: $count >= $this->maxConcurrent,
                identifier: $this->identifier,
                isAcquiredByMe: $this->isAcquiredByMe(),
                createdAt: new DateTimeImmutable(),
                driver: 'redis',
                metadata: [
                    'redis_key' => $this->redisKey,
                    'implementation' => 'sorted_set',
                    'lua_scripts_used' => true,
                ]
            );
        } catch (\Exception $e) {
            Log::error("Semaphore stats error: " . $e->getMessage());
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
            $currentTime = microtime(true);

            // Lua script for atomic count
            $lua = <<<'LUA'
                local key = KEYS[1]
                local current_time = tonumber(ARGV[1])
                local ttl = tonumber(ARGV[2])

                redis.call('ZREMRANGEBYSCORE', key, 0, current_time - ttl)
                return redis.call('ZCARD', key)
LUA;

            return Redis::eval($lua, 1, $this->redisKey, $currentTime, $this->ttl) ?: 0;
        } catch (\Exception $e) {
            Log::error("Semaphore count error: " . $e->getMessage());
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
     * Get semaphore TTL in seconds
     *
     * @return int Timeout value
     */
    public function getTtl(): int
    {
        return $this->ttl;
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
            $deleted = Redis::del($this->redisKey);
            Log::warning("Semaphore {$this->redisKey} cleared, deleted: {$deleted}");
            return $deleted > 0;
        } catch (\Exception $e) {
            Log::error("Semaphore clear error: " . $e->getMessage());
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
}
