<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Permission;
use PDO;

final class PermissionRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?Permission
    {
        $stmt = $this->db->prepare(
            'SELECT id, key_name, description, created_at
             FROM permissions
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, key_name: string, description: ?string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new Permission(
            id: (int) $row['id'],
            key_name: $row['key_name'],
            description: $row['description'],
            created_at: $row['created_at'],
        );
    }

    /**
     * @return array<int, Permission>
     */
    public function findByRoleId(int $role_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.id, p.key_name, p.description, p.created_at
             FROM permissions p
             JOIN role_permissions rp ON p.id = rp.permission_id
             WHERE rp.role_id = :role_id
             ORDER BY p.key_name ASC'
        );

        $stmt->execute(['role_id' => $role_id]);
        /** @var array<int, array{id: int|string, key_name: string, description: ?string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): Permission => new Permission(
                id: (int) $row['id'],
                key_name: $row['key_name'],
                description: $row['description'],
                created_at: $row['created_at'],
            ),
            $rows
        );
    }

    public function create(string $key_name, ?string $description = null): Permission
    {
        $stmt = $this->db->prepare(
            'INSERT INTO permissions (key_name, description)
             VALUES (:key_name, :description)'
        );

        $stmt->execute([
            'key_name' => $key_name,
            'description' => $description,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var Permission */
        return $this->findById($id);
    }
}
