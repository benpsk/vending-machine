<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\SecurityHeadersMiddleware;
use App\Http\Request;
use App\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    /**
     * @return iterable<string, array{bool, bool}>
     */
    public static function environments(): iterable
    {
        yield 'production includes HSTS' => [true, true];
        yield 'local omits HSTS' => [false, false];
    }

    #[DataProvider('environments')]
    public function testAlwaysOnHeadersAreApplied(bool $isProduction, bool $expectHsts): void
    {
        $middleware = new SecurityHeadersMiddleware($isProduction);
        $request = new Request(method: 'GET', path: '/');
        $next = static fn (Request $r): Response => Response::html('<h1>ok</h1>');

        $response = $middleware->handle($request, $next);

        $this->assertSame('DENY', $response->headers['x-frame-options']);
        $this->assertSame('nosniff', $response->headers['x-content-type-options']);
        $this->assertSame(
            'strict-origin-when-cross-origin',
            $response->headers['referrer-policy'],
        );
        $this->assertSame(
            'camera=(), microphone=(), geolocation=()',
            $response->headers['permissions-policy'],
        );
        $this->assertArrayHasKey('content-security-policy', $response->headers);
        $csp = $response->headers['content-security-policy'];
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringNotContainsString("unsafe-inline", $csp);
        $this->assertStringNotContainsString("unsafe-eval", $csp);

        if ($expectHsts) {
            $this->assertSame(
                'max-age=31536000; includeSubDomains; preload',
                $response->headers['strict-transport-security'],
            );
        } else {
            $this->assertArrayNotHasKey('strict-transport-security', $response->headers);
        }
    }

    public function testExistingResponseHeadersArePreserved(): void
    {
        $middleware = new SecurityHeadersMiddleware(isProduction: false);
        $request = new Request(method: 'GET', path: '/');
        $next = static fn (Request $r): Response => new Response(
            status: 201,
            headers: ['content-type' => 'application/json', 'x-custom' => 'keep-me'],
            body: '{"ok":true}',
        );

        $response = $middleware->handle($request, $next);

        $this->assertSame(201, $response->status);
        $this->assertSame('application/json', $response->headers['content-type']);
        $this->assertSame('keep-me', $response->headers['x-custom']);
        $this->assertSame('{"ok":true}', $response->body);
        $this->assertSame('DENY', $response->headers['x-frame-options']);
    }

    public function testStatusAndBodyAreUnchanged(): void
    {
        $middleware = new SecurityHeadersMiddleware(isProduction: true);
        $request = new Request(method: 'GET', path: '/');
        $next = static fn (Request $r): Response => new Response(
            status: 418,
            headers: [],
            body: 'teapot',
        );

        $response = $middleware->handle($request, $next);

        $this->assertSame(418, $response->status);
        $this->assertSame('teapot', $response->body);
    }
}
