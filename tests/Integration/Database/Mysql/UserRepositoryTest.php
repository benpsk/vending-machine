<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Mysql;

use App\Database\Mysql\UserRepository;
use App\Users\Role;
use Tests\Support\DatabaseTestCase;

final class UserRepositoryTest extends DatabaseTestCase
{
    private UserRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new UserRepository($this->pdo);
    }

    public function testCreateAndFindById(): void
    {
        $id = $this->repo->create('alice', 'alice@example.com', '$2y$10$abc', Role::User);

        $found = $this->repo->findById($id);
        $this->assertNotNull($found);
        $this->assertSame('alice', $found->username);
        $this->assertSame('alice@example.com', $found->email);
        $this->assertSame(Role::User, $found->role);
    }

    public function testFindByUsername(): void
    {
        $this->repo->create('bob', 'bob@example.com', '$2y$10$xyz', Role::Admin);

        $found = $this->repo->findByUsername('bob');
        $this->assertNotNull($found);
        $this->assertSame('bob', $found->username);
        $this->assertSame(Role::Admin, $found->role);
    }

    public function testFindByIdReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findById(999_999));
    }

    public function testFindByUsernameReturnsNullForMissing(): void
    {
        $this->assertNull($this->repo->findByUsername('nobody'));
    }

    public function testCreatePersistsRoleEnumValue(): void
    {
        $id = $this->repo->create('carol', 'carol@example.com', '$2y$10$h', Role::Admin);

        $stmt = $this->pdo->prepare('select role from users where id = :id');
        $stmt->execute(['id' => $id]);
        $this->assertSame('admin', (string)$stmt->fetchColumn());
    }
}
