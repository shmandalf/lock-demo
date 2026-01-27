<?php

namespace Tests\Unit\Services;

use App\Services\Semaphores\LegacySemaphore;
use App\Services\Semaphores\RedisSemaphore;
use App\Services\Semaphores\SemaphoreFactory;
use PHPUnit\Framework\TestCase;

class SemaphoreFactoryTest extends TestCase
{
    public function test_factory_creates_redis_semaphore(): void
    {
        $factory = new SemaphoreFactory(RedisSemaphore::class);
        $semaphore = $factory->create('test-key', 3, 30);

        $this->assertInstanceOf(RedisSemaphore::class, $semaphore);
        $this->assertEquals('semaphore:test-key', $this->getPrivateProperty($semaphore, 'redisKey'));
        $this->assertEquals(3, $semaphore->getMaxConcurrent());
        $this->assertEquals(30, $semaphore->getTtl());
    }

    public function test_factory_creates_legacy_semaphore(): void
    {
        $factory = new SemaphoreFactory(LegacySemaphore::class);
        $semaphore = $factory->create('test-key', 3, 30);

        $this->assertInstanceOf(LegacySemaphore::class, $semaphore);
        $this->assertEquals('semaphore-legacy:test-key', $this->getPrivateProperty($semaphore, 'redisKey'));
        $this->assertEquals(3, $semaphore->getMaxConcurrent());
        $this->assertEquals(30, $semaphore->getTtl());
    }

    public function test_factory_validation_empty_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Semaphore key cannot be empty');

        $factory = new SemaphoreFactory(RedisSemaphore::class);
        $factory->create('', 3, 30);
    }

    public function test_factory_validation_zero_max_concurrent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max concurrent must be at least 1');

        $factory = new SemaphoreFactory(RedisSemaphore::class);
        $factory->create('test', 0, 30);
    }

    public function test_factory_validation_negative_max_concurrent(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Max concurrent must be at least 1');

        $factory = new SemaphoreFactory(RedisSemaphore::class);
        $factory->create('test', -1, 30);
    }

    public function test_factory_validation_zero_ttl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Semaphore TTL must be at least 1 second');

        $factory = new SemaphoreFactory(RedisSemaphore::class);
        $factory->create('test', 3, 0);
    }

    public function test_factory_validation_negative_ttl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Semaphore TTL must be at least 1 second');

        $factory = new SemaphoreFactory(RedisSemaphore::class);
        $factory->create('test', 3, -1);
    }

    private function getPrivateProperty(object $object, string $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);

        return $property->getValue($object);
    }
}
