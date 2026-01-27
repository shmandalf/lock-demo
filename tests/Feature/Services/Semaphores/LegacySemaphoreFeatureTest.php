<?php

namespace Tests\Feature\Services\Semaphores;

use App\Services\Semaphores\LegacySemaphore;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class LegacySemaphoreFeatureTest extends TestCase
{
    /**
     * Test LegacySemaphore slot-based acquisition
     */
    public function test_legacy_semaphore_slot_based_acquisition(): void
    {
        $uniqueKey = 'test-legacy-' . uniqid();
        $slotCount = 3;

        $semaphore1 = new LegacySemaphore($uniqueKey, $slotCount, 10);

        // First acquisition should succeed
        $acquired1 = $semaphore1->acquire(1);
        $this->assertTrue($acquired1, 'First acquisition should succeed');

        // Check it's acquired
        $this->assertTrue($semaphore1->isAcquiredByMe());

        // Create second instance for same key
        $semaphore2 = new LegacySemaphore($uniqueKey, $slotCount, 10);

        // Second acquisition should also succeed (different slot)
        $acquired2 = $semaphore2->acquire(1);
        $this->assertTrue($acquired2, 'Second acquisition should succeed');

        // Create third instance
        $semaphore3 = new LegacySemaphore($uniqueKey, $slotCount, 10);

        // Third acquisition should succeed
        $acquired3 = $semaphore3->acquire(1);
        $this->assertTrue($acquired3, 'Third acquisition should succeed');

        // Fourth acquisition should fail (all slots taken)
        $semaphore4 = new LegacySemaphore($uniqueKey, $slotCount, 10);
        $acquired4 = $semaphore4->acquire(1);
        $this->assertFalse($acquired4, 'Fourth acquisition should fail (no slots)');

        // Check current count
        $count = $semaphore4->getCurrentCount();
        $this->assertEquals($slotCount, $count, "Should have $slotCount acquisitions");

        // Release first semaphore
        $released1 = $semaphore1->release();
        $this->assertTrue($released1);

        // Now fourth acquisition should succeed
        $acquired4AfterRelease = $semaphore4->acquire(1);
        $this->assertTrue($acquired4AfterRelease, 'Should succeed after release');

        // Cleanup
        $semaphore2->release();
        $semaphore3->release();
        $semaphore4->release();
    }

    /**
     * Test LegacySemaphore statistics DTO
     */
    public function test_legacy_semaphore_stats_dto(): void
    {
        $uniqueKey = 'test-legacy-stats-' . uniqid();
        $semaphore = new LegacySemaphore($uniqueKey, 2, 30);

        // Get stats before acquisition
        $statsBefore = $semaphore->getStats();

        $this->assertEquals($uniqueKey, $statsBefore->key);
        $this->assertEquals(2, $statsBefore->maxConcurrent);
        $this->assertEquals(0, $statsBefore->currentCount);
        $this->assertEquals(2, $statsBefore->available);
        $this->assertFalse($statsBefore->isFull);
        $this->assertFalse($statsBefore->isAcquiredByMe);
        $this->assertEquals('legacy', $statsBefore->driver);

        // Acquire semaphore
        $acquired = $semaphore->acquire(1);
        $this->assertTrue($acquired);

        // Get stats after acquisition
        $statsAfter = $semaphore->getStats();

        $this->assertEquals(1, $statsAfter->currentCount);
        $this->assertEquals(1, $statsAfter->available);
        $this->assertTrue($statsAfter->isAcquiredByMe);

        // Check metadata contains slot information
        $this->assertArrayHasKey('my_slot_index', $statsAfter->metadata);
        $this->assertArrayHasKey('occupied_slots', $statsAfter->metadata);
        $this->assertIsArray($statsAfter->metadata['occupied_slots']);

        // Test DTO methods
        $this->assertTrue($statsAfter->hasAvailableSlots());
        $this->assertEquals(50.0, $statsAfter->getUsagePercentage());

        // Cleanup
        $semaphore->release();
    }

    /**
     * Test LegacySemaphore timeout expiration
     */
    public function test_legacy_semaphore_expires_after_timeout(): void
    {
        $uniqueKey = 'test-legacy-expiry-' . uniqid();
        $shortTimeout = 2; // 2 seconds

        $semaphore = new LegacySemaphore($uniqueKey, 1, $shortTimeout);

        // Acquire semaphore
        $acquired = $semaphore->acquire(1);
        $this->assertTrue($acquired, 'Should acquire semaphore');

        // Проверяем, что семафор действительно захвачен
        $this->assertTrue($semaphore->isAcquiredByMe(), 'Should be acquired by me');

        // Получаем статистику чтобы узнать текущий TTL
        $stats = $semaphore->getStats();
        $this->assertGreaterThan(0, $stats->ttl, 'Should have positive TTL');

        // Дополнительно проверяем через Redis напрямую
        $slotKey = null;
        try {
            $reflection = new \ReflectionClass($semaphore);
            $method = $reflection->getMethod('getSlotKey');
            $method->setAccessible(true);
            $slotKey = $method->invoke($semaphore, 0);

            $ttl = Redis::ttl($slotKey);
            $this->assertGreaterThan(0, $ttl, 'Redis TTL should be positive');
            $this->assertLessThanOrEqual($shortTimeout, $ttl, 'Redis TTL should be <= timeout');
        } catch (\ReflectionException $e) {
            // Если не получается через рефлексию, просто проверяем через семафор
            $this->assertNotNull($slotKey, 'Should have slot key');
        }

        // Wait for timeout to expire
        sleep($shortTimeout + 1);

        // Семафор больше не должен быть захвачен
        $this->assertFalse($semaphore->isAcquiredByMe(), 'Should expire after timeout');

        // Проверяем через Redis
        if ($slotKey) {
            $ttlAfter = Redis::ttl($slotKey);
            $this->assertEquals(-2, $ttlAfter, 'Key should not exist after expiry (ttl = -2)');
        }

        // Can acquire again
        $acquiredAgain = $semaphore->acquire(1);
        $this->assertTrue($acquiredAgain, 'Should be able to acquire again after expiry');

        // Cleanup
        $semaphore->release();
    }
}
