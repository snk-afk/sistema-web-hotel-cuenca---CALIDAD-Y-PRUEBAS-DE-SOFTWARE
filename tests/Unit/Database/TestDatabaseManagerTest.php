<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\TestDatabaseManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TestDatabaseManagerTest extends TestCase
{
    public function testAcceptsTestingDatabaseName(): void
    {
        $manager = new TestDatabaseManager(
            host: '127.0.0.1',
            port: 3306,
            username: 'root',
            password: '',
            database_name: 'cs_hotel_test',
            environment: 'testing',
        );

        $manager->assertSafeEnvironment('testing');

        self::assertTrue(true);
    }

    public function testRejectsDevelopmentEnvironmentBeforeOpeningConnection(): void
    {
        $manager = new TestDatabaseManager(
            host: '127.0.0.1',
            port: 3306,
            username: 'root',
            password: '',
            database_name: 'cs_hotel',
            environment: 'local',
        );

        $this->expectException(RuntimeException::class);

        $manager->assertSafeEnvironment('local');
    }

    public function testRejectsUnapprovedDatabaseName(): void
    {
        $manager = new TestDatabaseManager(
            host: '127.0.0.1',
            port: 3306,
            username: 'root',
            password: '',
            database_name: 'cs_hotel',
            environment: 'testing',
        );

        $this->expectException(RuntimeException::class);

        $manager->assertSafeEnvironment('testing');
    }

    public function testAcceptsDedicatedTestDatabaseWithApprovedSuffix(): void
    {
        $manager = new TestDatabaseManager(
            host: '127.0.0.1',
            port: 3306,
            username: 'root',
            password: '',
            database_name: 'cs_hotel_test_backup',
            environment: 'testing',
        );

        $manager->assertSafeEnvironment('testing');

        self::assertTrue(true);
    }

    public function testRejectsTestEnvironmentWhenConfiguredNameIsNotDedicated(): void
    {
        $manager = new TestDatabaseManager(
            host: '127.0.0.1',
            port: 3306,
            username: 'root',
            password: '',
            database_name: 'hotel_test',
            environment: 'testing',
        );

        $this->expectException(RuntimeException::class);

        $manager->assertSafeEnvironment('testing');
    }
}
