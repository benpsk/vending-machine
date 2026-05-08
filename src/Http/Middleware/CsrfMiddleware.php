<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Storage\SessionStorageInterface;
use App\Http\Request;
use App\Http\Response;
use App\Support\Csrf;

final class CsrfMiddleware implements MiddlewareInterface
{
    private const STATEFUL_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const TOKEN_FIELD = '_token';

    public function __construct(private readonly SessionStorageInterface $session)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!in_array($request->method, self::STATEFUL_METHODS, true)) {
            return $next($request);
        }

        // API routes use JWT bearer auth, not cookies — no CSRF surface.
        if (str_starts_with($request->path, '/api/')) {
            return $next($request);
        }

        $candidate = $request->body[self::TOKEN_FIELD] ?? null;
        $candidate = is_string($candidate) ? $candidate : null;

        if (!Csrf::verify($this->session, $candidate)) {
            return Response::html('<!doctype html><h1>403 CSRF Token Mismatch</h1>', 403);
        }

        return $next($request);
    }
}
