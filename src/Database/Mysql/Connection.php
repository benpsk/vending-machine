<?php

declare(strict_types=1);

namespace App\Database\Mysql;

use PDO;

final class Connection
{
    public static function open(
        string $host,
        int $port,
        string $user,
        string $password,
        string $database,
        string $charset = 'utf8mb4',
    ): PDO {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $database, $charset);
        return new PDO($dsn, $user, $password, self::attributes());
    }

    public static function openServer(
        string $host,
        int $port,
        string $user,
        string $password,
        string $charset = 'utf8mb4',
    ): PDO {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=%s', $host, $port, $charset);
        return new PDO($dsn, $user, $password, self::attributes());
    }

    /**
     * @return array<int, mixed>
     */
    private static function attributes(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }
}
