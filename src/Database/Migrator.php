<?php

declare(strict_types=1);

namespace App\Database;

use PDO;
use RuntimeException;

final class Migrator
{
    private PDO $db;
    private string $migrations_dir;
    private bool $lock_held = false;
    private const string MIGRATION_LOCK_NAME = 'cs_hotel_migrations';

    public function __construct(?PDO $db = null, ?string $migrations_dir = null)
    {
        $this->db = $db ?? Database::connection();
        $this->migrations_dir = $migrations_dir ?? __DIR__ . '/../../database/migrations';
    }

    public function ensureMigrationsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT NOT NULL,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        // SQLite compatibility fallback
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $sql = 'CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                executed_at TEXT DEFAULT CURRENT_TIMESTAMP
            )';
        }

        $this->db->exec($sql);
    }

    /**
     * @return array<int, string>
     */
    public function getExecutedMigrations(): array
    {
        $this->ensureMigrationsTable();
        $stmt = $this->db->query('SELECT migration FROM migrations ORDER BY id ASC');

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, string> $migrations */
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return $migrations;
    }

    public function getNextBatchNumber(): int
    {
        $this->ensureMigrationsTable();
        $stmt = $this->db->query('SELECT MAX(batch) FROM migrations');

        /** @var int|string|false $max */
        $max = $stmt !== false ? $stmt->fetchColumn() : 0;
        return ((int) $max) + 1;
    }

    public function acquireLock(): bool
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return true;
        }

        $statement = $this->db->prepare('SELECT GET_LOCK(:lock_name, :timeout_seconds)');
        $statement->bindValue('lock_name', self::MIGRATION_LOCK_NAME, PDO::PARAM_STR);
        $statement->bindValue('timeout_seconds', 30, PDO::PARAM_INT);
        $statement->execute();

        if ((int) $statement->fetchColumn() !== 1) {
            return false;
        }

        $this->lock_held = true;
        return true;
    }

    public function releaseLock(): void
    {
        if (!$this->lock_held) {
            return;
        }

        $statement = $this->db->prepare('SELECT RELEASE_LOCK(:lock_name)');
        $statement->execute(['lock_name' => self::MIGRATION_LOCK_NAME]);
        $this->lock_held = false;
    }

    /**
     * @return array{driver: string, held: bool}|array{}
     */
    public function getLockState(): array
    {
        if (!$this->lock_held) {
            return [];
        }

        $driver_value = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $driver = is_string($driver_value) ? $driver_value : 'unknown';
        return ['driver' => $driver, 'held' => true];
    }

    /**
     * @return array<int, string>
     */
    public function migrate(): array
    {
        if (!$this->acquireLock()) {
            throw new RuntimeException('No se pudo adquirir el bloqueo de migraciones.');
        }

        try {
            return $this->migrateWithoutLock();
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * @return array<int, string>
     */
    private function migrateWithoutLock(): array
    {
        $this->ensureMigrationsTable();
        $executed = $this->getExecutedMigrations();
        $batch = $this->getNextBatchNumber();

        $all_files = glob($this->migrations_dir . '/*.php');
        if ($all_files === false) {
            $all_files = [];
        }
        sort($all_files);

        $executed_now = [];

        foreach ($all_files as $file_path) {
            $filename = basename($file_path);

            if (in_array($filename, $executed, true)) {
                continue;
            }

            /** @var mixed $migration_obj */
            $migration_obj = require $file_path;

            if (is_object($migration_obj) && method_exists($migration_obj, 'up')) {
                $is_nested = $this->db->inTransaction();
                if (!$is_nested) {
                    $this->db->beginTransaction();
                }

                try {
                    $migration_obj->up($this->db);

                    $stmt = $this->db->prepare(
                        'INSERT INTO migrations (migration, batch) VALUES (:migration, :batch)'
                    );
                    $stmt->execute([
                        'migration' => $filename,
                        'batch' => $batch,
                    ]);

                    if (!$is_nested && $this->db->inTransaction()) {
                        $this->db->commit();
                    }

                    $executed_now[] = $filename;
                } catch (\Throwable $e) {
                    if (!$is_nested && $this->db->inTransaction()) {
                        $this->db->rollBack();
                    }
                    throw $e;
                }
            }
        }

        return $executed_now;
    }
}
