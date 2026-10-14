<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\Migrator;
use Database\Seeds\DatabaseSeeder;
use PDO;
use PHPUnit\Framework\TestCase;

final class DatabaseSeederTest extends TestCase
{
    private PDO $pdo;

    /** @var array<string, mixed> */
    private array $bootstrap_env = [];

    private mixed $app_env;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->app_env = $_ENV['APP_ENV'] ?? null;

        foreach ([
            'BOOTSTRAP_ADMIN_USERNAME' => 'admin',
            'BOOTSTRAP_ADMIN_EMAIL' => 'admin@example.test',
            'BOOTSTRAP_ADMIN_PASSWORD' => 'safe-test-password-123',
            'BOOTSTRAP_ADMIN_NAME' => 'Admin de pruebas',
        ] as $key => $value) {
            $this->bootstrap_env[$key] = $_ENV[$key] ?? null;
            $_ENV[$key] = $value;
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->bootstrap_env as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
                continue;
            }

            $_ENV[$key] = $value;
        }

        if ($this->app_env === null) {
            unset($_ENV['APP_ENV']);
        } else {
            $_ENV['APP_ENV'] = $this->app_env;
        }

        parent::tearDown();
    }

    public function testProductionSeederPopulatesMasterDataOnly(): void
    {
        $migrator = new Migrator($this->pdo, __DIR__ . '/../../../database/migrations');
        $migrator->migrate();

        $seeder = new DatabaseSeeder($this->pdo, 'production');
        $seeder->run();

        $roles_count = $this->pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn();
        $users_count = $this->pdo->query('SELECT COUNT(*) FROM users WHERE username = "admin"')->fetchColumn();
        $categories_count = $this->pdo->query('SELECT COUNT(*) FROM room_categories')->fetchColumn();
        $stays_count = $this->pdo->query('SELECT COUNT(*) FROM stays')->fetchColumn();

        $this->assertEquals(4, (int) $roles_count);
        $this->assertEquals(1, (int) $users_count);
        $this->assertEquals(3, (int) $categories_count);
        $this->assertEquals(0, (int) $stays_count);
    }

    public function testDevelopmentSeederPopulatesAllTables(): void
    {
        $migrator = new Migrator($this->pdo, __DIR__ . '/../../../database/migrations');
        $migrator->migrate();

        $seeder = new DatabaseSeeder($this->pdo, 'development');
        $seeder->run();

        $rooms_count = $this->pdo->query('SELECT COUNT(*) FROM rooms')->fetchColumn();
        $guests_count = $this->pdo->query('SELECT COUNT(*) FROM guests')->fetchColumn();
        $stays_count = $this->pdo->query('SELECT COUNT(*) FROM stays')->fetchColumn();
        $folios_count = $this->pdo->query('SELECT COUNT(*) FROM folios')->fetchColumn();
        $products_count = $this->pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
        $expenses_count = $this->pdo->query('SELECT COUNT(*) FROM expenses')->fetchColumn();
        $invoices_count = $this->pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn();

        $this->assertGreaterThanOrEqual(5, (int) $rooms_count);
        $this->assertGreaterThanOrEqual(3, (int) $guests_count);
        $this->assertGreaterThanOrEqual(1, (int) $stays_count);
        $this->assertGreaterThanOrEqual(1, (int) $folios_count);
        $this->assertGreaterThanOrEqual(4, (int) $products_count);
        $this->assertGreaterThanOrEqual(1, (int) $expenses_count);
        $this->assertGreaterThanOrEqual(1, (int) $invoices_count);
    }

    public function testDevelopmentSeederIsBlockedInProduction(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('bloqueado en producción');

        (new DatabaseSeeder($this->pdo, 'development'))->run();
    }
}
