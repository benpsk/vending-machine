<?php

declare(strict_types=1);

namespace Tests\Integration\Products;

use App\Auth\PasswordHasher;
use App\Database\Mysql\Connection;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\TransactionRepository;
use App\Database\Mysql\UserRepository;
use App\Products\Exceptions\InvalidQuantityException;
use App\Products\Exceptions\OutOfStockException;
use App\Products\Exceptions\ProductNotFoundException;
use App\Products\PurchaseService;
use App\Users\Role;
use PDO;
use Tests\Support\TestCase;

/**
 * NOT extending DatabaseTestCase — PurchaseService opens its own PDO transaction,
 * which collides with DatabaseTestCase's outer per-test BEGIN. We open a raw PDO
 * here and clean up explicitly.
 */
final class PurchaseServiceTest extends TestCase
{
    private PDO $pdo;
    private PurchaseService $service;
    /** @var list<int> */
    private array $userIds = [];
    /** @var list<int> */
    private array $productIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = Connection::open(
            host: (string)($_ENV['DB_HOST'] ?? '127.0.0.1'),
            port: (int)($_ENV['DB_PORT'] ?? 3306),
            user: (string)($_ENV['DB_USER'] ?? ''),
            password: (string)($_ENV['DB_PASSWORD'] ?? ''),
            database: (string)($_ENV['DB_NAME'] ?? 'vending_test'),
        );

        $productRepo = new ProductRepository($this->pdo);
        $txRepo = new TransactionRepository($this->pdo);
        $this->service = new PurchaseService($this->pdo, $productRepo, $txRepo);
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

    public function testHappyPathDecrementsStockAndRecordsTransaction(): void
    {
        $userId = $this->seedUser('alice');
        $productId = $this->seedProduct('Coke', '3.99', 20);

        $transaction = $this->service->purchase($userId, $productId, 2);

        $this->assertSame($userId, $transaction->userId);
        $this->assertSame($productId, $transaction->productId);
        $this->assertSame(2, $transaction->quantity);
        $this->assertSame('3.990', $transaction->unitPrice); // DB normalises DECIMAL(10,3) to 3 decimals
        $this->assertSame('7.980', $transaction->totalAmount);

        $product = (new ProductRepository($this->pdo))->findById($productId);
        $this->assertNotNull($product);
        $this->assertSame(18, $product->quantityAvailable);
    }

    public function testThreeDecimalPricePrecisionExactWithBcmath(): void
    {
        // Pepsi at 6.885 × 3 = 20.655 exactly. Floats would drift; bcmul keeps it precise.
        $userId = $this->seedUser('bob');
        $productId = $this->seedProduct('Pepsi', '6.885', 20);

        $transaction = $this->service->purchase($userId, $productId, 3);

        $this->assertSame('20.655', $transaction->totalAmount);
    }

    public function testOutOfStockThrowsAndLeavesProductUnchanged(): void
    {
        $userId = $this->seedUser('carol');
        $productId = $this->seedProduct('Water', '0.500', 1);

        try {
            $this->service->purchase($userId, $productId, 5);
            $this->fail('Expected OutOfStockException');
        } catch (OutOfStockException $e) {
            $this->assertSame($productId, $e->productId);
            $this->assertSame(5, $e->requested);
            $this->assertSame(1, $e->available);
        }

        $product = (new ProductRepository($this->pdo))->findById($productId);
        $this->assertNotNull($product);
        $this->assertSame(1, $product->quantityAvailable);

        $stmt = $this->pdo->prepare('select count(*) from transactions where product_id = :id');
        $stmt->execute(['id' => $productId]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    public function testProductNotFoundThrows(): void
    {
        $userId = $this->seedUser('dave');

        $this->expectException(ProductNotFoundException::class);
        $this->service->purchase($userId, 999_999, 1);
    }

    public function testInvalidQuantityZero(): void
    {
        $userId = $this->seedUser('eve');
        $productId = $this->seedProduct('Coke', '3.99', 20);

        try {
            $this->service->purchase($userId, $productId, 0);
            $this->fail('Expected InvalidQuantityException');
        } catch (InvalidQuantityException $e) {
            $this->assertSame(0, $e->requested);
        }

        // Also verify no transaction was opened (no rollback weirdness).
        $product = (new ProductRepository($this->pdo))->findById($productId);
        $this->assertNotNull($product);
        $this->assertSame(20, $product->quantityAvailable);
    }

    public function testInvalidQuantityNegative(): void
    {
        $userId = $this->seedUser('frank');
        $productId = $this->seedProduct('Coke', '3.99', 20);

        $this->expectException(InvalidQuantityException::class);
        $this->service->purchase($userId, $productId, -1);
    }

    private function seedUser(string $username): int
    {
        $hasher = new PasswordHasher();
        $id = (new UserRepository($this->pdo))->create(
            $username,
            "{$username}@example.com",
            $hasher->hash('pw'),
            Role::User,
        );
        $this->userIds[] = $id;
        return $id;
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
}
