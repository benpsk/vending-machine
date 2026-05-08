<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Auth\PasswordHasher;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Support\Csrf;
use App\Users\Role;
use Tests\Feature\Fixtures\AdminStubController;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class RbacTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(
            dirname(__DIR__, 3),
            extraControllers: [AdminStubController::class],
            pdo: $this->pdo,
        );
    }

    public function testLoggedOutGetsRedirectedFromAdminRouteWithNextParam(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/stub'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/login?next=%2Fadmin%2Fstub', $response->headers['location'] ?? null);
    }

    public function testNonAdminGetsRedirectedHome(): void
    {
        $this->seedUser('alice', 'pw', Role::User);
        $this->loginAs('alice', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/stub'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['location'] ?? null);
    }

    public function testAdminGetsThrough(): void
    {
        $this->seedUser('admin', 'pw', Role::Admin);
        $this->loginAs('admin', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/stub'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Admin Area', $response->body);
    }

    private function loginAs(string $username, string $password): void
    {
        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);
        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: ['_token' => $token, 'username' => $username, 'password' => $password],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));
        $this->assertSame(302, $response->status, 'login should succeed during RBAC setup');
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
