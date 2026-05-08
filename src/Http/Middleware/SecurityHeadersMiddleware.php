<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    private const CSP =
        "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        . "object-src 'none'; base-uri 'self'; frame-ancestors 'none'";

    public function __construct(private readonly bool $isProduction)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        // Always-on hardening.
        $headers['x-frame-options'] = 'DENY';
        $headers['x-content-type-options'] = 'nosniff';
        $headers['referrer-policy'] = 'strict-origin-when-cross-origin';
        $headers['permissions-policy'] = 'camera=(), microphone=(), geolocation=()';
        $headers['content-security-policy'] = self::CSP;

        // HSTS only when running behind HTTPS in production. Browsers cache aggressively
        // (especially with `preload`), so dev (HTTP) deliberately omits it.
        if ($this->isProduction) {
            $headers['strict-transport-security'] = 'max-age=31536000; includeSubDomains; preload';
        }

        return new Response(
            status: $response->status,
            headers: $headers,
            body: $response->body,
        );
    }
}
