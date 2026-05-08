<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Auth\PasswordHasher;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Support\Csrf;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class ProductsListTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
    }

    public function testLoggedOutGetsRedirectedToLoginWithNext(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/products'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/login?next=%2Fproducts', $response->headers['location'] ?? null);
    }

    public function testListShowsSeededProducts(): void
    {
        $this->seedThreeProducts();
        $this->loginAsUser();

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/products'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Coke', $response->body);
        $this->assertStringContainsString('Pepsi', $response->body);
        $this->assertStringContainsString('Water', $response->body);
    }

    public function testPaginationLimitsRowsPerPage(): void
    {
        $this->seedThreeProducts();
        $this->loginAsUser();

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/products',
            query: ['page' => '1', 'perPage' => '2'],
        ));

        $this->assertSame(200, $response->status);
        // Show first two by default sort (id asc): Coke, Pepsi.
        $this->assertStringContainsString('Coke', $response->body);
        $this->assertStringContainsString('Pepsi', $response->body);
        $this->assertStringNotContainsString('Water', $response->body);
        $this->assertStringContainsString('Page 1 of 2', $response->body);
    }

    public function testSortByPriceAscOrdersFromCheapest(): void
    {
        $this->seedThreeProducts();
        $this->loginAsUser();

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/products',
            query: ['sort' => 'price', 'dir' => 'asc'],
        ));

        $this->assertSame(200, $response->status);
        // Water (0.500) should appear before Pepsi (6.885) in the body.
        $waterPos = strpos($response->body, 'Water');
        $pepsiPos = strpos($response->body, 'Pepsi');
        $this->assertNotFalse($waterPos);
        $this->assertNotFalse($pepsiPos);
        $this->assertLessThan($pepsiPos, $waterPos);
    }

    public function testEvilSortColumnReturns400(): void
    {
        $this->loginAsUser();

        $response = $this->kernel->handle(new Request(
            method: 'GET',
            path: '/products',
            query: ['sort' => 'password_hash'],
        ));

        $this->assertSame(400, $response->status);
    }

    private function seedThreeProducts(): void
    {
        $stmt = $this->pdo->prepare(
            'insert into products (name, price, quantity_available) values (:n, :p, :q)'
        );
        $stmt->execute(['n' => 'Coke', 'p' => '3.99', 'q' => 20]);
        $stmt->execute(['n' => 'Pepsi', 'p' => '6.885', 'q' => 20]);
        $stmt->execute(['n' => 'Water', 'p' => '0.500', 'q' => 50]);
    }

    private function loginAsUser(): void
    {
        $hasher = new PasswordHasher();
        (new UserRepository($this->pdo))->create('alice', 'alice@example.com', $hasher->hash('pw'), Role::User);

        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);
        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: ['_token' => $token, 'username' => 'alice', 'password' => 'pw'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));
        $this->assertSame(302, $response->status);
    }
}
