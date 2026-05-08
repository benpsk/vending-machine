<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Middleware;

use App\Auth\Middleware\AuthSessionMiddleware;
use App\Auth\PasswordHasher;
use App\Auth\Storage\ArraySessionStorage;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Http\Response;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;

final class AuthSessionMiddlewareTest extends DatabaseTestCase
{
    public function testNoUserIdInSessionLeavesRequestUntouched(): void
    {
        $session = new ArraySessionStorage();
        $middleware = new AuthSessionMiddleware($session, new UserRepository($this->pdo));
        $request = new Request(method: 'GET', path: '/');

        $response = $middleware->handle(
            $request,
            static fn (Request $r): Response => Response::html('ok'),
        );

        $this->assertSame(200, $response->status);
        $this->assertNull($request->attribute('user'));
    }

    public function testValidUserIdInSessionAttachesUserToRequest(): void
    {
        $hasher = new PasswordHasher();
        $repo = new UserRepository($this->pdo);
        $userId = $repo->create('alice', 'alice@example.com', $hasher->hash('pw'), Role::User);

        $session = new ArraySessionStorage();
        $session->set('user_id', $userId);

        $middleware = new AuthSessionMiddleware($session, $repo);
        $request = new Request(method: 'GET', path: '/');

        $middleware->handle($request, static fn (Request $r): Response => Response::html('ok'));

        $user = $request->attribute('user');
        $this->assertNotNull($user);
        $this->assertSame('alice', $user->username);
    }

    public function testStaleUserIdInSessionGetsCleared(): void
    {
        $repo = new UserRepository($this->pdo);

        $session = new ArraySessionStorage();
        $session->set('user_id', 999_999); // pointing at a deleted user
        $session->set('role', 'admin');

        $middleware = new AuthSessionMiddleware($session, $repo);
        $request = new Request(method: 'GET', path: '/');

        $middleware->handle($request, static fn (Request $r): Response => Response::html('ok'));

        $this->assertNull($request->attribute('user'));
        $this->assertFalse($session->has('user_id'));
        $this->assertFalse($session->has('role'));
    }
}
