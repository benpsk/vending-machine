<?php

declare(strict_types=1);

namespace App\Auth\Middleware;

use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;
use App\Users\User;

final class RequireAuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (!$request->attribute('user') instanceof User) {
            return Response::redirect('/login?next=' . rawurlencode($request->path));
        }
        return $next($request);
    }
}
