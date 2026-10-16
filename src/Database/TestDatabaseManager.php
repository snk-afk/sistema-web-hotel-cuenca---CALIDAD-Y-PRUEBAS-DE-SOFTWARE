<?php

declare(strict_types=1);

namespace App\Database;

use App\Support\Config;
use PDO;
use RuntimeException;

final class TestDatabaseManager
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username,
        private readonly string $password,
        private readonly string $database_name,
        private readonly string $environment,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            host: Config::string('database.host', '127.0.0.1'),
            port: Config::int('database.port', 3306),
            username: Config::string('database.username', 'root'),
            password: Config::string('database.password', ''),
            database_name: Config::string('database.test_database', 'railway_test'),
            environment: Config::string('app.env', 'production'),
        );
    }

    public function assertSafeEnvironment(string $environment): void
    {
        if ($environment !== 'testing' || $this->environment !== 'testing') {
            throw new RuntimeException('La reconstrucción de la base de pruebas requiere APP_ENV=testing.');
        }

        if (preg_match('/\\Arailway_test(?:_[A-Za-z0-9_]+)?\\z/', $this->database_name) !== 1) {
            throw new RuntimeException('La base configurada no pertenece al conjunto seguro de pruebas.');
        }
    }

    public function recreate(string $migrations_dir): void
    {
        $this->assertSafeEnvironment($this->environment);

        $server = $this->createServerConnection();
        $quoted_database = $this->quotedDatabaseName();

        $server->exec("DROP DATABASE IF EXISTS {$quoted_database}");
        $server->exec(
            "CREATE DATABASE {$quoted_database} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
        );

        $this->connection = $this->createDatabaseConnection();
        $migrator = new Migrator($this->connection, $migrations_dir);
        $migrator->migrate();

        $this->assertMigrationsTableExists();
    }

    public function connection(): PDO
    {
        $this->assertSafeEnvironment($this->environment);

        if ($this->connection === null) {
            $this->connection = $this->createDatabaseConnection();
        }

        return $this->connection;
    }

    private function createServerConnection(): PDO
    {
        $dsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $this->host, $this->port);

        return new PDO($dsn, $this->username, $this->password, $this->pdoOptions());
    }

    private function createDatabaseConnection(): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $this->host,
            $this->port,
            $this->database_name,
        );

        $connection = new PDO($dsn, $this->username, $this->password, $this->pdoOptions());
        $connection->exec("SET sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'");

        return $connection;
    }

    /**
     * @return array<int, mixed>
     */
    private function pdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
    }

    private function quotedDatabaseName(): string
    {
        if (preg_match('/\\Arailway_test(?:_[A-Za-z0-9_]+)?\\z/', $this->database_name) !== 1) {
            throw new RuntimeException('La base configurada no pertenece al conjunto seguro de pruebas.');
        }

        return '`' . $this->database_name . '`';
    }

    private function assertMigrationsTableExists(): void
    {
        $connection = $this->connection();
        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = :database_name AND table_name = :table_name'
        );
        $statement->execute([
            'database_name' => $this->database_name,
            'table_name' => 'migrations',
        ]);

        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException('La base de pruebas no contiene la tabla de migraciones.');
        }
    }
}
