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

final class AdminRedirectTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    public function testUnauthenticatedAdminProductsRedirectsToLoginWithNext(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/products'));

        $this->assertSame(302, $response->status);
        $this->assertSame(
            '/login?next=%2Fadmin%2Fproducts',
            $response->headers['location'] ?? null,
        );
    }

    public function testNonAdminUserHittingAdminProductsRedirectsHome(): void
    {
        $this->seedUser('alice', 'pw', Role::User);
        $this->loginAs('alice', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/products'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['location'] ?? null);
    }

    public function testAdminGetsAdminRootRedirectedToProductsList(): void
    {
        $this->seedUser('admin', 'pw', Role::Admin);
        $this->loginAs('admin', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/products', $response->headers['location'] ?? null);
    }

    public function testUnauthenticatedAdminRootRedirectsToLoginWithNext(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/login?next=%2Fadmin', $response->headers['location'] ?? null);
    }

    public function testPublicHomeNeverLinksToAdminEvenForAdminUser(): void
    {
        $this->seedUser('admin', 'pw', Role::Admin);
        $this->loginAs('admin', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/'));

        $this->assertSame(200, $response->status);
        $this->assertStringNotContainsString('href="/admin', $response->body);
    }

    public function testAdminProductsRendersAdminChrome(): void
    {
        $this->seedUser('admin', 'pw', Role::Admin);
        $this->loginAs('admin', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/products'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('admin-area', $response->body);
        $this->assertStringContainsString('admin-wordmark', $response->body);
        $this->assertStringContainsString('View site', $response->body);
    }

    public function testPublicProductsRendersPublicChromeNotAdmin(): void
    {
        $this->seedUser('admin', 'pw', Role::Admin);
        $this->loginAs('admin', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/products'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('public-area', $response->body);
        $this->assertStringNotContainsString('admin-wordmark', $response->body);
        $this->assertStringNotContainsString('href="/admin', $response->body);
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
        $this->assertSame(302, $response->status, 'login should succeed during setup');
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
