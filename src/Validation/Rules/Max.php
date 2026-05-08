<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class Max implements RuleInterface
{
    public function __construct(private readonly float|int $max)
    {
    }

    public function validate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        if ((float)$value > (float)$this->max) {
            return "{$field} must be less than or equal to {$this->max}.";
        }
        return null;
    }
}
