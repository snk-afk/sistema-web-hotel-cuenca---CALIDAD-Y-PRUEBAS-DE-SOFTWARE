<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\RoomTransfer;
use PDO;

final class RoomTransferRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array<int, RoomTransfer>
     */
    public function findByStayId(int $stay_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, stay_id, from_room_id, to_room_id, reason, created_by_user_id, created_at
             FROM room_transfers
             WHERE stay_id = :stay_id
             ORDER BY id ASC'
        );

        $stmt->execute(['stay_id' => $stay_id]);
        /** @var array<int, array{id: int|string, stay_id: int|string, from_room_id: int|string, to_room_id: int|string, reason: string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): RoomTransfer => new RoomTransfer(
                id: (int) $row['id'],
                stay_id: (int) $row['stay_id'],
                from_room_id: (int) $row['from_room_id'],
                to_room_id: (int) $row['to_room_id'],
                reason: $row['reason'],
                created_by_user_id: (int) $row['created_by_user_id'],
                created_at: $row['created_at'],
            ),
            $rows
        );
    }

    public function create(
        int $stay_id,
        int $from_room_id,
        int $to_room_id,
        string $reason,
        int $created_by_user_id
    ): RoomTransfer {
        $stmt = $this->db->prepare(
            'INSERT INTO room_transfers (stay_id, from_room_id, to_room_id, reason, created_by_user_id)
             VALUES (:stay_id, :from_room_id, :to_room_id, :reason, :created_by_user_id)'
        );

        $stmt->execute([
            'stay_id' => $stay_id,
            'from_room_id' => $from_room_id,
            'to_room_id' => $to_room_id,
            'reason' => $reason,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();

        return new RoomTransfer(
            id: $id,
            stay_id: $stay_id,
            from_room_id: $from_room_id,
            to_room_id: $to_room_id,
            reason: $reason,
            created_by_user_id: $created_by_user_id,
            created_at: date('Y-m-d H:i:s'),
        );
    }
}
