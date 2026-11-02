<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\LoginAttemptRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class LoginAttemptRepositoryTest extends TestCase
{
    private PDO $pdo;
    private LoginAttemptRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE auth_login_attempts ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'username_hash TEXT NOT NULL, '
            . 'source_hash TEXT NOT NULL, '
            . 'attempted_at TEXT NOT NULL, '
            . 'was_successful INTEGER NOT NULL DEFAULT 0, '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ')'
        );
        $this->repository = new LoginAttemptRepository($this->pdo);
    }

    public function testStoresAndCountsFailedAttemptsInTheWindow(): void
    {
        $now = new DateTimeImmutable('2026-09-07 12:00:00');
        $this->repository->create('user-hash', 'source-hash', $now, false);
        $this->repository->create('user-hash', 'source-hash', $now->modify('-20 minutes'), false);
        $this->repository->create('user-hash', 'source-hash', $now, true);

        self::assertSame(
            1,
            $this->repository->countFailedSince('user-hash', 'source-hash', $now->modify('-15 minutes'))
        );
    }

    public function testPrunesAttemptsBeforeCutoff(): void
    {
        $now = new DateTimeImmutable('2026-09-07 12:00:00');
        $this->repository->create('user-hash', 'source-hash', $now->modify('-2 days'), false);
        $this->repository->create('user-hash', 'source-hash', $now, false);

        self::assertSame(1, $this->repository->deleteBefore($now->modify('-1 day')));
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM auth_login_attempts')->fetchColumn());
    }
}
