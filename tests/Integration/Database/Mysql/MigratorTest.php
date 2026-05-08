<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Mysql;

use App\Database\Mysql\Connection;
use App\Database\Mysql\Migrator;
use PDO;
use Tests\Support\TestCase;

final class MigratorTest extends TestCase
{
    private PDO $pdo;
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = Connection::open(
            host: (string)($_ENV['DB_HOST'] ?? '127.0.0.1'),
            port: (int)($_ENV['DB_PORT'] ?? 3306),
            user: (string)($_ENV['DB_USER'] ?? ''),
            password: (string)($_ENV['DB_PASSWORD'] ?? ''),
            database: (string)($_ENV['DB_NAME'] ?? 'vending_test'),
        );

        $this->tmpDir = sys_get_temp_dir() . '/migrator-' . bin2hex(random_bytes(6));
        mkdir($this->tmpDir, 0o755, true);

        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();
        $this->removeDir($this->tmpDir);
        parent::tearDown();
    }

    public function testPendingListsAllFilesWhenNothingApplied(): void
    {
        $this->writeMigration('9001_test_create_a.sql', 'create table _migrator_test_a (id int);');
        $this->writeMigration('9002_test_create_b.sql', 'create table _migrator_test_b (id int);');

        $migrator = new Migrator($this->pdo, $this->tmpDir);

        $this->assertSame(
            ['9001_test_create_a.sql', '9002_test_create_b.sql'],
            $migrator->pending(),
        );
    }

    public function testMigrateAppliesPendingFilesAndRecordsThem(): void
    {
        $this->writeMigration('9001_test_create_a.sql', 'create table _migrator_test_a (id int);');
        $this->writeMigration('9002_test_create_b.sql', 'create table _migrator_test_b (id int);');

        $migrator = new Migrator($this->pdo, $this->tmpDir);
        $applied = $migrator->migrate();

        $this->assertSame(['9001_test_create_a.sql', '9002_test_create_b.sql'], $applied);
        $this->assertTrue($this->tableExists('_migrator_test_a'));
        $this->assertTrue($this->tableExists('_migrator_test_b'));
        $this->assertSame(['9001_test_create_a.sql', '9002_test_create_b.sql'], $this->recordedMigrations());
    }

    public function testMigrateIsIdempotent(): void
    {
        $this->writeMigration('9001_test_create_a.sql', 'create table _migrator_test_a (id int);');

        $migrator = new Migrator($this->pdo, $this->tmpDir);
        $migrator->migrate();

        $secondRun = $migrator->migrate();

        $this->assertSame([], $secondRun);
        $this->assertSame([], $migrator->pending());
    }

    public function testEnsureRegistryCreatesSchemaMigrationsIfMissing(): void
    {
        // Drop the table to verify Migrator recreates it (we'll restore by running the real migration after).
        $this->pdo->exec('drop table if exists schema_migrations');

        $migrator = new Migrator($this->pdo, $this->tmpDir);
        $migrator->pending(); // triggers ensureRegistry

        $this->assertTrue($this->tableExists('schema_migrations'));

        // Re-record the real initial migration so subsequent test runs don't try to re-apply it.
        $this->pdo->exec(
            "insert into schema_migrations (filename) values ('0001_initial_schema.sql')"
        );
    }

    public function testServerLevelConnectionCanQuery(): void
    {
        $server = Connection::openServer(
            host: (string)($_ENV['DB_HOST'] ?? '127.0.0.1'),
            port: (int)($_ENV['DB_PORT'] ?? 3306),
            user: (string)($_ENV['DB_USER'] ?? ''),
            password: (string)($_ENV['DB_PASSWORD'] ?? ''),
        );

        $stmt = $server->prepare('select 1 as one');
        $stmt->execute();
        $this->assertSame('1', (string)$stmt->fetchColumn());
    }

    private function cleanup(): void
    {
        $this->pdo->exec('drop table if exists _migrator_test_a');
        $this->pdo->exec('drop table if exists _migrator_test_b');
        $stmt = $this->pdo->prepare(
            "delete from schema_migrations where filename like '9%_test_%'"
        );
        $stmt->execute();
    }

    private function writeMigration(string $filename, string $sql): void
    {
        file_put_contents($this->tmpDir . '/' . $filename, $sql);
    }

    private function tableExists(string $name): bool
    {
        $stmt = $this->pdo->prepare(
            "select count(*) from information_schema.tables"
            . " where table_schema = database() and table_name = :name"
        );
        $stmt->execute(['name' => $name]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * @return list<string>
     */
    private function recordedMigrations(): array
    {
        $stmt = $this->pdo->prepare(
            "select filename from schema_migrations where filename like '9%_test_%' order by filename"
        );
        $stmt->execute();
        $names = [];
        /** @var array{filename: string} $row */
        foreach ($stmt as $row) {
            $names[] = $row['filename'];
        }
        return $names;
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
