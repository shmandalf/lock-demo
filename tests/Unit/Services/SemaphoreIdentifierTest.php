<?php

namespace Tests\Unit\Services;

use App\Services\Semaphores\RedisSemaphore;
use PHPUnit\Framework\TestCase;

class SemaphoreIdentifierTest extends TestCase
{
    public function test_semaphore_identifier_generation(): void
    {
        $semaphore = new RedisSemaphore('test-identifier', 1, 1);
        $identifier = $semaphore->getIdentifier();

        $this->assertIsString($identifier);
        $this->assertMatchesRegularExpression(
            '/^[a-zA-Z0-9\-]+:\d+:[a-zA-Z0-9]+:\d+\.\d+$/',
            $identifier
        );
    }

    public function test_different_semaphores_have_different_identifiers(): void
    {
        $semaphore1 = new RedisSemaphore('test-1', 1, 1);
        $semaphore2 = new RedisSemaphore('test-2', 1, 1);

        $this->assertNotEquals(
            $semaphore1->getIdentifier(),
            $semaphore2->getIdentifier()
        );
    }

    public function test_semaphore_properties_accessors(): void
    {
        $semaphore = new RedisSemaphore('test-props', 5, 60);

        $this->assertEquals(5, $semaphore->getMaxConcurrent());
        $this->assertEquals(60, $semaphore->getTtl());

        // Identifier has format: hostname:pid:random:timestamp
        // It does NOT contain the semaphore key
        $identifier = $semaphore->getIdentifier();

        // Verify identifier format
        $this->assertMatchesRegularExpression(
            '/^[a-zA-Z0-9\-]+:\d+:[a-zA-Z0-9]+:\d+\.\d+$/',
            $identifier
        );
    }
}
