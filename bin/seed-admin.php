<?php

declare(strict_types=1);

use App\Auth\PasswordHasher;
use App\Database\Mysql\Connection;
use App\Database\Mysql\UserRepository;
use App\Users\Role;
use Dotenv\Dotenv;

$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

if (is_file($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}

$password = (string)($_ENV['SEED_ADMIN_PASSWORD'] ?? '');
if ($password === '') {
    fwrite(STDERR, "SEED_ADMIN_PASSWORD is empty in .env. Set it before seeding.\n");
    exit(1);
}

$pdo = Connection::open(
    host: (string)($_ENV['DB_HOST'] ?? '127.0.0.1'),
    port: (int)($_ENV['DB_PORT'] ?? 3306),
    user: (string)($_ENV['DB_USER'] ?? ''),
    password: (string)($_ENV['DB_PASSWORD'] ?? ''),
    database: (string)($_ENV['DB_NAME'] ?? 'vending'),
);

$repo = new UserRepository($pdo);
$existing = $repo->findByUsername('admin');
if ($existing !== null) {
    echo "admin already exists (id={$existing->id})\n";
    exit(0);
}

$hasher = new PasswordHasher();
$id = $repo->create(
    username: 'admin',
    email: 'admin@vending.local',
    passwordHash: $hasher->hash($password),
    role: Role::Admin,
);

echo "created admin (id={$id})\n";
