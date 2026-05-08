<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Auth\JwtAuthenticator;
use App\Auth\PasswordHasher;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class ProductsApiTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    public function testNoTokenReturns401(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/api/products'));

        $this->assertSame(401, $response->status);
        $this->assertSame('Bearer error="invalid_token"', $response->headers['www-authenticate'] ?? null);
    }

    public function testListWithBearerReturnsItemsAndMeta(): void
    {
        $this->seedProducts();
        $token = $this->loginAndIssueToken('alice', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/api/products',
            headers: ['authorization' => "Bearer {$token}"],
        ));

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, true);
        $names = array_map(static fn ($p) => $p['name'], $body['data']);
        $this->assertContains('Coke', $names);
        $this->assertContains('Pepsi', $names);
        $this->assertSame(3, $body['meta']['total']);
    }

    public function testPaginate(): void
    {
        $this->seedProducts();
        $token = $this->loginAndIssueToken('alice', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/api/products',
            query: ['page' => '1', 'perPage' => '2'],
            headers: ['authorization' => "Bearer {$token}"],
        ));

        $body = json_decode($response->body, true);
        $this->assertCount(2, $body['data']);
        $this->assertSame(3, $body['meta']['total']);
    }

    public function testEvilSortReturns400(): void
    {
        $token = $this->loginAndIssueToken('alice', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/api/products',
            query: ['sort' => 'password_hash'],
            headers: ['authorization' => "Bearer {$token}"],
        ));

        $this->assertSame(400, $response->status);
    }

    public function testShowReturnsProduct(): void
    {
        $this->seedProducts();
        $repo = new ProductRepository($this->pdo);
        $first = $repo->paginate(perPage: 1)['items'][0];
        $token = $this->loginAndIssueToken('alice', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: "/api/products/{$first->id}",
            headers: ['authorization' => "Bearer {$token}"],
        ));

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame($first->id, $body['data']['id']);
    }

    public function testNonAdminCannotPost(): void
    {
        $token = $this->loginAndIssueToken('alice', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/api/products',
            body: ['name' => 'Sneaky', 'price' => '1.00', 'quantity_available' => '1'],
            headers: ['authorization' => "Bearer {$token}", 'content-type' => 'application/json'],
        ));

        $this->assertSame(403, $response->status);
    }

    public function testAdminCanCreate(): void
    {
        $token = $this->loginAndIssueToken('admin', Role::Admin);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/api/products',
            body: ['name' => 'Sprite', 'price' => '2.50', 'quantity_available' => '15'],
            headers: ['authorization' => "Bearer {$token}", 'content-type' => 'application/json'],
        ));

        $this->assertSame(201, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('Sprite', $body['data']['name']);

        $stmt = $this->pdo->prepare("select count(*) from products where name = 'Sprite'");
        $stmt->execute();
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    public function testAdminInvalidPostReturns422WithFields(): void
    {
        $token = $this->loginAndIssueToken('admin', Role::Admin);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/api/products',
            body: ['name' => '', 'price' => '0', 'quantity_available' => '-1'],
            headers: ['authorization' => "Bearer {$token}", 'content-type' => 'application/json'],
        ));

        $this->assertSame(422, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('validation_failed', $body['error']['code']);
        $this->assertArrayHasKey('name', $body['error']['fields']);
        $this->assertArrayHasKey('price', $body['error']['fields']);
    }

    public function testAdminCanUpdate(): void
    {
        $repo = new ProductRepository($this->pdo);
        $id = $repo->create('Coke', '3.99', 20);
        $token = $this->loginAndIssueToken('admin', Role::Admin);

        $response = $this->kernel->handle(new Request(
            method: 'PUT',
            path: "/api/products/{$id}",
            body: ['name' => 'Coke Zero', 'price' => '4.00', 'quantity_available' => '10'],
            headers: ['authorization' => "Bearer {$token}", 'content-type' => 'application/json'],
        ));

        $this->assertSame(200, $response->status);
        $product = $repo->findById($id);
        $this->assertNotNull($product);
        $this->assertSame('Coke Zero', $product->name);
    }

    public function testAdminCanDelete(): void
    {
        $repo = new ProductRepository($this->pdo);
        $id = $repo->create('Throwaway', '1.00', 1);
        $token = $this->loginAndIssueToken('admin', Role::Admin);

        $response = $this->kernel->handle(new Request(
            method: 'DELETE',
            path: "/api/products/{$id}",
            headers: ['authorization' => "Bearer {$token}"],
        ));

        $this->assertSame(204, $response->status);
        $this->assertSame('', $response->body);
        $this->assertNull($repo->findById($id));
    }

    private function seedProducts(): void
    {
        $stmt = $this->pdo->prepare(
            'insert into products (name, price, quantity_available) values (:n, :p, :q)'
        );
        $stmt->execute(['n' => 'Coke', 'p' => '3.99', 'q' => 20]);
        $stmt->execute(['n' => 'Pepsi', 'p' => '6.885', 'q' => 20]);
        $stmt->execute(['n' => 'Water', 'p' => '0.500', 'q' => 50]);
    }

    private function loginAndIssueToken(string $username, Role $role): string
    {
        $hasher = new PasswordHasher();
        $userId = (new UserRepository($this->pdo))->create(
            $username,
            "{$username}@example.com",
            $hasher->hash('pw'),
            $role,
        );
        $jwt = $this->kernel->container->get(JwtAuthenticator::class);
        return $jwt->issue($userId, $role)['token'];
    }
}
