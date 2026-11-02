<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\StayGuest;
use PDO;

final class StayGuestRepository
{
    private PDO $db;
    private GuestRepository $guest_repository;

    public function __construct(?PDO $db = null, ?GuestRepository $guest_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->guest_repository = $guest_repository ?? new GuestRepository($this->db);
    }

    /**
     * @return array<int, StayGuest>
     */
    public function findByStayId(int $stay_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, stay_id, guest_id, is_primary, created_at
             FROM stay_guests
             WHERE stay_id = :stay_id
             ORDER BY is_primary DESC, id ASC'
        );

        $stmt->execute(['stay_id' => $stay_id]);
        /** @var array<int, array{id: int|string, stay_id: int|string, guest_id: int|string, is_primary: int|string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): StayGuest {
                $guest = $this->guest_repository->findById((int) $row['guest_id']);
                return new StayGuest(
                    id: (int) $row['id'],
                    stay_id: (int) $row['stay_id'],
                    guest_id: (int) $row['guest_id'],
                    is_primary: (bool) $row['is_primary'],
                    created_at: $row['created_at'],
                    guest: $guest,
                );
            },
            $rows
        );
    }

    public function attachGuestToStay(int $stay_id, int $guest_id, bool $is_primary = false): StayGuest
    {
        $stmt = $this->db->prepare(
            'INSERT INTO stay_guests (stay_id, guest_id, is_primary)
             VALUES (:stay_id, :guest_id, :is_primary)'
        );

        $stmt->execute([
            'stay_id' => $stay_id,
            'guest_id' => $guest_id,
            'is_primary' => $is_primary ? 1 : 0,
        ]);

        $id = (int) $this->db->lastInsertId();
        $guest = $this->guest_repository->findById($guest_id);

        return new StayGuest(
            id: $id,
            stay_id: $stay_id,
            guest_id: $guest_id,
            is_primary: $is_primary,
            created_at: date('Y-m-d H:i:s'),
            guest: $guest,
        );
    }
}
