<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Mysql;

use App\Auth\PasswordHasher;
use App\Database\Mysql\TransactionRepository;
use App\Database\Mysql\UserRepository;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;

final class TransactionRepositoryTest extends DatabaseTestCase
{
    private TransactionRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TransactionRepository($this->pdo);
    }

    public function testRecordAndFindByIdRoundTrip(): void
    {
        $userId = $this->seedUser();
        $productId = $this->seedProduct('Coke', '3.99', 20);

        $txnId = $this->repo->record(
            userId: $userId,
            productId: $productId,
            quantity: 2,
            unitPrice: '3.99',
            totalAmount: '7.980',
        );

        $found = $this->repo->findById($txnId);
        $this->assertNotNull($found);
        $this->assertSame($userId, $found->userId);
        $this->assertSame($productId, $found->productId);
        $this->assertSame(2, $found->quantity);
        $this->assertSame('3.990', $found->unitPrice);
        $this->assertSame('7.980', $found->totalAmount);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(999_999));
    }

    public function testThreeDecimalPricePreserved(): void
    {
        $userId = $this->seedUser();
        $productId = $this->seedProduct('Pepsi', '6.885', 20);

        $txnId = $this->repo->record(
            userId: $userId,
            productId: $productId,
            quantity: 3,
            unitPrice: '6.885',
            totalAmount: '20.655',
        );

        $found = $this->repo->findById($txnId);
        $this->assertNotNull($found);
        $this->assertSame('6.885', $found->unitPrice);
        $this->assertSame('20.655', $found->totalAmount);
    }

    private function seedUser(): int
    {
        $hasher = new PasswordHasher();
        $repo = new UserRepository($this->pdo);
        return $repo->create('alice', 'alice@example.com', $hasher->hash('pw'), Role::User);
    }
}
