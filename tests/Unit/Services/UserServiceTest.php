<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE roles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE permissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                key_name TEXT NOT NULL UNIQUE,
                description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE role_permissions (
                role_id INTEGER NOT NULL,
                permission_id INTEGER NOT NULL,
                PRIMARY KEY (role_id, permission_id)
            );

            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                role_id INTEGER NOT NULL,
                username TEXT NOT NULL UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password_hash TEXT NOT NULL,
                full_name TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function testCreateUserAndAuthenticateSuccess(): void
    {
        $role_repo = new RoleRepository($this->pdo);
        $role = $role_repo->create('recepcion', 'Recepcionista del hotel');

        $user_service = new UserService($this->pdo);
        $user = $user_service->createUser(
            role_id: $role->id,
            username: 'maria',
            email: 'maria@hotel.com',
            password: 'SecretPassword123',
            full_name: 'María Delgado'
        );

        $this->assertEquals('maria', $user->username);
        $this->assertTrue(password_verify('SecretPassword123', $user->password_hash));

        $authenticated = $user_service->authenticate('maria', 'SecretPassword123');
        $this->assertEquals($user->id, $authenticated->id);
    }

    public function testAuthenticateThrowsExceptionForWrongPassword(): void
    {
        $role_repo = new RoleRepository($this->pdo);
        $role = $role_repo->create('admin', 'Administrador');

        $user_service = new UserService($this->pdo);
        $user_service->createUser(
            role_id: $role->id,
            username: 'admin',
            email: 'admin@hotel.com',
            password: 'AdminPassword123',
            full_name: 'Administrador Principal'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inválidas');

        $user_service->authenticate('admin', 'WrongPassword');
    }
}
