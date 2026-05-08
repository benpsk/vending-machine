<?php

declare(strict_types=1);

namespace App\Database\Mysql;

use App\Users\Role;
use App\Users\User;
use PDO;

// Not `final` so Mockery can stub it for the controller-unit-test layer (Req #15).
class UserRepository
{
    private const COLUMNS = 'id, username, email, password_hash, role, created_at, updated_at';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->pdo->prepare(
            'select ' . self::COLUMNS . ' from users where id = :id'
        );
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? User::fromRow($row) : null;
    }

    /**
     * Batch lookup keyed by id, used by list views to avoid N+1 fetches.
     *
     * @param list<int> $ids
     * @return array<int, User>
     */
    public function findManyById(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $unique = array_values(array_unique($ids));
        $placeholders = implode(',', array_fill(0, count($unique), '?'));
        $stmt = $this->pdo->prepare(
            'select ' . self::COLUMNS . " from users where id in ({$placeholders})"
        );
        $stmt->execute($unique);

        $out = [];
        $rows = $stmt->fetchAll();
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $user = User::fromRow($row);
                $out[$user->id] = $user;
            }
        }
        return $out;
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->pdo->prepare(
            'select ' . self::COLUMNS . ' from users where username = :username'
        );
        $stmt->execute(['username' => $username]);
        $row = $stmt->fetch();

        return is_array($row) ? User::fromRow($row) : null;
    }

    public function create(string $username, string $email, string $passwordHash, Role $role): int
    {
        $stmt = $this->pdo->prepare(
            'insert into users (username, email, password_hash, role)'
            . ' values (:username, :email, :password_hash, :role)'
        );
        $stmt->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => $role->value,
        ]);

        return (int)$this->pdo->lastInsertId();
    }
}
