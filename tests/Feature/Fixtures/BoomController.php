<?php

declare(strict_types=1);

namespace Tests\Feature\Fixtures;

use App\Http\Request;
use App\Http\Response;
use App\Routing\Route;
use RuntimeException;

final class BoomController
{
    #[Route('/boom', methods: ['GET'], name: 'fixtures.boom.web')]
    public function webBoom(Request $request): Response
    {
        throw new RuntimeException('boom-web');
    }

    #[Route('/api/boom', methods: ['GET'], name: 'fixtures.boom.api')]
    public function apiBoom(Request $request): Response
    {
        throw new RuntimeException('boom-api');
    }
}
