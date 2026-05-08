<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class Numeric implements RuleInterface
{
    public function validate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null; // let Required handle empties
        }
        if (!is_numeric($value)) {
            return "{$field} must be numeric.";
        }
        return null;
    }
}
