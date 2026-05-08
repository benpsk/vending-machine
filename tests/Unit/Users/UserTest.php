<?php

declare(strict_types=1);

namespace Tests\Unit\Users;

use App\Users\Role;
use App\Users\User;
use Tests\Support\TestCase;

final class UserTest extends TestCase
{
    public function testFromRowHydratesFields(): void
    {
        $user = User::fromRow([
            'id' => 7,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'password_hash' => '$2y$10$abc',
            'role' => 'admin',
            'created_at' => '2026-05-08 10:00:00',
            'updated_at' => '2026-05-08 11:00:00',
        ]);

        $this->assertSame(7, $user->id);
        $this->assertSame('alice', $user->username);
        $this->assertSame('alice@example.com', $user->email);
        $this->assertSame('$2y$10$abc', $user->passwordHash);
        $this->assertSame(Role::Admin, $user->role);
        $this->assertSame('2026-05-08 10:00:00', $user->createdAt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-05-08 11:00:00', $user->updatedAt->format('Y-m-d H:i:s'));
    }

    public function testRoleIsAdmin(): void
    {
        $this->assertTrue(Role::Admin->isAdmin());
        $this->assertFalse(Role::User->isAdmin());
    }

    public function testRoleValues(): void
    {
        $this->assertSame('admin', Role::Admin->value);
        $this->assertSame('user', Role::User->value);
    }
}
