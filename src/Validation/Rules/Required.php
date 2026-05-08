<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class Required implements RuleInterface
{
    public function validate(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return "{$field} is required.";
        }
        if (is_string($value) && trim($value) === '') {
            return "{$field} is required.";
        }
        if (is_array($value) && $value === []) {
            return "{$field} is required.";
        }
        return null;
    }
}
