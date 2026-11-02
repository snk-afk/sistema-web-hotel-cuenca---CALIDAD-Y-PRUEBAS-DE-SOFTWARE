<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\StayExtension;
use PDO;

final class StayExtensionRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array<int, StayExtension>
     */
    public function findByStayId(int $stay_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, stay_id, added_hours, added_price, created_by_user_id, created_at
             FROM stay_extensions
             WHERE stay_id = :stay_id
             ORDER BY id ASC'
        );

        $stmt->execute(['stay_id' => $stay_id]);
        /** @var array<int, array{id: int|string, stay_id: int|string, added_hours: int|string, added_price: float|string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): StayExtension => new StayExtension(
                id: (int) $row['id'],
                stay_id: (int) $row['stay_id'],
                added_hours: (int) $row['added_hours'],
                added_price: (float) $row['added_price'],
                created_by_user_id: (int) $row['created_by_user_id'],
                created_at: $row['created_at'],
            ),
            $rows
        );
    }

    public function create(
        int $stay_id,
        int $added_hours,
        float $added_price,
        int $created_by_user_id
    ): StayExtension {
        $stmt = $this->db->prepare(
            'INSERT INTO stay_extensions (stay_id, added_hours, added_price, created_by_user_id)
             VALUES (:stay_id, :added_hours, :added_price, :created_by_user_id)'
        );

        $stmt->execute([
            'stay_id' => $stay_id,
            'added_hours' => $added_hours,
            'added_price' => $added_price,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();

        return new StayExtension(
            id: $id,
            stay_id: $stay_id,
            added_hours: $added_hours,
            added_price: $added_price,
            created_by_user_id: $created_by_user_id,
            created_at: date('Y-m-d H:i:s'),
        );
    }
}
