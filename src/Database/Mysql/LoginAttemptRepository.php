<?php

declare(strict_types=1);

namespace App\Database\Mysql;

use DateTimeImmutable;
use PDO;

// Not `final` so Mockery can stub it for the controller-unit-test layer (Req #15).
class LoginAttemptRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(string $ip, bool $success): void
    {
        $stmt = $this->pdo->prepare(
            'insert into login_attempts (ip, success) values (:ip, :success)'
        );
        $stmt->execute([
            'ip' => $ip,
            'success' => $success ? 1 : 0,
        ]);
    }

    public function countFailedSince(string $ip, DateTimeImmutable $since): int
    {
        $stmt = $this->pdo->prepare(
            'select count(*) from login_attempts'
            . ' where ip = :ip and success = 0 and attempted_at >= :since'
        );
        $stmt->execute([
            'ip' => $ip,
            'since' => $since->format('Y-m-d H:i:s'),
        ]);

        return (int)$stmt->fetchColumn();
    }
}
