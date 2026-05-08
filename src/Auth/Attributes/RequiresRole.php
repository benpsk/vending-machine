<?php

declare(strict_types=1);

namespace App\Auth\Attributes;

use App\Users\Role;
use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class RequiresRole
{
    public function __construct(public readonly Role $role)
    {
    }
}
