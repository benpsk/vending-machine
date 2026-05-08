<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Auth\PasswordHasher;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class AuthApiTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    public function testValidCredentialsReturnsJwt(): void
    {
        $this->seedUser('alice', 'pw', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/api/auth/login',
            body: ['username' => 'alice', 'password' => 'pw'],
            server: ['REMOTE_ADDR' => '127.0.0.1', 'CONTENT_TYPE' => 'application/json'],
        ));

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, true);
        $this->assertNotEmpty($body['data']['token']);
        $this->assertGreaterThan(time(), $body['data']['expires_at']);
        $this->assertSame('Bearer', $body['data']['token_type']);
    }

    public function testWrongPasswordReturnsGenericError(): void
    {
        $this->seedUser('bob', 'real', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/api/auth/login',
            body: ['username' => 'bob', 'password' => 'wrong'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(401, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('invalid_credentials', $body['error']['code']);
    }

    public function testMissingUserReturnsIdenticalError(): void
    {
        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/api/auth/login',
            body: ['username' => 'ghost', 'password' => 'whatever'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(401, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('invalid_credentials', $body['error']['code']);
    }

    public function testRateLimitTriggersAfterFiveFailures(): void
    {
        $this->seedUser('carol', 'pw', Role::User);

        for ($i = 0; $i < 5; $i++) {
            $this->kernel->handle(new Request(
                method: 'POST',
                path: '/api/auth/login',
                body: ['username' => 'carol', 'password' => 'wrong'],
                server: ['REMOTE_ADDR' => '203.0.113.99'],
            ));
        }

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/api/auth/login',
            body: ['username' => 'carol', 'password' => 'wrong'],
            server: ['REMOTE_ADDR' => '203.0.113.99'],
        ));

        $this->assertSame(429, $response->status);
        $this->assertNotEmpty($response->headers['retry-after'] ?? '');
    }

    private function seedUser(string $username, string $password, Role $role): void
    {
        $hasher = new PasswordHasher();
        (new UserRepository($this->pdo))->create(
            $username,
            "{$username}@example.com",
            $hasher->hash($password),
            $role,
        );
    }
}
