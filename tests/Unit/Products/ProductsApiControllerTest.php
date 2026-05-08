<?php

declare(strict_types=1);

namespace Tests\Unit\Products;

use App\Database\Mysql\ProductRepository;
use App\Http\Request;
use App\Products\Exceptions\InvalidQuantityException;
use App\Products\Exceptions\OutOfStockException;
use App\Products\Exceptions\ProductNotFoundException;
use App\Products\Product;
use App\Products\ProductsApiController;
use App\Products\PurchaseService;
use App\Transactions\Transaction;
use App\Users\Role;
use App\Users\User;
use App\Validation\Validator;
use DateTimeImmutable;
use Tests\Support\TestCase;

final class ProductsApiControllerTest extends TestCase
{
    public function testIndexReturnsEnvelopeWithMeta(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('paginate')
            ->with(1, 20, 'id', 'asc')
            ->once()
            ->andReturn([
                'items' => [$this->makeProduct(1, 'Coke', '3.99', 20)],
                'total' => 1,
                'page' => 1,
                'perPage' => 20,
            ]);

        $controller = new ProductsApiController($repo, new Validator(), $this->mock(PurchaseService::class));
        $response = $controller->index(new Request(method: 'GET', path: '/api/products'));

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame(1, $body['meta']['total']);
        $this->assertSame('Coke', $body['data'][0]['name']);
    }

    public function testIndexInvalidSortReturns400(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('paginate')->once()->andThrow(new \InvalidArgumentException('Disallowed sort'));

        $controller = new ProductsApiController($repo, new Validator(), $this->mock(PurchaseService::class));
        $response = $controller->index(new Request(
            method: 'GET',
            path: '/api/products',
            query: ['sort' => 'evil'],
        ));

        $this->assertSame(400, $response->status);
        $this->assertStringContainsString('bad_request', $response->body);
    }

    public function testShowHappyAndNotFound(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(7)->once()->andReturn($this->makeProduct(7, 'Pepsi', '6.885', 12));
        $repo->shouldReceive('findById')->with(404)->once()->andReturn(null);

        $controller = new ProductsApiController($repo, new Validator(), $this->mock(PurchaseService::class));

        $ok = $controller->show(new Request(method: 'GET', path: '/api/products/7'), 7);
        $this->assertSame(200, $ok->status);
        $body = json_decode($ok->body, true);
        $this->assertSame('Pepsi', $body['data']['name']);

        $notFound = $controller->show(new Request(method: 'GET', path: '/api/products/404'), 404);
        $this->assertSame(404, $notFound->status);
    }

