<?php

declare(strict_types=1);

namespace App\Transactions;

use DateTimeImmutable;

final class Transaction
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly int $productId,
        public readonly int $quantity,
        public readonly string $unitPrice,
        public readonly string $totalAmount,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            userId: (int)$row['user_id'],
            productId: (int)$row['product_id'],
            quantity: (int)$row['quantity'],
            unitPrice: (string)$row['unit_price'],
            totalAmount: (string)$row['total_amount'],
            createdAt: new DateTimeImmutable((string)$row['created_at']),
        );
    }
}
