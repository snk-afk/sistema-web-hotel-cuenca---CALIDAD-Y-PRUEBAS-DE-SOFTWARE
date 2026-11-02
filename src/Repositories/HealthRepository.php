<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Support\Config;
use PDO;

final class HealthRepository
{
    private PDO $db;
    private string $migrations_dir;
    private string $storage_path;

    public function __construct(
        ?PDO $db = null,
        ?string $migrations_dir = null,
        ?string $storage_path = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->migrations_dir = $migrations_dir ?? dirname(__DIR__, 2) . '/database/migrations';
        $this->storage_path = $storage_path ?? Config::string(
            'storage.comprobantes_path',
            dirname(__DIR__, 2) . '/storage/comprobantes',
        );
    }

    /**
     * @return array{database: bool, migrations: bool, strict_sql_mode: bool, storage: bool}
     */
    public function check(): array
    {
        $database = $this->databaseIsAvailable();

        return [
            'database' => $database,
            'migrations' => $database && $this->migrationsAreCurrent(),
            'strict_sql_mode' => $database && $this->strictSqlModeIsActive(),
            'storage' => $this->storageIsReady(),
        ];
    }

    private function databaseIsAvailable(): bool
    {
        try {
            $statement = $this->db->query('SELECT 1');
            return $statement !== false && $statement->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function migrationsAreCurrent(): bool
    {
        try {
            $statement = $this->db->query('SELECT migration FROM migrations ORDER BY migration ASC');
            if ($statement === false) {
                return false;
            }

            /** @var array<int, string> $executed */
            $executed = $statement->fetchAll(PDO::FETCH_COLUMN);
            $files = glob($this->migrations_dir . '/*.php');
            if ($files === false) {
                return false;
            }

            $available = array_map(
                static fn(string $file): string => basename($file),
                $files,
            );
            sort($available);
            sort($executed);

            return $available !== [] && $available === $executed;
        } catch (\Throwable) {
            return false;
        }
    }

    private function strictSqlModeIsActive(): bool
    {
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return true;
        }

        try {
            $statement = $this->db->query('SELECT @@SESSION.sql_mode');
            $sql_mode = $statement !== false ? $statement->fetchColumn() : false;
            if (!is_string($sql_mode)) {
                return false;
            }

            return str_contains($sql_mode, 'STRICT_TRANS_TABLES')
                && str_contains($sql_mode, 'NO_ENGINE_SUBSTITUTION');
        } catch (\Throwable) {
            return false;
        }
    }

    private function storageIsReady(): bool
    {
        $driver = Config::string(
            'storage.driver',
            Config::string('STORAGE_DRIVER', Config::string('STORAGE_PROVIDER', 'local')),
        );
        if ($driver === 'r2') {
            return Config::string('storage.r2.account_id', Config::string('R2_ACCOUNT_ID')) !== ''
                && Config::string('storage.r2.access_key_id', Config::string('R2_ACCESS_KEY_ID')) !== ''
                && Config::string('storage.r2.secret_access_key', Config::string('R2_SECRET_ACCESS_KEY')) !== ''
                && Config::string('storage.r2.bucket_name', Config::string('R2_BUCKET_NAME')) !== '';
        }

        return is_dir($this->storage_path) && is_writable($this->storage_path);
    }
}
