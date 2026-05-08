<?php

declare(strict_types=1);

namespace App\Validation;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    /**
     * @param array<string, list<string>> $errors
     * @param array<string, mixed> $input
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $input,
    ) {
        parent::__construct('Validation failed.');
    }
}
