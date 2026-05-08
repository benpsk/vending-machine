<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class MaxLength implements RuleInterface
{
    public function __construct(private readonly int $max)
    {
    }

    public function validate(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_string($value)) {
            return null;
        }
        if (mb_strlen($value) > $this->max) {
            return "{$field} must not be longer than {$this->max} characters.";
        }
        return null;
    }
}
