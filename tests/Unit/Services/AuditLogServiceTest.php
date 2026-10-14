<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\AuditLogService;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuditLogServiceTest extends TestCase
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

            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                entity_name TEXT NOT NULL,
                entity_id INTEGER NOT NULL,
                old_values TEXT,
                new_values TEXT,
                ip_address TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function testLogActionStoresAuditRecord(): void
    {
        $role_repo = new RoleRepository($this->pdo);
        $role = $role_repo->create('admin', 'Administrador');

        $user_repo = new UserRepository($this->pdo, $role_repo);
        $user = $user_repo->create($role->id, 'admin', 'admin@hotel.com', 'hash', 'Admin');

        $audit_service = new AuditLogService($this->pdo);

        $log = $audit_service->logAction(
            user_id: $user->id,
            action: 'REVERSE_CHARGE',
            entity_name: 'folio_charges',
            entity_id: 15,
            old_values: ['amount' => 50.00],
            new_values: ['amount' => -50.00],
            ip_address: '192.168.1.50'
        );

        $this->assertEquals('REVERSE_CHARGE', $log->action);
        $this->assertEquals('folio_charges', $log->entity_name);
        $this->assertEquals(15, $log->entity_id);
    }
}
