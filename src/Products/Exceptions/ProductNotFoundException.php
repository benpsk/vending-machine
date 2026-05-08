<?php

declare(strict_types=1);

namespace App\Products\Exceptions;

use RuntimeException;

final class ProductNotFoundException extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Product not found: {$productId}.");
    }
}
