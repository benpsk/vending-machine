<?php

declare(strict_types=1);

namespace Tests\Integration\Products;

use App\Auth\PasswordHasher;
use App\Database\Mysql\Connection;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\TransactionRepository;
use App\Database\Mysql\UserRepository;
use App\Products\Exceptions\OutOfStockException;
use App\Products\PurchaseService;
use App\Users\Role;
use PDO;
use Tests\Support\TestCase;

/**
 * Two real OS processes (pcntl_fork) hammer PurchaseService against a product
 * with quantity_available=1. Verifies that exactly one purchase succeeds, the
 * other gets OutOfStockException, and quantity_available never goes negative.
 *
 * Loops 50× internally; CI also runs with --repeat=10 for a 500-attempt soak.
 */
final class PurchaseServiceConcurrencyTest extends TestCase
{
    private const ITERATIONS = 50;

    private PDO $pdo;
    private int $userId;

    protected function setUp(): void
    {
        if (!extension_loaded('pcntl')) {
            $this->markTestSkipped('pcntl extension not loaded');
        }

        parent::setUp();
        $this->pdo = $this->openPdo();

        // Seed a single user once for all iterations.
        $hasher = new PasswordHasher();
        $existing = (new UserRepository($this->pdo))->findByUsername('concurrency-buyer');
        if ($existing !== null) {
            $this->userId = $existing->id;
        } else {
            $this->userId = (new UserRepository($this->pdo))->create(
                'concurrency-buyer',
                'concurrency@example.com',
                $hasher->hash('pw'),
                Role::User,
            );
        }
    }

    public function testExactlyOneOfTwoConcurrentPurchasesSucceeds(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $productId = $this->seedSingleUnitProduct();

            // Each child must open its own PDO; closing the parent's first avoids
            // socket sharing across the fork.
            unset($this->pdo);

            $childPid = pcntl_fork();
            if ($childPid === -1) {
                $this->fail('pcntl_fork failed');
            }

            if ($childPid === 0) {
                // Child process
                $outcome = $this->attemptPurchase($productId);
                exit($outcome); // 0 = success, 1 = out-of-stock, 2 = other failure
            }

            // Parent process attempts purchase concurrently
            $parentOutcome = $this->attemptPurchase($productId);

            pcntl_waitpid($childPid, $status);
            $childOutcome = pcntl_wifexited($status) ? pcntl_wexitstatus($status) : 99;

            // Re-open PDO for the parent's verification queries.
            $this->pdo = $this->openPdo();

            // Exactly one success (exit 0), one out-of-stock (exit 1).
            $outcomes = [$parentOutcome, $childOutcome];
            sort($outcomes);
            $this->assertSame(
                [0, 1],
                $outcomes,
                "iteration #{$i}: expected one success + one out-of-stock, got " . json_encode($outcomes),
            );

            // Stock ends at 0; exactly one transaction row.
            $product = (new ProductRepository($this->pdo))->findById($productId);
            $this->assertNotNull($product);
            $this->assertSame(0, $product->quantityAvailable, "iteration #{$i}: stock should be 0");

            $stmt = $this->pdo->prepare('select count(*) from transactions where product_id = :id');
            $stmt->execute(['id' => $productId]);
            $this->assertSame(1, (int)$stmt->fetchColumn(), "iteration #{$i}: expected exactly one txn");

            // Cleanup this iteration's rows.
            $this->pdo->prepare('delete from transactions where product_id = :id')
                ->execute(['id' => $productId]);
            $this->pdo->prepare('delete from products where id = :id')
                ->execute(['id' => $productId]);
        }
    }

    private function attemptPurchase(int $productId): int
    {
        $pdo = $this->openPdo();
        $service = new PurchaseService(
            $pdo,
            new ProductRepository($pdo),
            new TransactionRepository($pdo),
        );

        try {
            $service->purchase($this->userId, $productId, 1);
            return 0;
        } catch (OutOfStockException) {
            return 1;
        } catch (\Throwable) {
            return 2;
        }
    }

    private function seedSingleUnitProduct(): int
    {
        $stmt = $this->pdo->prepare(
            'insert into products (name, price, quantity_available) values (:n, :p, 1)'
        );
        $stmt->execute(['n' => 'Race-' . bin2hex(random_bytes(4)), 'p' => '1.000']);
        return (int)$this->pdo->lastInsertId();
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
