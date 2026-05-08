<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Support\Container;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    protected function tearDown(): void
    {
        // Always close Mockery so expectations are verified and the registry stays clean,
        // regardless of whether a particular test used Mockery.
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Typed Mockery factory so PHPStan sees the mock as both the target class and MockInterface.
     *
     * @template T of object
     * @param class-string<T> $class
     * @return T&MockInterface
     */
    protected function mock(string $class): object
    {
        /** @var T&MockInterface $instance */
        $instance = Mockery::mock($class);
        return $instance;
    }
}
