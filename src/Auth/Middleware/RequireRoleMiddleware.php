<?php

declare(strict_types=1);

namespace App\Auth\Middleware;

use App\Http\JsonEnvelope;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;
use App\Users\Role;
use App\Users\User;

final class RequireRoleMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Role $required)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $isApi = str_starts_with($request->path, '/api/');
        $user = $request->attribute('user');

        if (!$user instanceof User) {
            // Defensive: AuthJwtMiddleware should already have rejected unauth API requests
            // with 401 before we reach this middleware. Web routes redirect to login.
            return $isApi
                ? JsonEnvelope::error('invalid_token', 'Authentication required.', 401)
                : Response::redirect('/login?next=' . rawurlencode($request->path));
        }

        if ($user->role !== $this->required) {
            // Web: bounce home (avoids the post-login loop where the same account
            // re-authenticates and bounces again). API: 403 JSON envelope.
            return $isApi
                ? JsonEnvelope::error('forbidden', 'Insufficient role.', 403)
                : Response::redirect('/');
        }

        return $next($request);
    }
}
