<?php

declare(strict_types=1);

namespace App\Auth\Exceptions;

enum JwtFailure: string
{
    case Expired = 'expired';
    case InvalidSignature = 'invalid_signature';
    case WrongAlgorithm = 'wrong_algorithm';
    case MissingClaim = 'missing_claim';
    case Malformed = 'malformed';
}
