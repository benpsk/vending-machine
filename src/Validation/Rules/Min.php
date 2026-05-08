<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class Min implements RuleInterface
{
    public function __construct(private readonly float|int $min)
    {
    }

    public function validate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null; // Numeric rule reports the type error
        }
        if ((float)$value < (float)$this->min) {
            return "{$field} must be greater than or equal to {$this->min}.";
        }
        return null;
    }
}
