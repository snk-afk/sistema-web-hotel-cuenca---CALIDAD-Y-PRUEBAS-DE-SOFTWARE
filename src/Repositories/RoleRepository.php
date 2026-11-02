<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Role;
use PDO;

final class RoleRepository
{
    private PDO $db;
    private PermissionRepository $permission_repository;

    public function __construct(?PDO $db = null, ?PermissionRepository $permission_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->permission_repository = $permission_repository ?? new PermissionRepository($this->db);
    }

    public function findById(int $id): ?Role
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, created_at
             FROM roles
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, name: string, description: ?string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $permissions = $this->permission_repository->findByRoleId((int) $row['id']);

        return new Role(
            id: (int) $row['id'],
            name: $row['name'],
            description: $row['description'],
            created_at: $row['created_at'],
            permissions: $permissions,
        );
    }

    public function findByName(string $name): ?Role
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, created_at
             FROM roles
             WHERE name = :name'
        );

        $stmt->execute(['name' => $name]);
        /** @var array{id: int|string, name: string, description: ?string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->findById((int) $row['id']);
    }

    public function create(string $name, ?string $description = null): Role
    {
        $stmt = $this->db->prepare(
            'INSERT INTO roles (name, description)
             VALUES (:name, :description)'
        );

        $stmt->execute([
            'name' => $name,
            'description' => $description,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var Role */
        return $this->findById($id);
    }

    public function attachPermission(int $role_id, int $permission_id): void
    {
        $check_stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM role_permissions
             WHERE role_id = :role_id AND permission_id = :permission_id'
        );
        $check_stmt->execute([
            'role_id' => $role_id,
            'permission_id' => $permission_id,
        ]);

        /** @var int|string $count */
        $count = $check_stmt->fetchColumn();
        if ((int) $count > 0) {
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO role_permissions (role_id, permission_id)
             VALUES (:role_id, :permission_id)'
        );

        $stmt->execute([
            'role_id' => $role_id,
            'permission_id' => $permission_id,
        ]);
    }
}
