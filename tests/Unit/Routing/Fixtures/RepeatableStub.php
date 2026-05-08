<?php

declare(strict_types=1);

namespace Tests\Unit\Routing\Fixtures;

use App\Routing\Route;

final class RepeatableStub
{
    #[Route('/admin/products/{id}', methods: ['POST'], name: 'admin.products.update')]
    #[Route('/admin/products/{id}', methods: ['PUT'], name: 'admin.products.update.put')]
    public function update(int $id): string
    {
        return 'update:' . $id;
    }
}
