<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Fixtures;

final class ContractImpl implements InterfaceContract
{
    public function name(): string
    {
        return 'impl';
    }
}
