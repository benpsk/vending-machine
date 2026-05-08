<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Auth\PasswordHasher;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Support\Csrf;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class ProductCrudTest extends DatabaseTestCase
{
    private TestKernel $kernel;
    private ProductRepository $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
        $this->products = new ProductRepository($this->pdo);
    }

    public function testLoggedOutGetsRedirectedFromCreatePageWithNext(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/products/create'));

        $this->assertSame(302, $response->status);
        $this->assertSame(
            '/login?next=%2Fadmin%2Fproducts%2Fcreate',
            $response->headers['location'] ?? null,
        );
    }

    public function testRegularUserGetsRedirectedHomeFromCreatePage(): void
    {
        $this->seedAndLogin('alice', Role::User);

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/products/create'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['location'] ?? null);
    }

    public function testAdminCanReachCreatePage(): void
    {
        $this->seedAndLogin('admin', Role::Admin);

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/products/create'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<form', $response->body);
    }

    public function testAdminCanCreateProduct(): void
    {
        $this->seedAndLogin('admin', Role::Admin);
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/admin/products',
            body: [
                '_token' => $token,
                'name' => 'Sprite',
                'price' => '2.50',
                'quantity_available' => '15',
            ],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/products', $response->headers['location'] ?? null);

        $page = $this->products->paginate(perPage: 100);
        $names = array_map(static fn ($p) => $p->name, $page['items']);
        $this->assertContains('Sprite', $names);
    }

    public function testAdminCanUpdateProduct(): void
    {
        $this->seedAndLogin('admin', Role::Admin);
        $id = $this->products->create('Coke', '3.99', 20);
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/admin/products/{$id}",
            body: [
                '_token' => $token,
                'name' => 'Coke Zero',
                'price' => '4.00',
                'quantity_available' => '10',
            ],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(302, $response->status);
        $product = $this->products->findById($id);
        $this->assertNotNull($product);
        $this->assertSame('Coke Zero', $product->name);
        $this->assertSame('4.000', $product->price);
        $this->assertSame(10, $product->quantityAvailable);
    }

    public function testAdminCanDeleteProduct(): void
    {
        $this->seedAndLogin('admin', Role::Admin);
        $id = $this->products->create('Throwaway', '1.00', 1);
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/admin/products/{$id}/delete",
            body: ['_token' => $token],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(302, $response->status);
        $this->assertNull($this->products->findById($id));
    }

    public function testRegularUserCannotPostToCreate(): void
    {
        $this->seedAndLogin('alice', Role::User);
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/admin/products',
            body: [
                '_token' => $token,
                'name' => 'Sneaky',
                'price' => '1.00',
                'quantity_available' => '1',
            ],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        // Wrong-role admin write attempt: middleware redirects home, write does not occur.
        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['location'] ?? null);
        $page = $this->products->paginate(perPage: 100);
        $names = array_map(static fn ($p) => $p->name, $page['items']);
        $this->assertNotContains('Sneaky', $names);
    }

    private function seedAndLogin(string $username, Role $role): void
    {
        $hasher = new PasswordHasher();
        (new UserRepository($this->pdo))->create(
            $username,
            "{$username}@example.com",
            $hasher->hash('pw'),
            $role,
        );
        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);
        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: ['_token' => $token, 'username' => $username, 'password' => 'pw'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));
        $this->assertSame(302, $response->status, "{$username} login should succeed");
    }
}
