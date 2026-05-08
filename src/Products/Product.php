<?php

declare(strict_types=1);

namespace App\Products;

use DateTimeImmutable;

final class Product
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $price,
        public readonly int $quantityAvailable,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            name: (string)$row['name'],
            price: (string)$row['price'],
            quantityAvailable: (int)$row['quantity_available'],
            createdAt: new DateTimeImmutable((string)$row['created_at']),
            updatedAt: new DateTimeImmutable((string)$row['updated_at']),
        );
    }
}
