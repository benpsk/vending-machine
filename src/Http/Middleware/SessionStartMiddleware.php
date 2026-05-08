<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Storage\SessionStorageInterface;
use App\Http\Request;
use App\Http\Response;

final class SessionStartMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly SessionStorageInterface $session)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        // API routes use JWT bearer auth; sessions are irrelevant there.
        if (str_starts_with($request->path, '/api/')) {
            return $next($request);
        }
        $this->session->start();
        return $next($request);
    }
}
