<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\Mysql\Connection;
use PDO;
use RuntimeException;

abstract class DatabaseTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $dbName = (string)($_ENV['DB_NAME'] ?? '');
        if (!str_ends_with($dbName, '_test')) {
            throw new RuntimeException(
                "Refusing to run integration tests against non-test DB: {$dbName}"
            );
        }

        $this->pdo = Connection::open(
            host: (string)($_ENV['DB_HOST'] ?? '127.0.0.1'),
            port: (int)($_ENV['DB_PORT'] ?? 3306),
            user: (string)($_ENV['DB_USER'] ?? ''),
            password: (string)($_ENV['DB_PASSWORD'] ?? ''),
            database: $dbName,
        );

        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        parent::tearDown();
    }

    protected function seedProduct(string $name, string $price, int $quantity): int
    {
        $stmt = $this->pdo->prepare(
            'insert into products (name, price, quantity_available)'
            . ' values (:name, :price, :quantity)'
        );
        $stmt->execute([
            'name' => $name,
            'price' => $price,
            'quantity' => $quantity,
        ]);
        return (int)$this->pdo->lastInsertId();
    }
}
