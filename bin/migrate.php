<?php

declare(strict_types=1);

use App\Database\Mysql\Connection;
use App\Database\Mysql\Migrator;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

if (is_file($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}

$host = (string)($_ENV['DB_HOST'] ?? '127.0.0.1');
$port = (int)($_ENV['DB_PORT'] ?? 3306);
$user = (string)($_ENV['DB_USER'] ?? 'root');
$password = (string)($_ENV['DB_PASSWORD'] ?? '');
$baseDb = (string)($_ENV['DB_NAME'] ?? 'vending');
$appEnv = (string)($_ENV['APP_ENV'] ?? 'local');

$targets = [$baseDb];
if ($appEnv !== 'production') {
    $targets[] = $baseDb . '_test';
}

$migrationsDir = $projectRoot . '/db/migrations';

$server = Connection::openServer($host, $port, $user, $password);

$exitCode = 0;
foreach ($targets as $dbName) {
    $quoted = '`' . str_replace('`', '``', $dbName) . '`';
    $server->exec(
        'create database if not exists ' . $quoted
        . ' character set utf8mb4 collate utf8mb4_unicode_ci'
    );

    $pdo = Connection::open($host, $port, $user, $password, $dbName);
    $migrator = new Migrator($pdo, $migrationsDir);

    try {
        $applied = $migrator->migrate();
    } catch (Throwable $e) {
        fwrite(STDERR, "[{$dbName}] failed: " . $e->getMessage() . "\n");
        $exitCode = 1;
        continue;
    }

    echo "[{$dbName}] applied=" . count($applied) . "\n";
    foreach ($applied as $name) {
        echo "  - {$name}\n";
    }
}

exit($exitCode);
