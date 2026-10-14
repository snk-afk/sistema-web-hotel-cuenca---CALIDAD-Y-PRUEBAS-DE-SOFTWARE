<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Room;
use PDO;

final class RoomRepository
{
    private PDO $db;
    private RoomCategoryRepository $category_repository;
    private StayRepository $stay_repository;

    public function __construct(
        ?PDO $db = null,
        ?RoomCategoryRepository $category_repository = null,
        ?StayRepository $stay_repository = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->category_repository = $category_repository ?? new RoomCategoryRepository($this->db);
        $this->stay_repository = $stay_repository ?? new StayRepository($this->db);
    }

    public function findById(int $id): ?Room
    {
        $stmt = $this->db->prepare(
            'SELECT id, room_category_id, number, status, floor, description, created_at, updated_at
             FROM rooms
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, room_category_id: int|string, number: string, status: string, floor: ?string, description: ?string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $category = $this->category_repository->findById((int) $row['room_category_id']);
        $current_stay = $row['status'] === 'occupied' ? $this->stay_repository->findActiveByRoomId((int) $row['id']) : null;

        return new Room(
            id: (int) $row['id'],
            room_category_id: (int) $row['room_category_id'],
            number: $row['number'],
            status: $row['status'],
            floor: $row['floor'],
            description: $row['description'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            category: $category,
            current_stay: $current_stay,
        );
    }

    public function lockById(int $id): bool
    {
        $sql = 'SELECT id FROM rooms WHERE id = :id';
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    public function findByNumber(string $number): ?Room
    {
        $stmt = $this->db->prepare(
            'SELECT id, room_category_id, number, status, floor, description, created_at, updated_at
             FROM rooms
             WHERE number = :number'
        );

        $stmt->execute(['number' => $number]);
        /** @var array{id: int|string, room_category_id: int|string, number: string, status: string, floor: ?string, description: ?string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $category = $this->category_repository->findById((int) $row['room_category_id']);
        $current_stay = $row['status'] === 'occupied' ? $this->stay_repository->findActiveByRoomId((int) $row['id']) : null;

        return new Room(
            id: (int) $row['id'],
            room_category_id: (int) $row['room_category_id'],
            number: $row['number'],
            status: $row['status'],
            floor: $row['floor'],
            description: $row['description'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            category: $category,
            current_stay: $current_stay,
        );
    }

    /**
     * @return array<int, Room>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, room_category_id, number, status, floor, description, created_at, updated_at
             FROM rooms
             ORDER BY number ASC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, room_category_id: int|string, number: string, status: string, floor: ?string, description: ?string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): Room {
                $category = $this->category_repository->findById((int) $row['room_category_id']);
                $current_stay = $row['status'] === 'occupied' ? $this->stay_repository->findActiveByRoomId((int) $row['id']) : null;

                return new Room(
                    id: (int) $row['id'],
                    room_category_id: (int) $row['room_category_id'],
                    number: $row['number'],
                    status: $row['status'],
                    floor: $row['floor'],
                    description: $row['description'],
                    created_at: $row['created_at'],
                    updated_at: $row['updated_at'],
                    category: $category,
                    current_stay: $current_stay,
                );
            },
            $rows
        );
    }

    public function create(
        int $room_category_id,
        string $number,
        ?string $floor = null,
        ?string $description = null
    ): Room {
        $stmt = $this->db->prepare(
            'INSERT INTO rooms (room_category_id, number, status, floor, description)
             VALUES (:room_category_id, :number, "free", :floor, :description)'
        );

        $stmt->execute([
            'room_category_id' => $room_category_id,
            'number' => $number,
            'floor' => $floor,
            'description' => $description,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var Room */
        return $this->findById($id);
    }

    public function update(
        int $id,
        int $room_category_id,
        string $number,
        ?string $floor = null,
        ?string $description = null
    ): Room {
        $stmt = $this->db->prepare(
            'UPDATE rooms
             SET room_category_id = :room_category_id,
                 number = :number,
                 floor = :floor,
                 description = :description
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'room_category_id' => $room_category_id,
            'number' => $number,
            'floor' => $floor,
            'description' => $description,
        ]);

        /** @var Room */
        return $this->findById($id);
    }

    public function updateStatus(int $room_id, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE rooms
             SET status = :status
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $room_id,
            'status' => $status,
        ]);
    }
}
