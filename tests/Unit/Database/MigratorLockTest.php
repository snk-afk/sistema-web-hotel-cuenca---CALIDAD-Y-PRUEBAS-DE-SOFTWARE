<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigratorLockTest extends TestCase
{
    public function testSqliteMigrationUsesSafeNoopLockAndReleasesIt(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $migrator = new Migrator($pdo, __DIR__ . '/../../../database/migrations');

        self::assertTrue($migrator->acquireLock());
        $migrator->releaseLock();
        self::assertSame([], $migrator->getLockState());
    }
}
