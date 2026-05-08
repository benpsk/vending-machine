<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use App\Routing\RouteCollector;
use RuntimeException;
use Tests\Support\TestCase;
use Tests\Unit\Routing\Fixtures\DuplicateStub;
use Tests\Unit\Routing\Fixtures\HomeStub;
use Tests\Unit\Routing\Fixtures\ProductsStub;
use Tests\Unit\Routing\Fixtures\RepeatableStub;

final class RouteCollectorTest extends TestCase
{
    public function testCollectsSingleAttributeRoute(): void
    {
        $routes = (new RouteCollector())->collect([HomeStub::class]);

        $this->assertCount(1, $routes);
        $this->assertSame('/', $routes[0]->path);
        $this->assertSame(['GET'], $routes[0]->methods);
        $this->assertSame('home', $routes[0]->name);
        $this->assertSame(HomeStub::class, $routes[0]->controller);
        $this->assertSame('index', $routes[0]->action);
        $this->assertSame([], $routes[0]->paramNames);
    }

    public function testIgnoresMethodsWithoutTheAttribute(): void
    {
        $routes = (new RouteCollector())->collect([HomeStub::class]);

        $actions = array_map(static fn ($r) => $r->action, $routes);
        $this->assertNotContains('untaggedHelper', $actions);
    }

    public function testCompilesPathParamIntoNamedRegexGroup(): void
    {
        $routes = (new RouteCollector())->collect([ProductsStub::class]);

        $show = $this->routeByAction($routes, 'show');
        $this->assertSame(['id'], $show->paramNames);
        $this->assertMatchesRegularExpression($show->regex, '/products/42');
        $this->assertDoesNotMatchRegularExpression($show->regex, '/products/');
        $this->assertDoesNotMatchRegularExpression($show->regex, '/products/42/extra');
    }

    public function testCollectsMultipleRepeatableAttributesOnOneMethod(): void
    {
        $routes = (new RouteCollector())->collect([RepeatableStub::class]);

        $this->assertCount(2, $routes);
        $methods = array_map(static fn ($r) => $r->methods[0], $routes);
        sort($methods);
        $this->assertSame(['POST', 'PUT'], $methods);
    }

    public function testThrowsOnDuplicateMethodAndPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Duplicate route registration: GET \/dup/');

        (new RouteCollector())->collect([DuplicateStub::class]);
    }

    public function testNormalisesMethodToUppercase(): void
    {
        $routes = (new RouteCollector())->collect([ProductsStub::class]);

        foreach ($routes as $route) {
            foreach ($route->methods as $method) {
                $this->assertSame(strtoupper($method), $method);
            }
        }
    }

    /**
     * @param list<\App\Routing\CompiledRoute> $routes
     */
    private function routeByAction(array $routes, string $action): \App\Routing\CompiledRoute
    {
        foreach ($routes as $route) {
            if ($route->action === $action) {
                return $route;
            }
        }
        $this->fail("No route found for action {$action}");
    }
}
