<?php

declare(strict_types=1);

namespace App\Database\Mysql;

use App\Transactions\Transaction;
use PDO;

// Not `final` so Mockery can stub it for the controller-unit-test layer (Req #15).
class TransactionRepository
{
    private const COLUMNS = 'id, user_id, product_id, quantity, unit_price, total_amount, created_at';
    private const PER_PAGE_MAX = 100;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(
        int $userId,
        int $productId,
        int $quantity,
        string $unitPrice,
        string $totalAmount,
    ): int {
        $stmt = $this->pdo->prepare(
            'insert into transactions (user_id, product_id, quantity, unit_price, total_amount)'
            . ' values (:user_id, :product_id, :quantity, :unit_price, :total_amount)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function findById(int $id): ?Transaction
    {
        $stmt = $this->pdo->prepare(
            'select ' . self::COLUMNS . ' from transactions where id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? Transaction::fromRow($row) : null;
    }

    /**
     * Page through one user's purchase history, newest first.
     *
     * @return array{items: list<Transaction>, page: int, perPage: int, total: int}
     */
    public function paginateForUser(int $userId, int $page = 1, int $perPage = 20): array
    {
        return $this->paginateInternal(
            where: 'where user_id = :user_id',
            params: ['user_id' => $userId],
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * Page through every transaction (admin-only), newest first.
     *
     * @return array{items: list<Transaction>, page: int, perPage: int, total: int}
     */
    public function paginateAll(int $page = 1, int $perPage = 20): array
    {
        return $this->paginateInternal(
            where: '',
            params: [],
            page: $page,
            perPage: $perPage,
        );
    }

    /**
     * @param array<string, scalar> $params
     * @return array{items: list<Transaction>, page: int, perPage: int, total: int}
     */
    private function paginateInternal(string $where, array $params, int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, self::PER_PAGE_MAX));
        $offset = ($page - 1) * $perPage;

        $countSql = 'select count(*) from transactions ' . $where;
        $countStmt = $this->pdo->prepare($countSql);
        foreach ($params as $key => $value) {
            $countStmt->bindValue(':' . $key, $value);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $listSql = 'select ' . self::COLUMNS . ' from transactions ' . $where
            . ' order by created_at desc, id desc limit :limit offset :offset';
        $listStmt = $this->pdo->prepare($listSql);
        foreach ($params as $key => $value) {
            $listStmt->bindValue(':' . $key, $value);
        }
        $listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $listStmt->execute();

        $rows = $listStmt->fetchAll();
        $items = is_array($rows) ? array_values(array_map(
            static fn (array $row) => Transaction::fromRow($row),
            $rows,
        )) : [];

        return [
            'items' => $items,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ];
    }
}
