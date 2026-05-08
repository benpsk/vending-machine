<?php

declare(strict_types=1);

namespace App\Routing;

use RuntimeException;

final class MethodNotAllowedException extends RuntimeException
{
    /**
     * @param list<string> $allowedMethods
     */
    public function __construct(public readonly array $allowedMethods, string $message = '')
    {
        parent::__construct($message !== '' ? $message : 'Method not allowed');
    }
}
