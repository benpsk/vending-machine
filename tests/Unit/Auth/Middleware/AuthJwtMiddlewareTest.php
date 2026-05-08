<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Middleware;

use App\Auth\Exceptions\JwtFailure;
use App\Auth\Exceptions\JwtVerificationException;
use App\Auth\JwtAuthenticator;
use App\Auth\JwtClaims;
use App\Auth\Middleware\AuthJwtMiddleware;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Http\Response;
use App\Users\Role;
use App\Users\User;
use DateTimeImmutable;
use Tests\Support\TestCase;

final class AuthJwtMiddlewareTest extends TestCase
{
    public function testMissingAuthorizationHeaderReturns401(): void
    {
        $jwt = $this->mock(JwtAuthenticator::class);
        $users = $this->mock(UserRepository::class);
        $middleware = new AuthJwtMiddleware($jwt, $users);

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api/products'),
            static function (): Response {
                throw new \RuntimeException('next should not be reached');
            },
        );

        $this->assertSame(401, $response->status);
        $this->assertSame('Bearer error="invalid_token"', $response->headers['www-authenticate'] ?? null);
        $this->assertStringContainsString('"invalid_token"', $response->body);
    }

    public function testNonBearerSchemeReturns401(): void
    {
        $jwt = $this->mock(JwtAuthenticator::class);
        $users = $this->mock(UserRepository::class);
        $middleware = new AuthJwtMiddleware($jwt, $users);

        $response = $middleware->handle(
            new Request(method: 'GET', path: '/api/products', headers: ['authorization' => 'Basic dXNlcjpwYXNz']),
            static function (): Response {
                throw new \RuntimeException('next should not be reached');
            },
        );

        $this->assertSame(401, $response->status);
    }

    public function testValidTokenLoadsUserAndCallsNext(): void
    {
        $jwt = $this->mock(JwtAuthenticator::class);
        $users = $this->mock(UserRepository::class);
        $user = $this->makeUser(7);

        $jwt->shouldReceive('verify')->with('valid-token')->once()->andReturn(
            new JwtClaims(sub: 7, role: Role::User, iat: 100, exp: 1000, jti: 'abc'),
        );
        $users->shouldReceive('findById')->with(7)->once()->andReturn($user);

        $middleware = new AuthJwtMiddleware($jwt, $users);
        $request = new Request(
            method: 'GET',
            path: '/api/products',
            headers: ['authorization' => 'Bearer valid-token'],
        );

        $response = $middleware->handle(
            $request,
            static fn (Request $r): Response => Response::html('ok'),
        );

        $this->assertSame(200, $response->status);
        $this->assertSame($user, $request->attribute('user'));
    }

    public function testValidTokenButDeletedUserReturns401(): void
    {
        $jwt = $this->mock(JwtAuthenticator::class);
        $users = $this->mock(UserRepository::class);

        $jwt->shouldReceive('verify')->with('valid-but-stale')->once()->andReturn(
            new JwtClaims(sub: 999, role: Role::User, iat: 100, exp: 1000, jti: 'abc'),
        );
        $users->shouldReceive('findById')->with(999)->once()->andReturn(null);

        $middleware = new AuthJwtMiddleware($jwt, $users);
        $response = $middleware->handle(
            new Request(
                method: 'GET',
                path: '/api/products',
                headers: ['authorization' => 'Bearer valid-but-stale'],
            ),
            static function (): Response {
                throw new \RuntimeException('next should not be reached');
            },
        );

        $this->assertSame(401, $response->status);
    }

    public function testVerifyFailureReturns401(): void
    {
        $jwt = $this->mock(JwtAuthenticator::class);
        $users = $this->mock(UserRepository::class);

        $jwt->shouldReceive('verify')->once()->andThrow(
            new JwtVerificationException(JwtFailure::Expired, 'expired'),
        );

        $middleware = new AuthJwtMiddleware($jwt, $users);
        $response = $middleware->handle(
            new Request(
                method: 'GET',
                path: '/api/products',
                headers: ['authorization' => 'Bearer expired-token'],
            ),
            static function (): Response {
                throw new \RuntimeException('next should not be reached');
            },
        );

        $this->assertSame(401, $response->status);
        $this->assertSame('Bearer error="invalid_token"', $response->headers['www-authenticate'] ?? null);
        $this->assertStringContainsString('expired', $response->body);
    }

    private function makeUser(int $id): User
    {
        return new User(
            id: $id,
            username: 'alice',
            email: 'alice@example.com',
            passwordHash: '$2y$10$x',
            role: Role::User,
            createdAt: new DateTimeImmutable('2026-01-01'),
            updatedAt: new DateTimeImmutable('2026-01-01'),
        );
    }
}
