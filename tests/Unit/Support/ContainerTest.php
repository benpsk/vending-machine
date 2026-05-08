<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use RuntimeException;
use Tests\Support\TestCase;
use Tests\Unit\Support\Fixtures\CircularA;
use Tests\Unit\Support\Fixtures\ClassWithDefaultPrimitive;
use Tests\Unit\Support\Fixtures\ClassWithDep;
use Tests\Unit\Support\Fixtures\ClassWithNoDeps;
use Tests\Unit\Support\Fixtures\ClassWithRequiredPrimitive;
use Tests\Unit\Support\Fixtures\ContractImpl;
use Tests\Unit\Support\Fixtures\InterfaceContract;

final class ContainerTest extends TestCase
{
    public function testResolvesClassWithNoDependencies(): void
    {
        $resolved = $this->container->get(ClassWithNoDeps::class);

        $this->assertInstanceOf(ClassWithNoDeps::class, $resolved);
    }

    public function testResolvesClassWithResolvableDependencies(): void
    {
        $resolved = $this->container->get(ClassWithDep::class);

        $this->assertInstanceOf(ClassWithDep::class, $resolved);
        $this->assertInstanceOf(ClassWithNoDeps::class, $resolved->dep);
    }

    public function testReturnsSingletonInstance(): void
    {
        $instance = new ClassWithNoDeps();
        $this->container->singleton(ClassWithNoDeps::class, $instance);

        $this->assertSame($instance, $this->container->get(ClassWithNoDeps::class));
    }

    public function testBindMapsAbstractToConcreteClassName(): void
    {
        $this->container->bind(InterfaceContract::class, ContractImpl::class);

        $resolved = $this->container->get(InterfaceContract::class);

        $this->assertInstanceOf(ContractImpl::class, $resolved);
    }

    public function testBindRunsClosureWithContainer(): void
    {
        $this->container->bind(
            ClassWithNoDeps::class,
            static fn (): ClassWithNoDeps => new ClassWithNoDeps(),
        );

        $a = $this->container->get(ClassWithNoDeps::class);
        $b = $this->container->get(ClassWithNoDeps::class);

        $this->assertInstanceOf(ClassWithNoDeps::class, $a);
        $this->assertNotSame($a, $b, 'closure binding should produce a fresh instance per get()');
    }

    public function testUsesDefaultValueForPrimitiveParameter(): void
    {
        $resolved = $this->container->get(ClassWithDefaultPrimitive::class);

        $this->assertInstanceOf(ClassWithDefaultPrimitive::class, $resolved);
        $this->assertSame(7, $resolved->count);
    }

    public function testThrowsWhenPrimitiveParameterHasNoDefault(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot resolve parameter \$count/');

        $this->container->get(ClassWithRequiredPrimitive::class);
    }

    public function testThrowsOnCircularDependency(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Circular dependency/i');

        $this->container->get(CircularA::class);
    }

    public function testThrowsWhenAbstractIsNotInstantiable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot instantiate/');

        $this->container->get(InterfaceContract::class);
    }
}
