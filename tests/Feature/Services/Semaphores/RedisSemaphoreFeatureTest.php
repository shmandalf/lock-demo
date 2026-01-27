<?php

namespace Tests\Feature\Services\Semaphores;

use App\Services\Semaphores\RedisSemaphore;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisSemaphoreFeatureTest extends TestCase
{
    /**
     * Test actual Redis integration for semaphore acquisition
     */
    public function test_redis_semaphore_actually_works_with_redis(): void
    {
        // Use unique key for each test to avoid collisions
        $uniqueKey = 'test-feature-' . uniqid();
        $semaphore = new RedisSemaphore($uniqueKey, 2, 10);

        // First acquisition should succeed
        $acquired1 = $semaphore->acquire(1);
        $this->assertTrue($acquired1, 'First acquisition should succeed');

        // Check current count
        $count1 = $semaphore->getCurrentCount();
        $this->assertEquals(1, $count1, 'Should have 1 acquisition');

        // Create second semaphore instance for same key
        $semaphore2 = new RedisSemaphore($uniqueKey, 2, 10);

        // Second acquisition should also succeed (max 2 concurrent)
        $acquired2 = $semaphore2->acquire(1);
        $this->assertTrue($acquired2, 'Second acquisition should succeed');

        // Check current count
        $count2 = $semaphore2->getCurrentCount();
        $this->assertEquals(2, $count2, 'Should have 2 acquisitions');

        // Create third semaphore instance
        $semaphore3 = new RedisSemaphore($uniqueKey, 2, 10);

        // Third acquisition should fail (max 2 reached)
        $acquired3 = $semaphore3->acquire(1);
        $this->assertFalse($acquired3, 'Third acquisition should fail (max concurrent reached)');

        // Release first semaphore
        $released1 = $semaphore->release();
        $this->assertTrue($released1, 'First semaphore should release successfully');

        // Now third acquisition should succeed
        $acquired3AfterRelease = $semaphore3->acquire(1);
        $this->assertTrue($acquired3AfterRelease, 'Third acquisition should succeed after release');

        // Cleanup
        $semaphore2->release();
        $semaphore3->release();
    }

    /**
     * Test semaphore timeout expiration
     */
    public function test_semaphore_expires_after_timeout(): void
    {
        $uniqueKey = 'test-expiry-' . uniqid();
        $shortTimeout = 2; // 2 seconds

        $semaphore = new RedisSemaphore($uniqueKey, 1, $shortTimeout);

        // Acquire semaphore
        $acquired = $semaphore->acquire(1);
        $this->assertTrue($acquired);

        // Wait for timeout to expire
        sleep($shortTimeout + 1);

        // Semaphore should no longer be acquired by me
        $isAcquired = $semaphore->isAcquiredByMe();
        $this->assertFalse($isAcquired, 'Semaphore should expire after timeout');

        // Current count should be 0 after expiration
        $count = $semaphore->getCurrentCount();
        $this->assertEquals(0, $count, 'Should have 0 acquisitions after timeout');

        // Can acquire again after expiration
        $acquiredAgain = $semaphore->acquire(1);
        $this->assertTrue($acquiredAgain, 'Should be able to acquire after expiration');

        // Cleanup
        $semaphore->release();
    }

    /**
     * Test semaphore statistics DTO
     */
    public function test_get_stats_returns_valid_dto(): void
    {
        $uniqueKey = 'test-stats-' . uniqid();
        $semaphore = new RedisSemaphore($uniqueKey, 3, 30);

        // Get stats before acquisition
        $statsBefore = $semaphore->getStats();

        $this->assertEquals($uniqueKey, $statsBefore->key);
        $this->assertEquals(3, $statsBefore->maxConcurrent);
        $this->assertEquals(0, $statsBefore->currentCount);
        $this->assertEquals(3, $statsBefore->available);
        $this->assertFalse($statsBefore->isFull);
        $this->assertFalse($statsBefore->isAcquiredByMe);
        $this->assertEquals('redis', $statsBefore->driver);

        // Acquire semaphore
        $acquired = $semaphore->acquire(1);
        $this->assertTrue($acquired);

        // Get stats after acquisition
        $statsAfter = $semaphore->getStats();

        $this->assertEquals(1, $statsAfter->currentCount);
        $this->assertEquals(2, $statsAfter->available);
        $this->assertTrue($statsAfter->isAcquiredByMe);
        $this->assertGreaterThan(0, $statsAfter->ttl);

        // Test DTO methods
        $this->assertTrue($statsAfter->hasAvailableSlots());
        $this->assertEquals(33.33, round($statsAfter->getUsagePercentage(), 2));

        // Cleanup
        $semaphore->release();
    }

    /**
     * Test multiple independent semaphores
     */
    public function test_multiple_independent_semaphores(): void
    {
        $key1 = 'test-multi-1-' . uniqid();
        $key2 = 'test-multi-2-' . uniqid();

        $semaphore1 = new RedisSemaphore($key1, 1, 30);
        $semaphore2 = new RedisSemaphore($key2, 1, 30);

        // Both should be able to acquire independently
        $acquired1 = $semaphore1->acquire(1);
        $acquired2 = $semaphore2->acquire(1);

        $this->assertTrue($acquired1);
        $this->assertTrue($acquired2);

        // Both should show as acquired
        $this->assertTrue($semaphore1->isAcquiredByMe());
        $this->assertTrue($semaphore2->isAcquiredByMe());

        // Cleanup
        $semaphore1->release();
        $semaphore2->release();
    }

    /**
     * Test clear method actually clears Redis key
     */
    public function test_clear_removes_redis_key(): void
    {
        $uniqueKey = 'test-clear-' . uniqid();
        $semaphore = new RedisSemaphore($uniqueKey, 1, 30);

        // Acquire to create Redis key
        $acquired = $semaphore->acquire(1);
        $this->assertTrue($acquired);

        // Key should exist
        $redisKey = 'semaphore:' . $uniqueKey;
        $existsBefore = Redis::exists($redisKey);
        $this->assertEquals(1, $existsBefore);

        // Clear semaphore
        $cleared = $semaphore->clear();
        $this->assertTrue($cleared);

        // Key should no longer exist
        $existsAfter = Redis::exists($redisKey);
        $this->assertEquals(0, $existsAfter);

        // Can acquire again after clear
        $acquiredAgain = $semaphore->acquire(1);
        $this->assertTrue($acquiredAgain);

        // Cleanup
        $semaphore->release();
    }

    /**
     * Test zero and negative timeout (non-blocking)
     */
    public function test_zero_and_negative_timeout_non_blocking(): void
    {
        $uniqueKey = 'test-nonblocking-' . uniqid();

        // First create a semaphore that's already at capacity
        $semaphore1 = new RedisSemaphore($uniqueKey, 1, 30);
        $acquired1 = $semaphore1->acquire(1);
        $this->assertTrue($acquired1);

        // Try to acquire with zero timeout (should fail immediately)
        $semaphore2 = new RedisSemaphore($uniqueKey, 1, 30);
        $startTime = microtime(true);
        $acquired2 = $semaphore2->acquire(0);
        $endTime = microtime(true);

        $this->assertFalse($acquired2);
        $this->assertLessThan(0.1, $endTime - $startTime, 'Zero timeout should return immediately');

        // Try with negative timeout (should also fail immediately)
        $semaphore3 = new RedisSemaphore($uniqueKey, 1, 30);
        $startTime = microtime(true);
        $acquired3 = $semaphore3->acquire(-1);
        $endTime = microtime(true);

        $this->assertFalse($acquired3);
        $this->assertLessThan(0.1, $endTime - $startTime, 'Negative timeout should return immediately');

        // Cleanup
        $semaphore1->release();
    }

    /**
     * Test identifier uniqueness
     */
    public function test_identifiers_are_unique(): void
    {
        $key1 = 'test-id-1-' . uniqid();
        $key2 = 'test-id-2-' . uniqid();

        $semaphore1 = new RedisSemaphore($key1, 1, 30);
        $semaphore2 = new RedisSemaphore($key2, 1, 30);
        $semaphore3 = new RedisSemaphore($key1, 1, 30); // Same key, new instance

        $id1 = $semaphore1->getIdentifier();
        $id2 = $semaphore2->getIdentifier();
        $id3 = $semaphore3->getIdentifier();

        // All identifiers should be unique
        $this->assertNotEquals($id1, $id2);
        $this->assertNotEquals($id1, $id3);
        $this->assertNotEquals($id2, $id3);

        // Identifiers should contain expected components
        $this->assertStringContainsString(gethostname(), $id1);
        $this->assertStringContainsString((string) getmypid(), $id1);

        // Cleanup
        $semaphore1->release();
        $semaphore2->release();
    }

    /**
     * Test that semaphore respects max concurrent limit
     */
    public function test_semaphore_respects_max_concurrent_limit(): void
    {
        $uniqueKey = 'test-limit-' . uniqid();
        $maxConcurrent = 3;

        $semaphores = [];

        // Acquire up to max concurrent
        for ($i = 0; $i < $maxConcurrent; $i++) {
            $semaphore = new RedisSemaphore($uniqueKey, $maxConcurrent, 30);
            $acquired = $semaphore->acquire(1);
            $this->assertTrue($acquired, "Acquisition $i should succeed");
            $semaphores[] = $semaphore;
        }

        // Next acquisition should fail
        $extraSemaphore = new RedisSemaphore($uniqueKey, $maxConcurrent, 30);
        $extraAcquired = $extraSemaphore->acquire(1);
        $this->assertFalse($extraAcquired, 'Extra acquisition should fail (max reached)');

        // Current count should be max concurrent
        $count = $extraSemaphore->getCurrentCount();
        $this->assertEquals($maxConcurrent, $count);

        // Release one semaphore
        $released = $semaphores[0]->release();
        $this->assertTrue($released);

        // Now extra acquisition should succeed
        $extraAcquiredAfterRelease = $extraSemaphore->acquire(1);
        $this->assertTrue($extraAcquiredAfterRelease, 'Should succeed after release');

        // Cleanup
        foreach ($semaphores as $semaphore) {
            $semaphore->release();
        }
        $extraSemaphore->release();
    }
}
