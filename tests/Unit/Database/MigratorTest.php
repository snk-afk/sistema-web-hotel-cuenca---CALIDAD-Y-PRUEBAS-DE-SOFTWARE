<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class MigratorTest extends TestCase
{
    private PDO $pdo;
    private string $migrations_dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->migrations_dir = sys_get_temp_dir() . '/railway_migrator_' . bin2hex(random_bytes(8));
        if (!mkdir($this->migrations_dir, 0700, true) && !is_dir($this->migrations_dir)) {
            throw new \RuntimeException('No se pudo crear el directorio temporal del migrador.');
        }

        $this->writeMigration(
            '2026_01_01_000001_create_probe.php',
            <<<'PHP'
<?php

declare(strict_types=1);

return new class {
    public function up(\PDO $db): void
    {
        $db->exec(
            'CREATE TABLE migration_probe (id INTEGER PRIMARY KEY AUTOINCREMENT, value TEXT NOT NULL)'
        );
    }
};
PHP
        );
        $this->writeMigration(
            '2026_01_01_000002_insert_probe.php',
            <<<'PHP'
<?php

declare(strict_types=1);

return new class {
    public function up(\PDO $db): void
    {
        $statement = $db->prepare('INSERT INTO migration_probe (value) VALUES (:value)');
        $statement->execute(['value' => 'ordered']);
    }
};
PHP
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->migrations_dir . '/*') ?: [] as $file_path) {
            if (is_file($file_path) && !unlink($file_path)) {
                throw new \RuntimeException('No se pudo limpiar una migración temporal.');
            }
        }

        if (!rmdir($this->migrations_dir)) {
            throw new \RuntimeException('No se pudo limpiar el directorio temporal del migrador.');
        }

        parent::tearDown();
    }

    public function testEnsureMigrationsTableAndMigrate(): void
    {
        $migrator = new Migrator($this->pdo, $this->migrations_dir);

        $migrator->ensureMigrationsTable();
        $executed = $migrator->migrate();

        $this->assertSame([
            '2026_01_01_000001_create_probe.php',
            '2026_01_01_000002_insert_probe.php',
        ], $executed);

        $value = $this->pdo->query('SELECT value FROM migration_probe ORDER BY id ASC')->fetchColumn();
        $this->assertSame('ordered', $value);

        // Running migrate again should execute 0 pending
        $second_run = $migrator->migrate();
        $this->assertCount(0, $second_run);
    }

    private function writeMigration(string $filename, string $contents): void
    {
        $path = $this->migrations_dir . '/' . $filename;
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException("No se pudo escribir la migración temporal {$filename}.");
        }
    }
}
