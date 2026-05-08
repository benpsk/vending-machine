<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\MiddlewareInterface;
use App\Http\Middleware\Pipeline;
use App\Http\Request;
use App\Http\Response;
use Closure;
use Tests\Support\TestCase;

final class PipelineTest extends TestCase
{
    public function testRunsMiddlewareInOrderAndReachesFinalHandler(): void
    {
        $log = [];
        $append = static function (string $entry) use (&$log): void {
            $log[] = $entry;
        };

        $response = Pipeline::run(
            [
                $this->logging('a', $append),
                $this->logging('b', $append),
                $this->logging('c', $append),
            ],
            static function (Request $r) use ($append): Response {
                $append('final');
                return Response::html('ok');
            },
            new Request(method: 'GET', path: '/'),
        );

        $this->assertSame(200, $response->status);
        $this->assertSame(
            ['a:in', 'b:in', 'c:in', 'final', 'c:out', 'b:out', 'a:out'],
            $log,
        );
    }

    public function testEarlyReturnSkipsLaterMiddlewareAndFinalHandler(): void
    {
        $log = [];
        $append = static function (string $entry) use (&$log): void {
            $log[] = $entry;
        };

        $blocker = new class implements MiddlewareInterface {
            public function handle(Request $request, callable $next): Response
            {
                return Response::html('blocked', 403);
            }
        };

        $response = Pipeline::run(
            [
                $this->logging('outer', $append),
                $blocker,
                $this->logging('inner', $append),
            ],
            static function (): Response {
                throw new \RuntimeException('final handler should not be reached');
            },
            new Request(method: 'GET', path: '/'),
        );

        $this->assertSame(403, $response->status);
        $this->assertSame(['outer:in', 'outer:out'], $log);
    }

    public function testEmptyMiddlewareListRunsFinalHandlerDirectly(): void
    {
        $response = Pipeline::run(
            [],
            static fn (Request $r): Response => Response::html('plain'),
            new Request(method: 'GET', path: '/'),
        );

        $this->assertSame('plain', $response->body);
    }

    private function logging(string $name, Closure $append): MiddlewareInterface
    {
        return new class ($name, $append) implements MiddlewareInterface {
            public function __construct(
                private readonly string $name,
                private readonly Closure $append,
            ) {
            }

            public function handle(Request $request, callable $next): Response
            {
                ($this->append)($this->name . ':in');
                $response = $next($request);
                ($this->append)($this->name . ':out');
                return $response;
            }
        };
    }
}
