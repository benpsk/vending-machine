<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

final class Container
{
    /** @var array<class-string, Closure|class-string> */
    private array $bindings = [];

    /** @var array<class-string, object> */
    private array $instances = [];

    /** @var array<class-string, true> */
    private array $resolving = [];

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @param Closure(self):T|class-string<T> $concrete
     */
    public function bind(string $abstract, Closure|string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @param T $instance
     */
    public function singleton(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function get(string $abstract): object
    {
        if (isset($this->instances[$abstract])) {
            /** @var T */
            return $this->instances[$abstract];
        }

        if (isset($this->resolving[$abstract])) {
            throw new RuntimeException("Circular dependency detected for {$abstract}");
        }

        $this->resolving[$abstract] = true;

        try {
            $resolved = $this->resolve($abstract);
        } finally {
            unset($this->resolving[$abstract]);
        }

        /** @var T */
        return $resolved;
    }

    /**
     * @param class-string $abstract
     */
    private function resolve(string $abstract): object
    {
        $concrete = $this->bindings[$abstract] ?? $abstract;

        if ($concrete instanceof Closure) {
            return $concrete($this);
        }

        $reflector = new ReflectionClass($concrete);

        if (!$reflector->isInstantiable()) {
            throw new RuntimeException("Cannot instantiate {$concrete}");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            return $reflector->newInstance();
        }

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            $args[] = $this->resolveParameter($parameter, $abstract);
        }

        return $reflector->newInstanceArgs($args);
    }

    private function resolveParameter(ReflectionParameter $parameter, string $owner): mixed
    {
        $type = $parameter->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            /** @var class-string $className */
            $className = $type->getName();
            return $this->get($className);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new RuntimeException(
            "Cannot resolve parameter \${$parameter->getName()} of {$owner}"
        );
    }
}
