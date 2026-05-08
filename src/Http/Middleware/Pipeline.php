<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Http\Response;

final class Pipeline
{
    /**
     * @param list<MiddlewareInterface> $middleware
     * @param callable(Request): Response $finalHandler
     */
    public static function run(array $middleware, callable $finalHandler, Request $request): Response
    {
        $next = $finalHandler;
        foreach (array_reverse($middleware) as $layer) {
            $current = $next;
            $next = static fn (Request $r): Response => $layer->handle($r, $current);
        }
        return $next($request);
    }
}
