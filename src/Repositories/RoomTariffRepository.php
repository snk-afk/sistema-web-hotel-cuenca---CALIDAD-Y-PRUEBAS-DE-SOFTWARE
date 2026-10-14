<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\RoomTariff;
use PDO;

final class RoomTariffRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?RoomTariff
    {
        $stmt = $this->db->prepare(
            'SELECT id, room_category_id, name, duration_hours, price, is_active, created_at, updated_at
             FROM room_tariffs
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, room_category_id: int|string, name: string, duration_hours: int|string, price: float|string, is_active: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new RoomTariff(
            id: (int) $row['id'],
            room_category_id: (int) $row['room_category_id'],
            name: $row['name'],
            duration_hours: (int) $row['duration_hours'],
            price: (float) $row['price'],
            is_active: (bool) $row['is_active'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
        );
    }

    /**
     * @return array<int, RoomTariff>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, room_category_id, name, duration_hours, price, is_active, created_at, updated_at
             FROM room_tariffs
             WHERE is_active = 1
             ORDER BY room_category_id ASC, id ASC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, room_category_id: int|string, name: string, duration_hours: int|string, price: float|string, is_active: int|string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): RoomTariff => new RoomTariff(
                id: (int) $row['id'],
                room_category_id: (int) $row['room_category_id'],
                name: $row['name'],
                duration_hours: (int) $row['duration_hours'],
                price: (float) $row['price'],
                is_active: (bool) $row['is_active'],
                created_at: $row['created_at'],
                updated_at: $row['updated_at'],
            ),
            $rows
        );
    }

    /**
     * @return array<int, RoomTariff>
     */
    public function findByCategoryId(int $category_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, room_category_id, name, duration_hours, price, is_active, created_at, updated_at
             FROM room_tariffs
             WHERE room_category_id = :category_id AND is_active = 1
             ORDER BY id ASC'
        );

        $stmt->execute(['category_id' => $category_id]);
        /** @var array<int, array{id: int|string, room_category_id: int|string, name: string, duration_hours: int|string, price: float|string, is_active: int|string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): RoomTariff => new RoomTariff(
                id: (int) $row['id'],
                room_category_id: (int) $row['room_category_id'],
                name: $row['name'],
                duration_hours: (int) $row['duration_hours'],
                price: (float) $row['price'],
                is_active: (bool) $row['is_active'],
                created_at: $row['created_at'],
                updated_at: $row['updated_at'],
            ),
            $rows
        );
    }

    public function create(
        int $room_category_id,
        string $name,
        int $duration_hours,
        float $price
    ): RoomTariff {
        $stmt = $this->db->prepare(
            'INSERT INTO room_tariffs (room_category_id, name, duration_hours, price, is_active)
             VALUES (:room_category_id, :name, :duration_hours, :price, 1)'
        );

        $stmt->execute([
            'room_category_id' => $room_category_id,
            'name' => $name,
            'duration_hours' => $duration_hours,
            'price' => $price,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var RoomTariff */
        return $this->findById($id);
    }

    public function update(
        int $id,
        int $room_category_id,
        string $name,
        int $duration_hours,
        float $price,
        int $is_active = 1
    ): RoomTariff {
        $stmt = $this->db->prepare(
            'UPDATE room_tariffs
             SET room_category_id = :room_category_id,
                 name = :name,
                 duration_hours = :duration_hours,
                 price = :price,
                 is_active = :is_active
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'room_category_id' => $room_category_id,
            'name' => $name,
            'duration_hours' => $duration_hours,
            'price' => $price,
            'is_active' => $is_active,
        ]);

        /** @var RoomTariff */
        return $this->findById($id);
    }
}
