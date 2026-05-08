<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Auth\PasswordHasher;
use App\Database\Mysql\ProductRepository;
use App\Database\Mysql\TransactionRepository;
use App\Database\Mysql\UserRepository;
use App\Http\Request;
use App\Http\Response;
use App\Support\Csrf;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;
use Tests\Support\TestKernel;

final class TransactionsListTest extends DatabaseTestCase
{
    private TestKernel $kernel;
    private TransactionRepository $transactions;
    private ProductRepository $products;
    private UserRepository $users;

    protected function setUp(): void
    {
        parent::setUp();
        $this->kernel = new TestKernel(dirname(__DIR__, 3), pdo: $this->pdo);
        $this->transactions = new TransactionRepository($this->pdo);
        $this->products = new ProductRepository($this->pdo);
        $this->users = new UserRepository($this->pdo);
    }

    public function testLoggedOutUserGetsRedirectedToLoginWithNext(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/purchases'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/login?next=%2Fpurchases', $response->headers['location'] ?? null);
    }

    public function testUserSeesOnlyTheirOwnPurchases(): void
    {
        $aliceId = $this->seedUser('alice', 'pw', Role::User);
        $bobId = $this->seedUser('bob', 'pw', Role::User);
        $cokeId = $this->products->create('Coke', '3.99', 10);
        $pepsiId = $this->products->create('Pepsi', '4.50', 10);

        // Alice bought Coke once; Bob bought Pepsi once. Alice should not see Pepsi.
        $this->transactions->record($aliceId, $cokeId, 2, '3.990', '7.980');
        $this->transactions->record($bobId, $pepsiId, 1, '4.500', '4.500');

        $this->loginAs('alice', 'pw');
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/purchases'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Coke', $response->body);
        $this->assertStringContainsString('7.980', $response->body);
        $this->assertStringNotContainsString('Pepsi', $response->body);
    }

    public function testEmptyHistoryRendersFriendlyEmptyState(): void
    {
        $this->seedUser('alice', 'pw', Role::User);
        $this->loginAs('alice', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/purchases'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString("haven't bought anything", $response->body);
    }

    public function testNonAdminUserCannotReachAdminTransactionsList(): void
    {
        $this->seedUser('alice', 'pw', Role::User);
        $this->loginAs('alice', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/transactions'));

        $this->assertSame(302, $response->status);
        $this->assertSame('/', $response->headers['location'] ?? null);
    }

    public function testUnauthenticatedAdminTransactionsRedirectsToLoginWithNext(): void
    {
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/transactions'));

        $this->assertSame(302, $response->status);
        $this->assertSame(
            '/login?next=%2Fadmin%2Ftransactions',
            $response->headers['location'] ?? null,
        );
    }

    public function testAdminSeesEveryUsersPurchases(): void
    {
        $aliceId = $this->seedUser('alice', 'pw', Role::User);
        $bobId = $this->seedUser('bob', 'pw', Role::User);
        $this->seedUser('admin', 'pw', Role::Admin);
        $cokeId = $this->products->create('Coke', '3.99', 10);
        $pepsiId = $this->products->create('Pepsi', '4.50', 10);

        $this->transactions->record($aliceId, $cokeId, 1, '3.990', '3.990');
        $this->transactions->record($bobId, $pepsiId, 3, '4.500', '13.500');

        $this->loginAs('admin', 'pw');
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/transactions'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('Coke', $response->body);
        $this->assertStringContainsString('Pepsi', $response->body);
        $this->assertStringContainsString('alice', $response->body);
        $this->assertStringContainsString('bob', $response->body);
        $this->assertStringContainsString('13.500', $response->body);
        // Admin chrome must be present, not public.
        $this->assertStringContainsString('admin-area', $response->body);
        $this->assertStringNotContainsString('public-area', $response->body);
    }

    public function testAdminEmptyStateRenders(): void
    {
        $this->seedUser('admin', 'pw', Role::Admin);
        $this->loginAs('admin', 'pw');

        $response = $this->kernel->handle(new Request(method: 'GET', path: '/admin/transactions'));

        $this->assertSame(200, $response->status);
        $this->assertStringContainsString('No transactions recorded yet.', $response->body);
    }

    public function testTransactionsAreOrderedNewestFirst(): void
    {
        $aliceId = $this->seedUser('alice', 'pw', Role::User);
        $cokeId = $this->products->create('Coke', '3.99', 10);
        $pepsiId = $this->products->create('Pepsi', '4.50', 10);

        // Manually insert with explicit timestamps to control ordering.
        $this->pdo->prepare(
            'insert into transactions (user_id, product_id, quantity, unit_price, total_amount, created_at)'
            . " values (:u, :p, 1, '3.990', '3.990', '2026-04-01 10:00:00')"
        )->execute(['u' => $aliceId, 'p' => $cokeId]);
        $this->pdo->prepare(
            'insert into transactions (user_id, product_id, quantity, unit_price, total_amount, created_at)'
            . " values (:u, :p, 2, '4.500', '9.000', '2026-05-01 10:00:00')"
        )->execute(['u' => $aliceId, 'p' => $pepsiId]);

        $this->loginAs('alice', 'pw');
        $response = $this->kernel->handle(new Request(method: 'GET', path: '/purchases'));

        $pepsiPos = strpos($response->body, 'Pepsi');
        $cokePos = strpos($response->body, 'Coke');
        $this->assertNotFalse($pepsiPos);
        $this->assertNotFalse($cokePos);
        $this->assertLessThan($cokePos, $pepsiPos, 'newest (Pepsi) should appear before older (Coke)');
    }

    private function loginAs(string $username, string $password): void
    {
        $this->kernel->handle(new Request(method: 'GET', path: '/login'));
        $token = Csrf::token($this->kernel->session);
        $response = $this->kernel->handle(new Request(
            method: 'POST',
            path: '/login',
            body: ['_token' => $token, 'username' => $username, 'password' => $password],
            server: ['REMOTE_ADDR' => '127.0.0.1'],
        ));
        $this->assertSame(302, $response->status, 'login should succeed during setup');
    }

    private function seedUser(string $username, string $password, Role $role): int
    {
        $hasher = new PasswordHasher();
        return $this->users->create(
            $username,
            "{$username}@example.com",
            $hasher->hash($password),
            $role,
        );
    }
}
