<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Middleware;

use App\Auth\Middleware\RequireRoleMiddleware;
use App\Http\Request;
use App\Http\Response;
use App\Users\Role;
use App\Users\User;
use DateTimeImmutable;
use Tests\Support\TestCase;

final class RequireRoleMiddlewareTest extends TestCase
{
    public function testRedirectsToLoginWithNextWhenNoUserPresent(): void
    {
        $middleware = new RequireRoleMiddleware(Role::Admin);
        $response = $middleware->handle(
            new Request(method: 'GET', path: '/admin/stub'),
            static function (): Response {
                throw new \RuntimeException('next should not be reached');
            },
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/login?next=%2Fadmin%2Fstub', $response->headers['location'] ?? null);
    }

    public function testRedirectsHomeWhenAuthenticatedUserLacksRole(): void
    {
        $middleware = new RequireRoleMiddleware(Role::Admin);
        $request = new Request(method: 'GET', path: '/admin/stub');
        $request->setAttribute('user', $this->makeUser(Role::User));

        $response = $middleware->handle(
            $request,
            static function (): Response {
                throw new \RuntimeException('next should not be reached');
            },
        );

        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['location'] ?? null);
    }

    public function testCallsNextWhenRoleMatches(): void
    {
        $middleware = new RequireRoleMiddleware(Role::Admin);
        $request = new Request(method: 'GET', path: '/admin/stub');
        $request->setAttribute('user', $this->makeUser(Role::Admin));

        $response = $middleware->handle(
            $request,
            static fn (Request $r): Response => Response::html('ok'),
        );

        $this->assertSame(200, $response->status);
        $this->assertSame('ok', $response->body);
    }

    private function makeUser(Role $role): User
    {
        return new User(
            id: 1,
            username: 'user',
            email: 'u@example.com',
            passwordHash: '$2y$10$x',
            role: $role,
            createdAt: new DateTimeImmutable('2026-01-01'),
            updatedAt: new DateTimeImmutable('2026-01-01'),
        );
    }
}
