<?php

declare(strict_types=1);

namespace Tests\Unit\Products;

use App\Auth\Storage\ArraySessionStorage;
use App\Database\Mysql\ProductRepository;
use App\Http\Request;
use App\Http\View;
use App\Products\Product;
use App\Products\ProductsController;
use App\Products\PurchaseService;
use App\Validation\Validator;
use DateTimeImmutable;
use Tests\Support\TestCase;

final class ProductsControllerTest extends TestCase
{
    private View $view;
    private ArraySessionStorage $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new View(dirname(__DIR__, 3) . '/templates');
        $this->session = new ArraySessionStorage();
    }

    private function makeController(ProductRepository $repo, ?PurchaseService $purchases = null): ProductsController
    {
        return new ProductsController(
            $this->view,
            $repo,
            new Validator(),
            $this->session,
            $purchases ?? $this->mock(PurchaseService::class),
        );
    }

    public function testIndexRendersListWithRepoData(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('paginate')
            ->once()
            ->with(1, 20, 'id', 'asc')
            ->andReturn([
                'items' => [$this->makeProduct(1, 'Coke', '3.99', 20)],
                'total' => 1,
                'page' => 1,
                'perPage' => 20,
            ]);

        $controller = $this->makeController($repo);
        $response = $controller->index(new Request(method: 'GET', path: '/products'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Coke', $response->body);
    }

    public function testIndexReturns400OnDisallowedSort(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('paginate')
            ->once()
            ->andThrow(new \InvalidArgumentException('Disallowed sort column: evil'));

        $controller = $this->makeController($repo);
        $request = new Request(method: 'GET', path: '/products', query: ['sort' => 'evil']);

        $response = $controller->index($request);

        $this->assertSame(400, $response->status);
    }

    public function testShowReturnsProductDetail(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(7)->once()->andReturn($this->makeProduct(7, 'Pepsi', '6.885', 12));

        $controller = $this->makeController($repo);
        $response = $controller->show(new Request(method: 'GET', path: '/products/7'), 7);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Pepsi', $response->body);
        $this->assertStringContainsString('6.885', $response->body);
    }

    public function testShowReturns404WhenMissing(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(999)->once()->andReturn(null);

        $controller = $this->makeController($repo);
        $response = $controller->show(new Request(method: 'GET', path: '/products/999'), 999);

        $this->assertSame(404, $response->status);
    }

    public function testCreateRendersEmptyForm(): void
    {
        $repo = $this->mock(ProductRepository::class);

        $controller = $this->makeController($repo);
        $response = $controller->create(new Request(method: 'GET', path: '/admin/products/create'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('<form', $response->body);
        $this->assertStringContainsString('name="_token"', $response->body);
    }

    public function testStoreCreatesProductAndRedirects(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('create')
            ->once()
            ->with('Sprite', '2.50', 15)
            ->andReturn(99);

        $controller = $this->makeController($repo);
        $response = $controller->store(new Request(
            method: 'POST',
            path: '/admin/products',
            body: ['name' => 'Sprite', 'price' => '2.50', 'quantity_available' => '15'],
        ));

        $this->assertSame(302, $response->status);
        $this->assertSame('/admin/products', $response->headers['location'] ?? null);
    }

    public function testStoreRendersFormWithErrorsAndOldOnValidationFailure(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldNotReceive('create');

        $controller = $this->makeController($repo);
        $response = $controller->store(new Request(
            method: 'POST',
            path: '/admin/products',
            body: ['name' => '', 'price' => '0', 'quantity_available' => '-1'],
        ));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('required', $response->body);
        $this->assertStringContainsString('greater than or equal', $response->body);
        // Old input preserved (price=0 in the value attribute):
        $this->assertStringContainsString('value="0"', $response->body);
    }

    public function testEditPrefillsFormFromExistingRow(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(3)->once()->andReturn($this->makeProduct(3, 'Water', '0.500', 50));

        $controller = $this->makeController($repo);
        $response = $controller->edit(new Request(method: 'GET', path: '/admin/products/3/edit'), 3);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('value="Water"', $response->body);
        $this->assertStringContainsString('value="0.500"', $response->body);
        $this->assertStringContainsString('action="/admin/products/3"', $response->body);
    }

    public function testEditReturns404ForMissing(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(404)->once()->andReturn(null);

        $controller = $this->makeController($repo);
        $response = $controller->edit(new Request(method: 'GET', path: '/admin/products/404/edit'), 404);

        $this->assertSame(404, $response->status);
    }

    public function testUpdatePersistsAndRedirects(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(5)->once()->andReturn($this->makeProduct(5, 'Coke', '3.99', 10));
        $repo->shouldReceive('update')
            ->once()
            ->with(5, 'Coke Zero', '4.00', 8)
            ->andReturn(true);

        $controller = $this->makeController($repo);
        $response = $controller->update(
            new Request(
                method: 'POST',
                path: '/admin/products/5',
                body: ['name' => 'Coke Zero', 'price' => '4.00', 'quantity_available' => '8'],
            ),
            5,
        );

        $this->assertSame(302, $response->status);
    }

    public function testUpdateReturns404WhenMissing(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(404)->once()->andReturn(null);
        $repo->shouldNotReceive('update');

        $controller = $this->makeController($repo);
        $response = $controller->update(
            new Request(method: 'POST', path: '/admin/products/404', body: []),
            404,
        );

        $this->assertSame(404, $response->status);
    }

    public function testUpdateRendersFormWithErrorsOnValidationFailure(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(7)->once()->andReturn($this->makeProduct(7, 'Pepsi', '6.885', 12));
        $repo->shouldNotReceive('update');

        $controller = $this->makeController($repo);
        $response = $controller->update(
            new Request(
                method: 'POST',
                path: '/admin/products/7',
                body: ['name' => '', 'price' => 'oops', 'quantity_available' => '5'],
            ),
            7,
        );

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('required', $response->body);
        $this->assertStringContainsString('action="/admin/products/7"', $response->body);
    }

    public function testConfirmDestroyRendersConfirmPage(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(2)->once()->andReturn($this->makeProduct(2, 'Pepsi', '6.885', 12));

        $controller = $this->makeController($repo);
        $response = $controller->confirmDestroy(new Request(method: 'GET', path: '/admin/products/2/delete'), 2);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Pepsi', $response->body);
        $this->assertStringContainsString('action="/admin/products/2/delete"', $response->body);
    }

    public function testDestroyDeletesAndRedirects(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(4)->once()->andReturn($this->makeProduct(4, 'Water', '0.500', 50));
        $repo->shouldReceive('delete')->with(4)->once()->andReturn(true);

        $controller = $this->makeController($repo);
        $response = $controller->destroy(new Request(method: 'POST', path: '/admin/products/4/delete'), 4);

        $this->assertSame(302, $response->status);
    }

    public function testDestroyReturns404WhenMissing(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(404)->once()->andReturn(null);
        $repo->shouldNotReceive('delete');

        $controller = $this->makeController($repo);
        $response = $controller->destroy(new Request(method: 'POST', path: '/admin/products/404/delete'), 404);

        $this->assertSame(404, $response->status);
    }

    public function testPurchaseFormRendersWhenProductExists(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(3)->once()->andReturn($this->makeProduct(3, 'Coke', '3.99', 20));

        $controller = $this->makeController($repo);
        $response = $controller->purchaseForm(new Request(method: 'GET', path: '/products/3/purchase'), 3);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Buy Coke', $response->body);
        $this->assertStringContainsString('name="quantity"', $response->body);
    }

    public function testPurchaseFormReturns404ForMissing(): void
    {
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(404)->once()->andReturn(null);

        $controller = $this->makeController($repo);
        $response = $controller->purchaseForm(new Request(method: 'GET', path: '/products/404/purchase'), 404);

        $this->assertSame(404, $response->status);
    }

    public function testPurchaseSuccessRendersReceipt(): void
    {
        $product = $this->makeProduct(3, 'Coke', '3.99', 20);
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(3)->once()->andReturn($product);

        $purchases = $this->mock(PurchaseService::class);
        $purchases->shouldReceive('purchase')
            ->once()
            ->with(7, 3, 2)
            ->andReturn(new \App\Transactions\Transaction(
                id: 99,
                userId: 7,
                productId: 3,
                quantity: 2,
                unitPrice: '3.99',
                totalAmount: '7.980',
                createdAt: new DateTimeImmutable('2026-05-08 10:00:00'),
            ));

        $request = new Request(
            method: 'POST',
            path: '/products/3/purchase',
            body: ['quantity' => '2'],
        );
        $request->setAttribute('user', $this->makeUser(7));

        $controller = $this->makeController($repo, $purchases);
        $response = $controller->purchase($request, 3);

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Thank you', $response->body);
        $this->assertStringContainsString('7.980', $response->body);
    }

    public function testPurchaseRendersFormOnOutOfStock(): void
    {
        $product = $this->makeProduct(3, 'Coke', '3.99', 1);
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(3)->twice()->andReturn($product);

        $purchases = $this->mock(PurchaseService::class);
        $purchases->shouldReceive('purchase')
            ->once()
            ->andThrow(new \App\Products\Exceptions\OutOfStockException(productId: 3, requested: 5, available: 1));

        $request = new Request(
            method: 'POST',
            path: '/products/3/purchase',
            body: ['quantity' => '5'],
        );
        $request->setAttribute('user', $this->makeUser(7));

        $controller = $this->makeController($repo, $purchases);
        $response = $controller->purchase($request, 3);

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Out of stock', $response->body);
        $this->assertStringContainsString('only 1 available', $response->body);
        $this->assertStringContainsString('value="5"', $response->body);
    }

    public function testPurchaseRendersFormOnInvalidQuantity(): void
    {
        $product = $this->makeProduct(3, 'Coke', '3.99', 20);
        $repo = $this->mock(ProductRepository::class);
        $repo->shouldReceive('findById')->with(3)->once()->andReturn($product);

        $purchases = $this->mock(PurchaseService::class);
        $purchases->shouldReceive('purchase')
            ->once()
            ->andThrow(new \App\Products\Exceptions\InvalidQuantityException(0));

        $request = new Request(
            method: 'POST',
            path: '/products/3/purchase',
            body: ['quantity' => '0'],
        );
        $request->setAttribute('user', $this->makeUser(7));

        $controller = $this->makeController($repo, $purchases);
        $response = $controller->purchase($request, 3);

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('Quantity must be at least 1', $response->body);
    }

    private function makeUser(int $id): \App\Users\User
    {
        return new \App\Users\User(
            id: $id,
            username: 'alice',
            email: 'alice@example.com',
            passwordHash: '$2y$10$x',
            role: \App\Users\Role::User,
            createdAt: new DateTimeImmutable('2026-01-01'),
            updatedAt: new DateTimeImmutable('2026-01-01'),
        );
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
}
