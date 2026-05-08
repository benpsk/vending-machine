<?php

declare(strict_types=1);

use App\Database\Mysql\Connection;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

if (is_file($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}

$seedFile = $projectRoot . '/db/seeds/0002_seed_products.sql';
if (!is_file($seedFile)) {
    fwrite(STDERR, "seed file missing: {$seedFile}\n");
    exit(1);
}

$pdo = Connection::open(
    host: (string)($_ENV['DB_HOST'] ?? '127.0.0.1'),
    port: (int)($_ENV['DB_PORT'] ?? 3306),
    user: (string)($_ENV['DB_USER'] ?? ''),
    password: (string)($_ENV['DB_PASSWORD'] ?? ''),
    database: (string)($_ENV['DB_NAME'] ?? 'vending'),
);

$count = (int)$pdo->query('select count(*) from products')->fetchColumn();
if ($count > 0) {
    echo "products table already populated (count={$count}); skipping seed\n";
    exit(0);
}

$sql = (string)file_get_contents($seedFile);
$pdo->exec($sql);

$after = (int)$pdo->query('select count(*) from products')->fetchColumn();
echo "seeded products (count={$after})\n";
