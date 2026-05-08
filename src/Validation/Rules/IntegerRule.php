<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class IntegerRule implements RuleInterface
{
    public function validate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value)) {
            return null;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return null;
        }
        return "{$field} must be an integer.";
    }
}
