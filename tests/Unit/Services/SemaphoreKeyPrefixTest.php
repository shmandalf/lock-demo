<?php

namespace Tests\Unit\Services;

use App\Services\Semaphores\LegacySemaphore;
use App\Services\Semaphores\RedisSemaphore;
use PHPUnit\Framework\TestCase;

class SemaphoreKeyPrefixTest extends TestCase
{
    public function test_redis_semaphore_key_prefix(): void
    {
        $semaphore = new RedisSemaphore('my-key', 1, 1);

        $reflection = new \ReflectionClass($semaphore);
        $redisKeyProperty = $reflection->getProperty('redisKey');
        $redisKey = $redisKeyProperty->getValue($semaphore);

        $this->assertStringStartsWith('semaphore:', $redisKey);
        $this->assertStringEndsWith('my-key', $redisKey);
    }

    public function test_legacy_semaphore_key_prefix(): void
    {
        $semaphore = new LegacySemaphore('my-key', 1, 1);

        $reflection = new \ReflectionClass($semaphore);
        $redisKeyProperty = $reflection->getProperty('redisKey');
        $redisKey = $redisKeyProperty->getValue($semaphore);

        $this->assertStringStartsWith('semaphore-legacy:', $redisKey);
        $this->assertStringEndsWith('my-key', $redisKey);
    }

    public function test_legacy_semaphore_slot_keys(): void
    {
        $semaphore = new LegacySemaphore('test', 3, 1);

        $reflection = new \ReflectionClass($semaphore);
        $getSlotKeyMethod = $reflection->getMethod('getSlotKey');

        $slot0 = $getSlotKeyMethod->invoke($semaphore, 0);
        $slot1 = $getSlotKeyMethod->invoke($semaphore, 1);
        $slot2 = $getSlotKeyMethod->invoke($semaphore, 2);

        $this->assertStringEndsWith('0', $slot0);
        $this->assertStringEndsWith('1', $slot1);
        $this->assertStringEndsWith('2', $slot2);
    }
}