    public function testStoreCreatesAndReturns201(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('create')->with('Sprite', '2.50', 15)->once()->andReturn(99);

        $controller = new ProductsApiController($repo, new Validator(), $this->mock(PurchaseService::class));
        $response = $controller->store(new Request(
            method: 'POST',
            path: '/api/products',
            body: ['name' => 'Sprite', 'price' => '2.50', 'quantity_available' => '15'],
        ));

        $this->assertSame(201, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame(99, $body['data']['id']);
    }

    public function testStoreReturns422OnValidationFailure(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldNotReceive('create');

        $controller = new ProductsApiController($repo, new Validator(), $this->mock(PurchaseService::class));
        $response = $controller->store(new Request(
            method: 'POST',
            path: '/api/products',
            body: ['name' => '', 'price' => '0', 'quantity_available' => '-1'],
        ));

        $this->assertSame(422, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('validation_failed', $body['error']['code']);
        $this->assertArrayHasKey('name', $body['error']['fields']);
        $this->assertArrayHasKey('price', $body['error']['fields']);
    }

    public function testUpdateHappyAndNotFound(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(5)->once()->andReturn($this->makeProduct(5, 'Coke', '3.99', 10));
        $repo->shouldReceive('update')->with(5, 'Coke Zero', '4.00', 8)->once()->andReturn(true);

        $controller = new ProductsApiController($repo, new Validator(), $this->mock(PurchaseService::class));

        $ok = $controller->update(
            new Request(
                method: 'PUT',
                path: '/api/products/5',
                body: ['name' => 'Coke Zero', 'price' => '4.00', 'quantity_available' => '8'],
            ),
            5,
        );
        $this->assertSame(200, $ok->status);

        $repo->shouldReceive('findById')->with(404)->once()->andReturn(null);
        $missing = $controller->update(new Request(method: 'PUT', path: '/api/products/404', body: []), 404);
        $this->assertSame(404, $missing->status);
    }

    public function testDestroyHappyAndNotFound(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(2)->once()->andReturn($this->makeProduct(2, 'Pepsi', '6.885', 12));
        $repo->shouldReceive('delete')->with(2)->once()->andReturn(true);

        $controller = new ProductsApiController($repo, new Validator(), $this->mock(PurchaseService::class));

        $ok = $controller->destroy(new Request(method: 'DELETE', path: '/api/products/2'), 2);
        $this->assertSame(204, $ok->status);
        $this->assertSame('', $ok->body);

        $repo->shouldReceive('findById')->with(404)->once()->andReturn(null);
        $missing = $controller->destroy(new Request(method: 'DELETE', path: '/api/products/404'), 404);
        $this->assertSame(404, $missing->status);
    }

    public function testPurchaseSuccessReturnsTransactionEnvelope(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $purchases = $this->mock(PurchaseService::class);
        $purchases->shouldReceive('purchase')
            ->with(7, 3, 2)
            ->once()
            ->andReturn(new Transaction(
                id: 99,
                userId: 7,
                productId: 3,
                quantity: 2,
                unitPrice: '3.990',
                totalAmount: '7.980',
                createdAt: new DateTimeImmutable('2026-05-08 10:00:00'),
            ));

        $request = new Request(
            method: 'POST',
            path: '/api/products/3/purchase',
            body: ['quantity' => '2'],
        );
        $request->setAttribute('user', $this->makeUser(7));

        $controller = new ProductsApiController($repo, new Validator(), $purchases);
        $response = $controller->purchase($request, 3);

        $this->assertSame(200, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame(99, $body['data']['id']);
        $this->assertSame('7.980', $body['data']['total_amount']);
    }

    public function testPurchaseOutOfStockReturns422(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $purchases = $this->mock(PurchaseService::class);
        $purchases->shouldReceive('purchase')
            ->once()
            ->andThrow(new OutOfStockException(productId: 3, requested: 5, available: 1));

        $request = new Request(
            method: 'POST',
            path: '/api/products/3/purchase',
            body: ['quantity' => '5'],
        );
        $request->setAttribute('user', $this->makeUser(7));

        $controller = new ProductsApiController($repo, new Validator(), $purchases);
        $response = $controller->purchase($request, 3);

        $this->assertSame(422, $response->status);
        $body = json_decode($response->body, true);
        $this->assertSame('out_of_stock', $body['error']['code']);
    }

    public function testPurchaseInvalidQuantityReturns422(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $purchases = $this->mock(PurchaseService::class);
        $purchases->shouldReceive('purchase')->once()->andThrow(new InvalidQuantityException(0));

        $request = new Request(method: 'POST', path: '/api/products/3/purchase', body: ['quantity' => '0']);
        $request->setAttribute('user', $this->makeUser(7));

        $controller = new ProductsApiController($repo, new Validator(), $purchases);
        $response = $controller->purchase($request, 3);

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('invalid_quantity', $response->body);
    }

    public function testPurchaseProductNotFoundReturns404(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $purchases = $this->mock(PurchaseService::class);
        $purchases->shouldReceive('purchase')->once()->andThrow(new ProductNotFoundException(404));

        $request = new Request(method: 'POST', path: '/api/products/404/purchase', body: ['quantity' => '1']);
        $request->setAttribute('user', $this->makeUser(7));

        $controller = new ProductsApiController($repo, new Validator(), $purchases);
        $response = $controller->purchase($request, 404);

        $this->assertSame(404, $response->status);
    }

    private function makeProduct(int $id, string $name, string $price, int $qty): Product
    {
        return new Product(
            id: $id,
            name: $name,
            price: $price,
            quantityAvailable: $qty,
            createdAt: new DateTimeImmutable('2026-05-01'),
            updatedAt: new DateTimeImmutable('2026-05-01'),
        );
    }

    private function makeUser(int $id): User
    {
        return new User(
            id: $id,
            username: 'alice',
            email: 'alice@example.com',
            passwordHash: '$2y$10$x',
            role: Role::User,
            createdAt: new DateTimeImmutable('2026-01-01'),
            updatedAt: new DateTimeImmutable('2026-01-01'),
        );
    }
}
