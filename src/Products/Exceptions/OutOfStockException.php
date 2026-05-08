<?php

declare(strict_types=1);

namespace App\Products\Exceptions;

use RuntimeException;

final class OutOfStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requested,
        public readonly int $available,
    ) {
        parent::__construct(
            "Product {$productId} is out of stock (requested {$requested}, available {$available})."
        );
    }
}
