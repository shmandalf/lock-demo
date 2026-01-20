<?php

namespace App\Services;

use App\Contracts\SemaphoreInterface;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RedisSemaphore implements SemaphoreInterface
{
    private string $key;
    private int $maxConcurrent;
    private int $timeout;
    private string $identifier;
    private string $lockKey;

    public function __construct(string $key, int $maxConcurrent = 3, int $timeout = 30)
    {
        $this->key = "semaphore:{$key}";
        $this->maxConcurrent = $maxConcurrent;
        $this->timeout = $timeout;
        $this->identifier = $this->generateIdentifier();
        $this->lockKey = "semaphore_lock:{$key}";
    }

    /**
     * Генерирует уникальный идентификатор
     */
    private function generateIdentifier(): string
    {
        $hostname = gethostname();
        $pid = getmypid();
        $random = Str::random(8);

        return "{$hostname}:{$pid}:{$random}:" . microtime(true);
    }

    /**
     * Пытается захватить семафор с использованием Redis MULTI для атомарности
     */
    public function acquire(int $timeout = 0): bool
    {
        if ($timeout > 0) {
            return $this->tryAcquire($timeout);
        }

        return $this->acquireInternal();
    }

    /**
     * Пытается захватить семафор с таймаутом
     */
    public function tryAcquire(int $timeout = 5): bool
    {
        $startTime = microtime(true);
        $attempt = 0;
        $maxAttempts = 100; // Защита от бесконечного цикла

        while ((microtime(true) - $startTime) < $timeout && $attempt < $maxAttempts) {
            $attempt++;

            if ($this->acquireInternal()) {
                Log::debug("Semaphore {$this->key} acquired on attempt {$attempt}");
                return true;
            }

            // Exponential backoff с максимальной задержкой 500ms
            $delay = min(500000, 100000 * pow(1.5, $attempt - 1));
            usleep($delay);
        }

        Log::debug("Semaphore {$this->key} timeout after {$timeout}s, {$attempt} attempts");
        return false;
    }

    /**
     * Внутренний метод захвата семафора
     */
    private function acquireInternal(): bool
    {
        try {
            $currentTime = microtime(true);

            // Используем Lua script для атомарности
            $lua = <<<'LUA'
                local key = KEYS[1]
                local identifier = ARGV[1]
                local current_time = tonumber(ARGV[2])
                local timeout = tonumber(ARGV[3])
                local max_concurrent = tonumber(ARGV[4])

                -- Удаляем просроченные записи
                redis.call('ZREMRANGEBYSCORE', key, 0, current_time - timeout)

                -- Получаем текущее количество
                local count = redis.call('ZCARD', key)

                -- Если есть место - добавляем себя
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
                $this->key,
                $this->identifier,
                $currentTime,
                $this->timeout,
                $this->maxConcurrent
            );

            if ($result === 1) {
                $currentCount = $this->getCurrentCount();
                Log::debug("Semaphore {$this->key} acquired by {$this->identifier}, count: {$currentCount}/{$this->maxConcurrent}");
                return true;
            } else {
                Log::debug("Semaphore {$this->key} is full, count: " . $this->getCurrentCount() . "/{$this->maxConcurrent}");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Semaphore acquire error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Освобождает семафор
     */
    public function release(): bool
    {
        try {
            // Используем Lua script для атомарного удаления
            $lua = <<<'LUA'
                local key = KEYS[1]
                local identifier = ARGV[1]

                -- Удаляем идентификатор
                local removed = redis.call('ZREM', key, identifier)

                -- Если семафор пуст - удаляем ключ
                if redis.call('ZCARD', key) == 0 then
                    redis.call('DEL', key)
                end

                return removed
LUA;

            $removed = Redis::eval($lua, 1, $this->key, $this->identifier);

            if ($removed) {
                Log::info("Semaphore {$this->key} released by {$this->identifier}");
                return true;
            } else {
                Log::warning("Semaphore {$this->key}: identifier {$this->identifier} not found for release");
                return false;
            }
        } catch (\Exception $e) {
            Log::error("Semaphore release error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Проверяет, захвачен ли семафор текущим идентификатором
     */
    public function isAcquiredByMe(): bool
    {
        try {
            $currentTime = microtime(true);

            // Lua script для атомарной проверки
            $lua = <<<'LUA'
                local key = KEYS[1]
                local identifier = ARGV[1]
                local current_time = tonumber(ARGV[2])
                local timeout = tonumber(ARGV[3])

                -- Получаем время добавления
                local score = redis.call('ZSCORE', key, identifier)

                if score == false then
                    return 0
                end

                -- Проверяем не просрочен ли
                if (current_time - tonumber(score)) > timeout then
                    redis.call('ZREM', key, identifier)
                    return 0
                end

                return 1
LUA;

            $result = Redis::eval($lua, 1, $this->key, $this->identifier, $currentTime, $this->timeout);
            return $result === 1;
        } catch (\Exception $e) {
            Log::error("Semaphore check acquired error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Возвращает статистику по семафору
     */
    public function getStats(): array
    {
        try {
            $currentTime = microtime(true);

            // Lua script для атомарного получения статистики
            $lua = <<<'LUA'
                local key = KEYS[1]
                local current_time = tonumber(ARGV[1])
                local timeout = tonumber(ARGV[2])

                -- Очищаем просроченные
                redis.call('ZREMRANGEBYSCORE', key, 0, current_time - timeout)

                local count = redis.call('ZCARD', key)
                local ttl = redis.call('TTL', key)

                return {count, ttl}
LUA;

            $result = Redis::eval($lua, 1, $this->key, $currentTime, $this->timeout);
            $count = $result[0] ?? 0;
            $ttl = $result[1] ?? 0;

            return [
                'key' => $this->key,
                'max_concurrent' => $this->maxConcurrent,
                'current_count' => $count,
                'available' => $this->maxConcurrent - $count,
                'timeout' => $this->timeout,
                'ttl' => $ttl > 0 ? $ttl : 0,
                'is_full' => $count >= $this->maxConcurrent,
                'identifier' => $this->identifier,
                'is_acquired_by_me' => $this->isAcquiredByMe(),
            ];
        } catch (\Exception $e) {
            Log::error("Semaphore stats error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Возвращает текущее количество захвативших семафор
     */
    public function getCurrentCount(): int
    {
        try {
            $currentTime = microtime(true);

            // Lua script для атомарного подсчета
            $lua = <<<'LUA'
                local key = KEYS[1]
                local current_time = tonumber(ARGV[1])
                local timeout = tonumber(ARGV[2])

                redis.call('ZREMRANGEBYSCORE', key, 0, current_time - timeout)
                return redis.call('ZCARD', key)
LUA;

            return Redis::eval($lua, 1, $this->key, $currentTime, $this->timeout) ?: 0;
        } catch (\Exception $e) {
            Log::error("Semaphore count error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Получить ключ семафора
     */
    public function getKey(): string
    {
        return str_replace('semaphore:', '', $this->key);
    }

    /**
     * Получить максимальное количество одновременных захватов
     */
    public function getMaxConcurrent(): int
    {
        return $this->maxConcurrent;
    }

    /**
     * Получить таймаут семафора
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Получить идентификатор текущего процесса
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Использование семафора в try-finally блоке
     */
    public function withSemaphore(callable $callback)
    {
        if (!$this->acquire()) {
            throw new \RuntimeException("Could not acquire semaphore {$this->key}");
        }

        try {
            return $callback();
        } finally {
            $this->release();
        }
    }

    /**
     * Очистить все записи семафора
     */
    public function clear(): bool
    {
        try {
            $deleted = Redis::del($this->key);
            Log::warning("Semaphore {$this->key} cleared, deleted: {$deleted}");
            return $deleted > 0;
        } catch (\Exception $e) {
            Log::error("Semaphore clear error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить список всех идентификаторов
     */
    public function getIdentifiers(): array
    {
        try {
            $currentTime = microtime(true);

            // Lua script для получения идентификаторов
            $lua = <<<'LUA'
                local key = KEYS[1]
                local current_time = tonumber(ARGV[1])
                local timeout = tonumber(ARGV[2])

                -- Очищаем просроченные
                redis.call('ZREMRANGEBYSCORE', key, 0, current_time - timeout)

                return redis.call('ZRANGE', key, 0, -1)
LUA;

            return Redis::eval($lua, 1, $this->key, $currentTime, $this->timeout) ?: [];
        } catch (\Exception $e) {
            Log::error("Semaphore get identifiers error: " . $e->getMessage());
            return [];
        }
    }
}
