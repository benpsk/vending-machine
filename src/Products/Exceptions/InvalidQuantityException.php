<?php

declare(strict_types=1);

namespace App\Products\Exceptions;

use RuntimeException;

final class InvalidQuantityException extends RuntimeException
{
    public function __construct(public readonly int $requested)
    {
        parent::__construct("Invalid quantity: {$requested} (must be at least 1).");
    }
}
