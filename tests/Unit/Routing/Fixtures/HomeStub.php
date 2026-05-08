<?php

declare(strict_types=1);

namespace Tests\Unit\Routing\Fixtures;

use App\Routing\Route;

final class HomeStub
{
    #[Route('/', methods: ['GET'], name: 'home')]
    public function index(): string
    {
        return 'home';
    }

    public function untaggedHelper(): string
    {
        return 'should not be discovered';
    }
}
