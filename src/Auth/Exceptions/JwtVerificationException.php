<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

use RuntimeException;

final class JwtVerificationException extends RuntimeException
{
    public function __construct(
        public readonly JwtFailure $failure,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : "JWT verification failed: {$failure->value}");
    }
}
