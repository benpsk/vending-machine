<?php

declare(strict_types=1);

namespace Tests\Unit\Routing;

use App\Routing\MethodNotAllowedException;
use App\Routing\RouteCollector;
use App\Routing\RouteNotFoundException;
use App\Routing\Router;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;
use Tests\Unit\Routing\Fixtures\HomeStub;
use Tests\Unit\Routing\Fixtures\ProductsStub;
use Tests\Unit\Routing\Fixtures\RepeatableStub;

final class RouterTest extends TestCase
{
    /**
     * @param array<string, string> $expectedParams
     */
    #[DataProvider('matchCases')]
    public function testMatchesKnownRoute(
        string $method,
        string $path,
        string $expectedAction,
        array $expectedParams,
    ): void {
        $router = new Router((new RouteCollector())->collect([HomeStub::class, ProductsStub::class]));

        $result = $router->match($method, $path);

        $this->assertSame($expectedAction, $result->action);
        $this->assertSame($expectedParams, $result->params);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: array<string, string>}>
     */
    public static function matchCases(): iterable
    {
        yield 'GET / matches home'                  => ['GET',  '/',                       'index',    []];
        yield 'GET /products matches products list' => ['GET',  '/products',               'index',    []];
        yield 'GET /products/42 extracts id'        => ['GET',  '/products/42',            'show',     ['id' => '42']];
        yield 'POST /products/7/purchase extracts'  => ['POST', '/products/7/purchase',    'purchase', ['id' => '7']];
        yield 'HEAD falls back to GET /'            => ['HEAD', '/',                       'index',    []];
        yield 'lowercase method is uppercased'      => ['get',  '/products',               'index',    []];
    }

    public function testThrowsRouteNotFoundForUnknownPath(): void
    {
        $router = new Router((new RouteCollector())->collect([HomeStub::class]));

        $this->expectException(RouteNotFoundException::class);
        $router->match('GET', '/nope');
    }

    public function testThrowsMethodNotAllowedForKnownPathWrongMethod(): void
    {
        $router = new Router((new RouteCollector())->collect([HomeStub::class]));

        try {
            $router->match('POST', '/');
            $this->fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(['GET', 'HEAD'], $e->allowedMethods);
        }
    }

    public function testMethodNotAllowedListsAllRegisteredMethods(): void
    {
        $router = new Router((new RouteCollector())->collect([RepeatableStub::class]));

        try {
            $router->match('GET', '/admin/products/9');
            $this->fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $e) {
            $this->assertSame(['POST', 'PUT'], $e->allowedMethods);
        }
    }

    public function testHeadAllowedAlongsideGetEvenWhenPostExists(): void
    {
        $router = new Router((new RouteCollector())->collect([HomeStub::class, ProductsStub::class]));

        $result = $router->match('HEAD', '/products');

        $this->assertSame('index', $result->action);
    }
}
