<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Mysql;

use App\Database\Mysql\LoginAttemptRepository;
use DateTimeImmutable;
use Tests\Support\DatabaseTestCase;

final class LoginAttemptRepositoryTest extends DatabaseTestCase
{
    private LoginAttemptRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new LoginAttemptRepository($this->pdo);
    }

    public function testRecordSucceedsForBothOutcomes(): void
    {
        $this->repo->record('203.0.113.1', false);
        $this->repo->record('203.0.113.1', true);

        $stmt = $this->pdo->prepare('select count(*) from login_attempts where ip = :ip');
        $stmt->execute(['ip' => '203.0.113.1']);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    public function testCountFailedSinceCountsOnlyFailuresWithinWindow(): void
    {
        $this->repo->record('198.51.100.5', false);
        $this->repo->record('198.51.100.5', false);
        $this->repo->record('198.51.100.5', true);
        $this->repo->record('198.51.100.6', false);

        $now = new DateTimeImmutable();
        $window = $now->modify('-15 minutes');

        $this->assertSame(2, $this->repo->countFailedSince('198.51.100.5', $window));
        $this->assertSame(1, $this->repo->countFailedSince('198.51.100.6', $window));
        $this->assertSame(0, $this->repo->countFailedSince('198.51.100.7', $window));
    }

    public function testCountFailedSinceExcludesAttemptsBeforeWindow(): void
    {
        $stmt = $this->pdo->prepare(
            'insert into login_attempts (ip, success, attempted_at) values (:ip, 0, :ts)'
        );
        $oldTimestamp = (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s');
        $stmt->execute(['ip' => '203.0.113.99', 'ts' => $oldTimestamp]);

        $window = (new DateTimeImmutable())->modify('-15 minutes');
        $this->assertSame(0, $this->repo->countFailedSince('203.0.113.99', $window));
    }
}
