<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Auth\Storage\ArraySessionStorage;
use App\Http\Middleware\CsrfMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Support\Csrf;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

final class CsrfMiddlewareTest extends TestCase
{
    /**
     * @param array<string, mixed> $body
     */
    #[DataProvider('cases')]
    public function testCsrfMiddleware(
        string $method,
        string $path,
        array $body,
        bool $tokenInSession,
        int $expectedStatus,
        bool $expectedReachedNext,
    ): void {
        $session = new ArraySessionStorage();
        if ($tokenInSession) {
            Csrf::token($session);
        }

        $middleware = new CsrfMiddleware($session);
        $reached = false;
        $response = $middleware->handle(
            new Request(method: $method, path: $path, body: $body),
            static function (Request $r) use (&$reached): Response {
                $reached = true;
                return Response::html('ok');
            },
        );

        $this->assertSame($expectedStatus, $response->status);
        $this->assertSame($expectedReachedNext, $reached);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: array<string, mixed>, 3: bool, 4: int, 5: bool}>
     */
    public static function cases(): iterable
    {
        yield 'GET passes without token'
            => ['GET',  '/login', [], false, 200, true];
        yield 'HEAD passes without token'
            => ['HEAD', '/login', [], false, 200, true];
        yield 'POST with bad token returns 403'
            => ['POST', '/login', ['_token' => 'wrong'], true, 403, false];
        yield 'POST with no token returns 403'
            => ['POST', '/login', [], true, 403, false];
        yield 'POST under /api/* skips CSRF'
            => ['POST', '/api/products', [], false, 200, true];
        yield 'PUT under /api/* skips CSRF'
            => ['PUT',  '/api/products/1', [], false, 200, true];
    }

    public function testValidTokenIsCheckedAgainstSession(): void
    {
        // Specific assertion that the right token actually verifies (the "valid token" case
        // above uses a placeholder that the test substitutes for the real token).
        $session = new ArraySessionStorage();
        $token = Csrf::token($session);

        $middleware = new CsrfMiddleware($session);
        $response = $middleware->handle(
            new Request(method: 'POST', path: '/login', body: ['_token' => $token]),
            static fn (Request $r): Response => Response::html('passed'),
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('passed', $response->body);
    }
}
