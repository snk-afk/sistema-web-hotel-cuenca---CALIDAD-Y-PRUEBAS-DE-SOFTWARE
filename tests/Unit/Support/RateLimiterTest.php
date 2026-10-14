<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Repositories\LoginAttemptRepository;
use App\Support\Config;
use App\Support\RateLimiter;
use App\Support\RateLimitExceededException;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    private PDO $pdo;
    private RateLimiter $rate_limiter;

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
        $_ENV['APP_KEY'] = 'test-application-key-for-rate-limiter';
        Config::clear();
        Config::load(dirname(__DIR__, 3));
        $this->rate_limiter = new RateLimiter(
            db: $this->pdo,
            attempt_repository: new LoginAttemptRepository($this->pdo),
            max_attempts: 2,
            window_seconds: 900,
            clock: new DateTimeImmutable('2026-09-07 12:00:00'),
        );
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_KEY']);
        Config::clear();
        parent::tearDown();
    }

    public function testBlocksAfterConfiguredFailedAttempts(): void
    {
        $this->rate_limiter->record('admin', '127.0.0.1', false);
        $this->rate_limiter->record('admin', '127.0.0.1', false);

        $this->expectException(RateLimitExceededException::class);

        $this->rate_limiter->assertAllowed('admin', '127.0.0.1');
    }

    public function testSuccessfulAttemptIsRecordedButDoesNotConsumeFailureLimit(): void
    {
        $this->rate_limiter->record('admin', '127.0.0.1', true);
        $this->rate_limiter->assertAllowed('admin', '127.0.0.1');

        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM auth_login_attempts')->fetchColumn());
    }
}
