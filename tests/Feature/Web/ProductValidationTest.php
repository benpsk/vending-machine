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

final class ProductValidationTest extends DatabaseTestCase
{
    private TestKernel $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
        $this->loginAdmin();
    }

    public function testZeroPriceIsRejectedAndOldInputPreserved(): void
    {
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/admin/products',
            body: [
                '_token' => $token,
                'name' => 'Free Stuff',
                'price' => '0',
                'quantity_available' => '5',
            ],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('greater than or equal', $response->body);
        $this->assertStringContainsString('value="Free Stuff"', $response->body);
        $this->assertStringContainsString('value="0"', $response->body);
    }

    public function testMissingNameSurfacesRequiredError(): void
    {
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/admin/products',
            body: [
                '_token' => $token,
                'name' => '',
                'price' => '1.00',
                'quantity_available' => '1',
            ],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('required', $response->body);
    }

    public function testNegativeQuantityIsRejected(): void
    {
        $token = Csrf::token($this->kernel->session);

        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/admin/products',
            body: [
                '_token' => $token,
                'name' => 'Sprite',
                'price' => '2.50',
                'quantity_available' => '-1',
            ],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(422, $response->status);
        $this->assertStringContainsString('greater than or equal', $response->body);
    }

    public function testMissingCsrfTokenReturns403(): void
    {
        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/admin/products',
            body: [
                'name' => 'Sneaky',
                'price' => '1.00',
                'quantity_available' => '1',
            ],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));

        $this->assertSame(403, $response->status);
    }

    private function loginAdmin(): void
    {
        $hasher = new PasswordHasher();
        (new UserRepository($this->pdo))->create('admin', 'admin@vending.local', $hasher->hash('pw'), Role::Admin);

        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);
        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: ['_token' => $token, 'username' => 'admin', 'password' => 'pw'],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));
        $this->assertSame(302, $response->status);
    }
}
