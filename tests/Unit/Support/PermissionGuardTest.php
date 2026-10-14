<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Support\PermissionGuard;
use PDO;
use PHPUnit\Framework\TestCase;

final class PermissionGuardTest extends TestCase
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

    public function testAdminHasAllPermissions(): void
    {
        $role_repo = new RoleRepository($this->pdo);
        $admin_role = $role_repo->create('admin', 'Administrador');

        $user_repo = new UserRepository($this->pdo, $role_repo);
        $admin_user = $user_repo->create($admin_role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin User');

        $this->assertTrue(PermissionGuard::can($admin_user, 'any.permission.key'));
    }

    public function testReceptionistHasAssignedPermissionsOnly(): void
    {
        $role_repo = new RoleRepository($this->pdo);
        $perm_repo = new PermissionRepository($this->pdo);

        $reception_role = $role_repo->create('receptionist', 'Recepcionista');
        $occupy_perm = $perm_repo->create('rooms.occupy', 'Hospedar Habitación');
        $reverse_perm = $perm_repo->create('folios.reverse', 'Revertir Cargos');

        $role_repo->attachPermission($reception_role->id, $occupy_perm->id);

        $user_repo = new UserRepository($this->pdo, $role_repo);
        $receptionist = $user_repo->create($reception_role->id, 'recep1', 'recep1@hotel.com', 'hash', 'Recep');

        $this->assertTrue(PermissionGuard::can($receptionist, 'rooms.occupy'));
        $this->assertFalse(PermissionGuard::can($receptionist, 'folios.reverse'));
    }
}
