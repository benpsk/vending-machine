<?php

declare(strict_types=1);

namespace App\Auth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class RequiresJwt
{
}
