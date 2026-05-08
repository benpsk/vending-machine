<?php

declare(strict_types=1);

namespace Tests\Unit\Routing\Fixtures;

use App\Routing\Route;

final class ProductsStub
{
    #[Route('/products', methods: ['GET'], name: 'products.index')]
    public function index(): string
    {
        return 'index';
    }

    #[Route('/products/{id}', methods: ['GET'], name: 'products.show')]
    public function show(int $id): string
    {
        return 'show:' . $id;
    }

    #[Route('/products/{id}/purchase', methods: ['POST'], name: 'products.purchase')]
    public function purchase(int $id): string
    {
        return 'purchase:' . $id;
    }
}
