<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\TestDatabaseManager;
use PDO;
use RuntimeException;

final class MySqlTestDatabase
{
    public function __construct(
        private readonly TestDatabaseManager $manager,
        private readonly string $environment,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            manager: TestDatabaseManager::fromConfig(),
            environment: \App\Support\Config::string('app.env', 'production'),
        );
    }

    public function connection(): PDO
    {
        $this->manager->assertSafeEnvironment($this->environment);
        $connection = $this->manager->connection();

        $statement = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = :table_name'
        );
        $statement->execute(['table_name' => 'migrations']);

        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException(
                'La base de pruebas no está preparada. Ejecute make db-test-reset antes de la suite.'
            );
        }

        return $connection;
    }
}
