<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Auth\PasswordHasher;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Http\Response;
use App\Support\Csrf;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class RateLimitTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
        $this->seedUser('alice', 'real-password', Role::User);
    }

    public function testFiveFailedAttemptsTriggersLockout(): void
    {
        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);

        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postLogin($token, 'alice', 'wrong');
            $this->assertSame(200, $response->status, "attempt #{$i} should still render the form");
        }

        $sixth = $this->postLogin($token, 'alice', 'wrong');
        $this->assertSame(429, $sixth->status);
        $this->assertNotEmpty($sixth->headers['retry-after'] ?? '');
    }

    public function testCorrectPasswordIsBlockedDuringLockoutWindow(): void
    {
        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);

        for ($i = 1; $i <= 5; $i++) {
            $this->postLogin($token, 'alice', 'wrong');
        }

        // Even the correct password is blocked while the failure count exceeds the threshold.
        $response = $this->postLogin($token, 'alice', 'real-password');
        $this->assertSame(429, $response->status);
    }

    private function postLogin(string $token, string $username, string $password): Response
    {
        return $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: ['_token' => $token, 'username' => $username, 'password' => $password],
            server: ['REMOTE_ADDR' => '203.0.113.42'],
        ));
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
