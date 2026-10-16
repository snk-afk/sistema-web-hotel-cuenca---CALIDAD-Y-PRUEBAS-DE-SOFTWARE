<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use App\Database\Migrator;
use Tests\Support\MySqlTestCase;

final class TestDatabaseLifecycleTest extends MySqlTestCase
{
    public function testUsesTheDedicatedTestDatabase(): void
    {
        $statement = $this->pdo()->query('SELECT DATABASE()');

        self::assertSame('railway_test', $statement->fetchColumn());
    }

    public function testMigrationRegistryContainsEachMigrationOnce(): void
    {
        $migration_files = glob(dirname(__DIR__, 3) . '/database/migrations/*.php');

        self::assertIsArray($migration_files);

        $expected_migrations = array_map(
            static fn (string $file_path): string => basename($file_path),
            $migration_files,
        );
        sort($expected_migrations);

        $statement = $this->pdo()->query('SELECT migration FROM migrations ORDER BY migration ASC');
        $executed_migrations = $statement->fetchAll(\PDO::FETCH_COLUMN);

        self::assertSame($expected_migrations, $executed_migrations);
    }

    public function testSecondMigrationRunHasNoPendingMigrations(): void
    {
        $migrator = new Migrator($this->pdo(), dirname(__DIR__, 3) . '/database/migrations');

        self::assertSame([], $migrator->migrate());
    }
}
