<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Auth\JwtAuthenticator;
use App\Auth\PasswordHasher;
use App\Database\Mysql\Connection;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Users\Role;
use PDO;
use Tests\Support\TestCase;
use Tests\Support\TestKernel;

/**
 * NOT extending DatabaseTestCase — PurchaseService opens its own PDO transaction,
 * conflicts with the per-test wrap. Raw PDO + explicit cleanup, same pattern as PurchaseFlowTest.
 */
final class PurchaseApiTest extends TestCase
{
    private PDO $pdo;
    private TestKernel $kernel;
    /** @var list<int> */
    private array $userIds = [];
    /** @var list<int> */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = $this->openPdo();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    protected function tearDown(): void
    {
        if ($this->productIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->productIds), '?'));
            $stmt = $this->pdo->prepare("delete from transactions where product_id in ({$placeholders})");
            $stmt->execute($this->productIds);
            $stmt = $this->pdo->prepare("delete from products where id in ({$placeholders})");
            $stmt->execute($this->productIds);
        }
        if ($this->userIds !== []) {
            $placeholders = implode(',', array_fill(0, count($this->userIds), '?'));
            $stmt = $this->pdo->prepare("delete from users where id in ({$placeholders})");
            $stmt->execute($this->userIds);
        }
        parent::tearDown();
    }

    public function testPurchaseHappyPath(): void
    {
        $productId = $this->seedProduct('Pepsi', '6.885', 20);
        $token = $this->loginAndIssueToken('alice', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/api/products/{$productId}/purchase",
            body: ['quantity' => '3'],
            headers: ['authorization' => "Bearer {$token}", 'content-type' => 'application/json'],
        ));

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('20.655', $body['data']['total_amount']);
        $this->assertSame(3, $body['data']['quantity']);

        $product = (new ProductRepository($this->pdo))->findById($productId);
        $this->assertNotNull($product);
        $this->assertSame(17, $product->quantityAvailable);
    }

    public function testOutOfStockReturns422(): void
    {
        $productId = $this->seedProduct('Water', '0.500', 1);
        $token = $this->loginAndIssueToken('bob', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/api/products/{$productId}/purchase",
            body: ['quantity' => '5'],
            headers: ['authorization' => "Bearer {$token}", 'content-type' => 'application/json'],
        ));

        $this->assertSame(422, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('out_of_stock', $body['error']['code']);
    }

    public function testInvalidQuantityReturns422(): void
    {
        $productId = $this->seedProduct('Coke', '3.99', 20);
        $token = $this->loginAndIssueToken('carol', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/api/products/{$productId}/purchase",
            body: ['quantity' => '0'],
            headers: ['authorization' => "Bearer {$token}", 'content-type' => 'application/json'],
        ));

        $this->assertSame(422, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('invalid_quantity', $body['error']['code']);
    }

    public function testMissingProductReturns404(): void
    {
        $token = $this->loginAndIssueToken('dave', Role::User);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/api/products/999999/purchase',
            body: ['quantity' => '1'],
            headers: ['authorization' => "Bearer {$token}", 'content-type' => 'application/json'],
        ));

        $this->assertSame(404, $response->status);
    }

    public function testMissingBearerReturns401(): void
    {
        $productId = $this->seedProduct('Coke', '3.99', 20);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/api/products/{$productId}/purchase",
            body: ['quantity' => '1'],
        ));

        $this->assertSame(401, $response->status);
    }

    private function seedProduct(string $name, string $price, int $qty): int
    {
        $stmt = $this->pdo->prepare(
            'insert into products (name, price, quantity_available) values (:n, :p, :q)'
        );
        $stmt->execute(['n' => $name, 'p' => $price, 'q' => $qty]);
        $id = (int)$this->pdo->lastInsertId();
        $this->productIds[] = $id;
        return $id;
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
        $this->userIds[] = $userId;
        $jwt = $this->kernel->container->get(JwtAuthenticator::class);
        return $jwt->issue($userId, $role)['token'];
    }

    private function openPdo(): PDO
    {
        return Connection::open(
            host: (string)($_ENV['DB_HOST'] ?? '127.0.0.1'),
            port: (int)($_ENV['DB_PORT'] ?? 3306),
            user: (string)($_ENV['DB_USER'] ?? ''),
            password: (string)($_ENV['DB_PASSWORD'] ?? ''),
            database: (string)($_ENV['DB_NAME'] ?? 'vending_test'),
        );
    }
}
