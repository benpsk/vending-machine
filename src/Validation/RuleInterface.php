<?php

declare(strict_types=1);

namespace App\Validation;

interface RuleInterface
{
    /**
     * Validate $value for $field. Return null on pass, error message on fail.
     */
    public function validate(mixed $value, string $field): ?string;
}
