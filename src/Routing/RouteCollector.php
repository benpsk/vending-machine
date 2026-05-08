<?php

declare(strict_types=1);

namespace App\Routing;

use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class RouteCollector
{
    /**
     * @param list<class-string> $controllers
     * @return list<CompiledRoute>
     */
    public function collect(array $controllers): array
    {
        /** @var array<string, true> $seen keyed by "METHOD path" */
        $seen = [];
        $compiled = [];

        foreach ($controllers as $controllerClass) {
            $reflector = new ReflectionClass($controllerClass);
            foreach ($reflector->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->isStatic() || $method->isConstructor()) {
                    continue;
                }
                foreach ($method->getAttributes(Route::class) as $attr) {
                    /** @var Route $route */
                    $route = $attr->newInstance();
                    $compiledRoute = $this->compileOne($route, $controllerClass, $method->getName());

                    foreach ($compiledRoute->methods as $httpMethod) {
                        $key = $httpMethod . ' ' . $route->path;
                        if (isset($seen[$key])) {
                            throw new RuntimeException("Duplicate route registration: {$key}");
                        }
                        $seen[$key] = true;
                    }

                    $compiled[] = $compiledRoute;
                }
            }
        }

        return $compiled;
    }

    /**
     * @param class-string $controllerClass
     */
    private function compileOne(Route $route, string $controllerClass, string $action): CompiledRoute
    {
        $paramNames = [];
        $regex = preg_replace_callback(
            '~\{([a-zA-Z_][a-zA-Z0-9_]*)\}~',
            static function (array $m) use (&$paramNames): string {
                $paramNames[] = $m[1];
                return '(?P<' . $m[1] . '>[^/]+)';
            },
            $route->path,
        ) ?? $route->path;

        $methods = [];
        foreach ($route->methods as $method) {
            $methods[] = strtoupper($method);
        }

        return new CompiledRoute(
            path: $route->path,
            regex: '~^' . $regex . '$~',
            methods: $methods,
            paramNames: $paramNames,
            controller: $controllerClass,
            action: $action,
            name: $route->name,
        );
    }
}
