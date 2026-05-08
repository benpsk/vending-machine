<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Auth\PasswordHasher;
use App\Database\Mysql\Connection;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Support\Csrf;
use App\Users\Role;
use PDO;
use Tests\Support\TestCase;
use Tests\Support\TestKernel;

/**
 * NOT extending DatabaseTestCase — PurchaseService opens its own PDO transaction,
 * conflicts with the per-test BEGIN. Uses raw PDO + explicit cleanup.
 */
final class PurchaseFlowTest extends TestCase
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

    public function testGetPurchaseFormLoggedOutRedirectsToLoginWithNext(): void
    {
        $productId = $this->seedProduct('Coke', '3.99', 20);

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: "/products/{$productId}/purchase",
        ));

        $this->assertSame(302, $response->status);
        $this->assertSame(
            '/login?next=' . rawurlencode("/products/{$productId}/purchase"),
            $response->headers['location'] ?? null,
        );
    }

    public function testGetPurchaseFormLoggedInRendersForm(): void
    {
        $productId = $this->seedProduct('Coke', '3.99', 20);
        $this->loginAs('alice');

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: "/products/{$productId}/purchase",
        ));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Buy Coke', $response->body);
        $this->assertStringContainsString('name="quantity"', $response->body);
    }

    public function testValidPurchaseRendersReceiptAndDecrementsStock(): void
    {
        $productId = $this->seedProduct('Pepsi', '6.885', 20);
        $this->loginAs('bob');
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/products/{$productId}/purchase",
            body: ['_token' => $token, 'quantity' => '3'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Thank you', $response->body);
        $this->assertStringContainsString('20.655', $response->body);

        $product = (new ProductRepository($this->pdo))->findById($productId);
        $this->assertNotNull($product);
        $this->assertSame(17, $product->quantityAvailable);

        $stmt = $this->pdo->prepare('select count(*) from transactions where product_id = :id');
        $stmt->execute(['id' => $productId]);
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    public function testPurchaseWithoutCsrfTokenReturns403(): void
    {
        $productId = $this->seedProduct('Coke', '3.99', 20);
        $this->loginAs('carol');

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/products/{$productId}/purchase",
            body: ['quantity' => '1'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(403, $response->status);
    }

    public function testPurchaseQuantityZeroReturns422WithError(): void
    {
        $productId = $this->seedProduct('Coke', '3.99', 20);
        $this->loginAs('dave');
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/products/{$productId}/purchase",
            body: ['_token' => $token, 'quantity' => '0'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Quantity must be at least 1', $response->body);
    }

    public function testPurchaseAgainstOutOfStockReturns422(): void
    {
        $productId = $this->seedProduct('Water', '0.500', 1);
        $this->loginAs('eve');
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: "/products/{$productId}/purchase",
            body: ['_token' => $token, 'quantity' => '5'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Out of stock', $response->body);
    }

    public function testPurchaseAgainstMissingProductReturns404(): void
    {
        $this->loginAs('frank');

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/products/999999/purchase',
        ));

        $this->assertSame(404, $response->status);
    }

    private function loginAs(string $username): void
    {
        $hasher = new PasswordHasher();
        $userId = (new UserRepository($this->pdo))->create(
            $username,
            "{$username}@example.com",
            $hasher->hash('pw'),
            Role::User,
        );
        $this->userIds[] = $userId;

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
