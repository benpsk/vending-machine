<?php

declare(strict_types=1);

namespace App\Database\Mysql;

use PDO;
use RuntimeException;

final class Migrator
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $migrationsDir,
    ) {
    }

    /**
     * @return list<string> filenames newly applied this run
     */
    public function migrate(): array
    {
        $this->ensureRegistry();

        $applied = [];
        foreach ($this->pending() as $filename) {
            $path = $this->migrationsDir . '/' . $filename;
            $sql = file_get_contents($path);
            if ($sql === false || $sql === '') {
                throw new RuntimeException("Migration file is empty or unreadable: {$filename}");
            }

            $this->pdo->exec($sql);
            $stmt = $this->pdo->prepare('insert into schema_migrations (filename) values (:filename)');
            $stmt->execute(['filename' => $filename]);

            $applied[] = $filename;
        }

        return $applied;
    }

    /**
     * @return list<string> filenames not yet applied, sorted
     */
    public function pending(): array
    {
        $this->ensureRegistry();

        $all = $this->listMigrationFiles();
        $appliedSet = $this->fetchAppliedSet();

        $pending = [];
        foreach ($all as $filename) {
            if (!isset($appliedSet[$filename])) {
                $pending[] = $filename;
            }
        }

        return $pending;
    }

    private function ensureRegistry(): void
    {
        $this->pdo->exec(
            'create table if not exists schema_migrations ('
            . 'filename varchar(255) not null primary key, '
            . 'applied_at timestamp not null default current_timestamp'
            . ') engine=innodb default charset=utf8mb4 collate=utf8mb4_unicode_ci'
        );
    }

    /**
     * @return list<string>
     */
    private function listMigrationFiles(): array
    {
        if (!is_dir($this->migrationsDir)) {
            throw new RuntimeException("Migrations directory not found: {$this->migrationsDir}");
        }

        $entries = scandir($this->migrationsDir);
        if ($entries === false) {
            throw new RuntimeException("Cannot read migrations directory: {$this->migrationsDir}");
        }

        $files = [];
        foreach ($entries as $entry) {
            if (str_ends_with($entry, '.sql')) {
                $files[] = $entry;
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @return array<string, true>
     */
    private function fetchAppliedSet(): array
    {
        $stmt = $this->pdo->query('select filename from schema_migrations');
        if ($stmt === false) {
            return [];
        }

        $set = [];
        /** @var array{filename: string} $row */
        foreach ($stmt as $row) {
            $set[$row['filename']] = true;
        }
        return $set;
    }
}
