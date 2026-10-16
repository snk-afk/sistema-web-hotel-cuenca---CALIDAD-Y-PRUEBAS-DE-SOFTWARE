<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Database\TestDatabaseManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\MySqlTestDatabase;

final class MySqlTestDatabaseTest extends TestCase
{
    public function testRejectsConnectionWhenEnvironmentIsNotTesting(): void
    {
        $manager = new TestDatabaseManager(
            host: '127.0.0.1',
            port: 3306,
            username: 'root',
            password: '',
            database_name: 'railway_test',
            environment: 'local',
        );
        $database = new MySqlTestDatabase($manager, 'local');

        $this->expectException(RuntimeException::class);

        $database->connection();
    }
}
