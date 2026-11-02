<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Support\Config;
use App\Repositories\HealthRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class HealthRepositoryTest extends TestCase
{
    private PDO $pdo;
    private string $migrations_dir;
    private string $storage_path;
    /** @var array<string, mixed> */
    private array $storage_environment = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::clear();
        foreach (['STORAGE_DRIVER', 'R2_ACCOUNT_ID', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET_NAME'] as $key) {
            $this->storage_environment[$key] = $_ENV[$key] ?? null;
            unset($_ENV[$key]);
        }
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->migrations_dir = sys_get_temp_dir() . '/cs-hotel-health-migrations-' . bin2hex(random_bytes(4));
        $this->storage_path = sys_get_temp_dir() . '/cs-hotel-health-storage-' . bin2hex(random_bytes(4));
        mkdir($this->migrations_dir, 0700, true);
        mkdir($this->storage_path, 0700, true);
        file_put_contents($this->migrations_dir . '/001_probe.php', '<?php');
        $this->pdo->exec(
            "CREATE TABLE migrations (id INTEGER PRIMARY KEY AUTOINCREMENT, migration TEXT NOT NULL UNIQUE, batch INTEGER NOT NULL, executed_at TEXT DEFAULT CURRENT_TIMESTAMP)"
        );
        $this->pdo->exec("INSERT INTO migrations (migration, batch) VALUES ('001_probe.php', 1)");
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrations_dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->migrations_dir);
        rmdir($this->storage_path);
        foreach ($this->storage_environment as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }
        Config::clear();
        parent::tearDown();
    }

    public function testReportsAllReadinessDependencies(): void
    {
        $checks = (new HealthRepository($this->pdo, $this->migrations_dir, $this->storage_path))->check();

        self::assertSame([
            'database' => true,
            'migrations' => true,
            'strict_sql_mode' => true,
            'storage' => true,
        ], $checks);
    }

    public function testDependencyFailureIsReportedWithoutExceptionDetails(): void
    {
        $this->pdo->exec('DROP TABLE migrations');

        $checks = (new HealthRepository($this->pdo, $this->migrations_dir, $this->storage_path))->check();

        self::assertFalse($checks['migrations']);
        self::assertTrue($checks['database']);
    }

    public function testR2ReadinessDoesNotRequirePublicObjectUrls(): void
    {
        $_ENV['STORAGE_DRIVER'] = 'r2';
        $_ENV['R2_ACCOUNT_ID'] = 'account';
        $_ENV['R2_ACCESS_KEY_ID'] = 'access';
        $_ENV['R2_SECRET_ACCESS_KEY'] = 'secret';
        $_ENV['R2_BUCKET_NAME'] = 'bucket';
        Config::load(dirname(__DIR__, 3));

        $checks = (new HealthRepository($this->pdo, $this->migrations_dir, $this->storage_path))->check();

        self::assertTrue($checks['storage']);
    }
}
