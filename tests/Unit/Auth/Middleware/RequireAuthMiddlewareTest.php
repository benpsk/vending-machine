<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Middleware;

use App\Auth\Middleware\RequireAuthMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Users\Role;
use App\Users\User;
use DateTimeImmutable;
use Tests\Support\TestCase;

final class RequireAuthMiddlewareTest extends TestCase
{
    public function testRedirectsToLoginWhenNoUserAttached(): void
    {
        $middleware = new RequireAuthMiddleware();
        $response = $middleware->handle(
            new Request(method: 'GET', path: '/products'),
            static function (): Response {
                throw new \RuntimeException('next should not be reached');
            },
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/login?next=%2Fproducts', $response->headers['location'] ?? null);
    }

    public function testCallsNextWhenUserAttached(): void
    {
        $middleware = new RequireAuthMiddleware();
        $request = new Request(method: 'GET', path: '/products');
        $request->setAttribute('user', $this->makeUser());

        $response = $middleware->handle(
            $request,
            static fn (Request $r): Response => Response::html('ok'),
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('ok', $response->body);
    }

    public function testRedirectsWhenAttributeIsNotAUserInstance(): void
    {
        $middleware = new RequireAuthMiddleware();
        $request = new Request(method: 'GET', path: '/products');
        $request->setAttribute('user', 'not-a-user-object');

        $response = $middleware->handle(
            $request,
            static function (): Response {
                throw new \RuntimeException('next should not be reached');
            },
        );

        $this->assertSame(302, $response->status);
    }

    private function makeUser(): User
    {
        return new User(
            id: 1,
            username: 'alice',
            email: 'alice@example.com',
            passwordHash: '$2y$10$x',
            role: Role::User,
            createdAt: new DateTimeImmutable('2026-01-01'),
            updatedAt: new DateTimeImmutable('2026-01-01'),
        );
    }
}
