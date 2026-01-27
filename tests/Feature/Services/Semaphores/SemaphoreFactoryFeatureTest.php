<?php

namespace Tests\Feature\Services\Semaphores;

use App\Services\Semaphores\LegacySemaphore;
use App\Services\Semaphores\RedisSemaphore;
use App\Services\Semaphores\SemaphoreFactory;
use Tests\TestCase;

class SemaphoreFactoryFeatureTest extends TestCase
{
    /**
     * Test factory creates configured semaphore type
     */
    public function test_factory_creates_configured_semaphore_type(): void
    {
        // Test with RedisSemaphore
        $redisFactory = new SemaphoreFactory(RedisSemaphore::class);
        $redisSemaphore = $redisFactory->create('test-factory-redis', 2, 30);

        $this->assertInstanceOf(RedisSemaphore::class, $redisSemaphore);
        $this->assertEquals(2, $redisSemaphore->getMaxConcurrent());

        // Test with LegacySemaphore
        $legacyFactory = new SemaphoreFactory(LegacySemaphore::class);
        $legacySemaphore = $legacyFactory->create('test-factory-legacy', 2, 30);

        $this->assertInstanceOf(LegacySemaphore::class, $legacySemaphore);
        $this->assertEquals(2, $legacySemaphore->getMaxConcurrent());

        // Both should work independently
        $acquiredRedis = $redisSemaphore->acquire(1);
        $acquiredLegacy = $legacySemaphore->acquire(1);

        $this->assertTrue($acquiredRedis);
        $this->assertTrue($acquiredLegacy);

        // Cleanup
        $redisSemaphore->release();
        $legacySemaphore->release();
    }

    /**
     * Test factory parameter validation
     */
    public function test_factory_parameter_validation(): void
    {
        $factory = new SemaphoreFactory(RedisSemaphore::class);

        // Valid parameters should work
        $semaphore = $factory->create('valid-key', 1, 1);
        $this->assertInstanceOf(RedisSemaphore::class, $semaphore);
        $semaphore->release();

        // Invalid parameters should throw
        $this->expectException(\InvalidArgumentException::class);
        $factory->create('', 1, 1);
    }

    /**
     * Test factory with real Redis interaction
     */
    public function test_factory_creates_working_semaphore(): void
    {
        $uniqueKey = 'test-factory-real-' . uniqid();
        $factory = new SemaphoreFactory(RedisSemaphore::class);

        $semaphore = $factory->create($uniqueKey, 1, 30);

        // Should be able to acquire
        $acquired = $semaphore->acquire(1);
        $this->assertTrue($acquired);

        // Should show as acquired
        $this->assertTrue($semaphore->isAcquiredByMe());

        // Should get stats
        $stats = $semaphore->getStats();
        $this->assertEquals($uniqueKey, $stats->key);

        // Cleanup
        $semaphore->release();
    }
}
