<?php

declare(strict_types=1);

namespace App\Users;

use DateTimeImmutable;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly Role $role,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int)$row['id'],
            username: (string)$row['username'],
            email: (string)$row['email'],
            passwordHash: (string)$row['password_hash'],
            role: Role::from((string)$row['role']),
            createdAt: new DateTimeImmutable((string)$row['created_at']),
            updatedAt: new DateTimeImmutable((string)$row['updated_at']),
        );
    }
}
