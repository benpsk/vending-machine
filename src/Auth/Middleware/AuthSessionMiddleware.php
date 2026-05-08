<?php

declare(strict_types=1);

namespace App\Auth\Middleware;

use App\Auth\Storage\SessionStorageInterface;
use App\Database\Mysql\UserRepository;
use App\Http\Middleware\MiddlewareInterface;
use App\Http\Request;
use App\Http\Response;

final class AuthSessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionStorageInterface $session,
        private readonly UserRepository $users,
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        // API routes use JWT bearer auth (AuthJwtMiddleware); sessions are irrelevant there.
        if (str_starts_with($request->path, '/api/')) {
            return $next($request);
        }

        $userId = $this->session->get('user_id');
        if (is_int($userId)) {
            $user = $this->users->findById($userId);
            if ($user !== null) {
                $request->setAttribute('user', $user);
            } else {
                // Session refers to a deleted user — clear it.
                $this->session->forget('user_id');
                $this->session->forget('role');
            }
        }

        return $next($request);
    }
}
