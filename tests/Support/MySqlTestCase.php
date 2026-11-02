<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Database\Database;
use App\Support\Config;
use PDO;
use PHPUnit\Framework\TestCase;

abstract class MySqlTestCase extends TestCase
{
    protected PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        Config::clear();
        Config::load(dirname(__DIR__, 2));

        $this->pdo = MySqlTestDatabase::fromConfig()->connection();
        $this->pdo->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }

        Database::setConnection(null);
        parent::tearDown();
    }

    protected function pdo(): PDO
    {
        return $this->pdo;
    }

    protected function createUserFixture(int $user_id, string $suffix): int
    {
        $role = $this->pdo->prepare(
            'INSERT INTO roles (id, name, description) VALUES (:id, :name, :description)'
        );
        $role->execute([
            'id' => $user_id,
            'name' => 'test-role-' . $suffix,
            'description' => 'Integration test role',
        ]);

        $user = $this->pdo->prepare(
            'INSERT INTO users '
            . '(id, role_id, username, email, password_hash, full_name, is_active) '
            . 'VALUES (:id, :role_id, :username, :email, :password_hash, :full_name, 1)'
        );
        $user->execute([
            'id' => $user_id,
            'role_id' => $user_id,
            'username' => 'test-user-' . $suffix,
            'email' => 'test-user-' . $suffix . '@example.test',
            'password_hash' => 'test-hash',
            'full_name' => 'Integration ' . $suffix,
        ]);

        return $user_id;
    }
}
