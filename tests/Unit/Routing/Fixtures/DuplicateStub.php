<?php

declare(strict_types=1);

namespace Tests\Unit\Routing\Fixtures;

use App\Routing\Route;

final class DuplicateStub
{
    #[Route('/dup', methods: ['GET'])]
    public function first(): string
    {
        return 'first';
    }

    #[Route('/dup', methods: ['GET'])]
    public function second(): string
    {
        return 'second';
    }
}
