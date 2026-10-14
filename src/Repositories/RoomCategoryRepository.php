<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\RoomCategory;
use PDO;

final class RoomCategoryRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?RoomCategory
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, description, is_active, created_at, updated_at
             FROM room_categories
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, name: string, description: ?string, is_active: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new RoomCategory(
            id: (int) $row['id'],
            name: $row['name'],
            description: $row['description'],
            is_active: (bool) $row['is_active'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
        );
    }

    /**
     * @return array<int, RoomCategory>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, name, description, is_active, created_at, updated_at
             FROM room_categories
             ORDER BY id ASC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, name: string, description: ?string, is_active: int|string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): RoomCategory => new RoomCategory(
                id: (int) $row['id'],
                name: $row['name'],
                description: $row['description'],
                is_active: (bool) $row['is_active'],
                created_at: $row['created_at'],
                updated_at: $row['updated_at'],
            ),
            $rows
        );
    }

    public function create(string $name, ?string $description = null): RoomCategory
    {
        $stmt = $this->db->prepare(
            'INSERT INTO room_categories (name, description, is_active)
             VALUES (:name, :description, 1)'
        );

        $stmt->execute([
            'name' => $name,
            'description' => $description,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var RoomCategory */
        return $this->findById($id);
    }

    public function update(int $id, string $name, ?string $description = null, int $is_active = 1): RoomCategory
    {
        $stmt = $this->db->prepare(
            'UPDATE room_categories
             SET name = :name,
                 description = :description,
                 is_active = :is_active
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'description' => $description,
            'is_active' => $is_active,
        ]);

        /** @var RoomCategory */
        return $this->findById($id);
    }
}
