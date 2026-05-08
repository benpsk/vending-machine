<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use App\Auth\AuthApiController;
use App\Auth\JwtAuthenticator;
use App\Auth\PasswordHasher;
use App\Database\Mysql\LoginAttemptRepository;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Users\Role;
use App\Users\User;
use DateTimeImmutable;
use Tests\Support\TestCase;

final class AuthApiControllerTest extends TestCase
{
    public function testSuccessReturnsJwtEnvelopeAndRecordsAttempt(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('correct-password');
        $user = $this->makeUser(7, 'alice', $hash, Role::Admin);

        $users = $this->mock(UserRepository::class);
        $users->shouldReceive('findByUsername')->with('alice')->once()->andReturn($user);

        $jwt = $this->mock(JwtAuthenticator::class);
        $jwt->shouldReceive('issue')->with(7, Role::Admin)->once()->andReturn([
            'token' => 'eyJ.fake.token',
            'expiresAt' => 1736435000,
        ]);

        $attempts = $this->mock(LoginAttemptRepository::class);
        $attempts->shouldReceive('countFailedSince')->once()->andReturn(0);
        $attempts->shouldReceive('record')->with('127.0.0.1', true)->once();

        $controller = new AuthApiController($users, $hasher, $jwt, $attempts);
        $response = $controller->login(new Request(
            method: 'POST',
            path: '/api/auth/login',
            body: ['username' => 'alice', 'password' => 'correct-password'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, true);
        $this->assertIsArray($body);
        $this->assertSame('eyJ.fake.token', $body['data']['token']);
        $this->assertSame(1736435000, $body['data']['expires_at']);
        $this->assertSame('Bearer', $body['data']['token_type']);
    }

    public function testWrongPasswordReturnsGenericError(): void
    {
        $hasher = new PasswordHasher();
        $hash = $hasher->hash('right');
        $user = $this->makeUser(7, 'alice', $hash, Role::User);

        $users = $this->mock(UserRepository::class);
        $users->shouldReceive('findByUsername')->with('alice')->once()->andReturn($user);

        $jwt = $this->mock(JwtAuthenticator::class);
        $jwt->shouldNotReceive('issue');

        $attempts = $this->mock(LoginAttemptRepository::class);
        $attempts->shouldReceive('countFailedSince')->once()->andReturn(0);
        $attempts->shouldReceive('record')->with('127.0.0.1', false)->once();

        $controller = new AuthApiController($users, $hasher, $jwt, $attempts);
        $response = $controller->login(new Request(
            method: 'POST',
            path: '/api/auth/login',
            body: ['username' => 'alice', 'password' => 'wrong'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(401, $response->status);
        $this->assertStringContainsString('invalid_credentials', $response->body);
    }

    public function testMissingUserReturnsSameGenericError(): void
    {
        $users = $this->mock(UserRepository::class);
        $users->shouldReceive('findByUsername')->with('ghost')->once()->andReturn(null);

        $jwt = $this->mock(JwtAuthenticator::class);
        $jwt->shouldNotReceive('issue');

        $attempts = $this->mock(LoginAttemptRepository::class);
        $attempts->shouldReceive('countFailedSince')->once()->andReturn(0);
        $attempts->shouldReceive('record')->with('127.0.0.1', false)->once();

        $controller = new AuthApiController($users, new PasswordHasher(), $jwt, $attempts);
        $response = $controller->login(new Request(
            method: 'POST',
            path: '/api/auth/login',
            body: ['username' => 'ghost', 'password' => 'whatever'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(401, $response->status);
        $this->assertStringContainsString('invalid_credentials', $response->body);
    }

    public function testRateLimitReturns429(): void
    {
        $users = $this->mock(UserRepository::class);
        $users->shouldNotReceive('findByUsername');

        $jwt = $this->mock(JwtAuthenticator::class);
        $jwt->shouldNotReceive('issue');

        $attempts = $this->mock(LoginAttemptRepository::class);
        $attempts->shouldReceive('countFailedSince')->once()->andReturn(5);
        $attempts->shouldNotReceive('record');

        $controller = new AuthApiController($users, new PasswordHasher(), $jwt, $attempts);
        $response = $controller->login(new Request(
            method: 'POST',
            path: '/api/auth/login',
            body: ['username' => 'alice', 'password' => 'whatever'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(429, $response->status);
        $this->assertSame('900', $response->headers['retry-after'] ?? null);
        $this->assertStringContainsString('rate_limited', $response->body);
    }

    private function makeUser(int $id, string $username, string $hash, Role $role): User
    {
        return new User(
            id: $id,
            username: $username,
            email: "{$username}@example.com",
            passwordHash: $hash,
            role: $role,
            createdAt: new DateTimeImmutable('2026-01-01'),
            updatedAt: new DateTimeImmutable('2026-01-01'),
        );
    }
}
