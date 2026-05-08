<?php

declare(strict_types=1);

namespace App\Database\Mysql;

use App\Products\Product;
use InvalidArgumentException;
use PDO;

// Not `final` so Mockery can stub it for the controller-unit-test layer (Req #15).
class ProductRepository
{
    private const COLUMNS = 'id, name, price, quantity_available, created_at, updated_at';
    private const SORT_ALLOW_LIST = ['id', 'name', 'price', 'quantity_available', 'created_at'];
    private const DIRECTION_ALLOW_LIST = ['asc', 'desc'];
    private const PER_PAGE_MAX = 100;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?Product
    {
        $stmt = $this->pdo->prepare(
            'select ' . self::COLUMNS . ' from products where id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Product::fromRow($row) : null;
    }

    /**
     * Batch lookup keyed by id, used by list views to avoid N+1 fetches.
     *
     * @param list<int> $ids
     * @return array<int, Product>
     */
    public function findManyById(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $unique = array_values(array_unique($ids));
        $placeholders = implode(',', array_fill(0, count($unique), '?'));
        $stmt = $this->pdo->prepare(
            'select ' . self::COLUMNS . " from products where id in ({$placeholders})"
        );
        $stmt->execute($unique);

        $out = [];
        $rows = $stmt->fetchAll();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $product = Product::fromRow($row);
                $out[$product->id] = $product;
            }
        }
        return $out;
    }

    /**
     * Locks the product row for the duration of the current transaction.
     * MUST be called inside an active transaction; otherwise the lock is a no-op.
     * Used by {@see \App\Products\PurchaseService} to avoid overselling.
     */
    public function findByIdForUpdate(int $id): ?Product
    {
        $stmt = $this->pdo->prepare(
            'select ' . self::COLUMNS . ' from products where id = :id for update'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Product::fromRow($row) : null;
    }

    /**
     * Atomically subtracts $by from quantity_available.
     * Caller is responsible for prior {@see findByIdForUpdate} on the same connection.
     */
    public function decrementStock(int $id, int $by): bool
    {
        $stmt = $this->pdo->prepare(
            'update products set quantity_available = quantity_available - :by where id = :id'
        );
        $stmt->execute(['id' => $id, 'by' => $by]);

        return $stmt->rowCount() > 0;
    }

    /**
     * @return array{items: list<Product>, total: int, page: int, perPage: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        string $sort = 'id',
        string $direction = 'asc',
    ): array {
        $direction = strtolower($direction);

        if (!in_array($sort, self::SORT_ALLOW_LIST, true)) {
            throw new InvalidArgumentException("Disallowed sort column: {$sort}");
        }
        if (!in_array($direction, self::DIRECTION_ALLOW_LIST, true)) {
            throw new InvalidArgumentException("Disallowed sort direction: {$direction}");
        }

        $page = max(1, $page);
        $perPage = max(1, min(self::PER_PAGE_MAX, $perPage));
        $offset = ($page - 1) * $perPage;

        $sql = 'select ' . self::COLUMNS
            . ' from products'
            . ' order by ' . $sort . ' ' . $direction . ', id asc'
            . ' limit :limit offset :offset';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $items = [];
        /** @var array<string, mixed> $row */
        foreach ($stmt as $row) {
            $items[] = Product::fromRow($row);
        }

        $countStmt = $this->pdo->prepare('select count(*) from products');
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    public function create(string $name, string $price, int $quantityAvailable): int
    {
        $stmt = $this->pdo->prepare(
            'insert into products (name, price, quantity_available)'
            . ' values (:name, :price, :quantity_available)'
        );
        $stmt->execute([
            'name' => $name,
            'price' => $price,
            'quantity_available' => $quantityAvailable,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name, string $price, int $quantityAvailable): bool
    {
        $stmt = $this->pdo->prepare(
            'update products'
            . ' set name = :name, price = :price, quantity_available = :quantity_available'
            . ' where id = :id'
        );
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'price' => $price,
            'quantity_available' => $quantityAvailable,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('delete from products where id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}
