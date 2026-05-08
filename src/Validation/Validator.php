<?php

declare(strict_types=1);

namespace App\Validation;

final class Validator
{
    /**
     * Run every rule against every field. Collects all errors. Throws if any.
     *
     * @param array<string, mixed> $data
     * @param array<string, list<RuleInterface>> $rules
     */
    public function validate(array $data, array $rules): void
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = array_key_exists($field, $data) ? $data[$field] : null;
            foreach ($fieldRules as $rule) {
                $message = $rule->validate($value, $field);
                if ($message !== null) {
                    $errors[$field][] = $message;
                }
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors, $data);
        }
    }
}
