<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Stay;
use PDO;

final class StayRepository
{
    private PDO $db;
    private StayExtensionRepository $extension_repository;

    public function __construct(?PDO $db = null, ?StayExtensionRepository $extension_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->extension_repository = $extension_repository ?? new StayExtensionRepository($this->db);
    }

    public function findById(int $id): ?Stay
    {
        $stmt = $this->db->prepare(
            'SELECT id, room_id, room_tariff_id, guest_id, check_in_at, expected_check_out_at,
                    actual_check_out_at, base_price, applied_price, discount_reason, status,
                    created_by_user_id, created_at, updated_at
             FROM stays
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, room_id: int|string, room_tariff_id: int|string, guest_id: int|string|null, check_in_at: string, expected_check_out_at: string, actual_check_out_at: ?string, base_price: float|string, applied_price: float|string, discount_reason: ?string, status: string, created_by_user_id: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $extensions = $this->extension_repository->findByStayId((int) $row['id']);

        return new Stay(
            id: (int) $row['id'],
            room_id: (int) $row['room_id'],
            room_tariff_id: (int) $row['room_tariff_id'],
            guest_id: $row['guest_id'] !== null ? (int) $row['guest_id'] : null,
            check_in_at: $row['check_in_at'],
            expected_check_out_at: $row['expected_check_out_at'],
            actual_check_out_at: $row['actual_check_out_at'],
            base_price: (float) $row['base_price'],
            applied_price: (float) $row['applied_price'],
            discount_reason: $row['discount_reason'],
            status: $row['status'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            extensions: $extensions,
        );
    }

    public function findActiveByRoomId(int $room_id): ?Stay
    {
        $stmt = $this->db->prepare(
            'SELECT id, room_id, room_tariff_id, guest_id, check_in_at, expected_check_out_at,
                    actual_check_out_at, base_price, applied_price, discount_reason, status,
                    created_by_user_id, created_at, updated_at
             FROM stays
             WHERE room_id = :room_id AND status = "active"
             ORDER BY id DESC
             LIMIT 1'
        );

        $stmt->execute(['room_id' => $room_id]);
        /** @var array{id: int|string, room_id: int|string, room_tariff_id: int|string, guest_id: int|string|null, check_in_at: string, expected_check_out_at: string, actual_check_out_at: ?string, base_price: float|string, applied_price: float|string, discount_reason: ?string, status: string, created_by_user_id: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $extensions = $this->extension_repository->findByStayId((int) $row['id']);

        return new Stay(
            id: (int) $row['id'],
            room_id: (int) $row['room_id'],
            room_tariff_id: (int) $row['room_tariff_id'],
            guest_id: $row['guest_id'] !== null ? (int) $row['guest_id'] : null,
            check_in_at: $row['check_in_at'],
            expected_check_out_at: $row['expected_check_out_at'],
            actual_check_out_at: $row['actual_check_out_at'],
            base_price: (float) $row['base_price'],
            applied_price: (float) $row['applied_price'],
            discount_reason: $row['discount_reason'],
            status: $row['status'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            extensions: $extensions,
        );
    }

    public function create(
        int $room_id,
        int $room_tariff_id,
        string $check_in_at,
        string $expected_check_out_at,
        float $base_price,
        float $applied_price,
        ?string $discount_reason,
        int $created_by_user_id,
        ?int $guest_id = null
    ): Stay {
        $stmt = $this->db->prepare(
            'INSERT INTO stays (room_id, room_tariff_id, guest_id, check_in_at, expected_check_out_at, base_price, applied_price, discount_reason, status, created_by_user_id)
             VALUES (:room_id, :room_tariff_id, :guest_id, :check_in_at, :expected_check_out_at, :base_price, :applied_price, :discount_reason, "active", :created_by_user_id)'
        );

        $stmt->execute([
            'room_id' => $room_id,
            'room_tariff_id' => $room_tariff_id,
            'guest_id' => $guest_id,
            'check_in_at' => $check_in_at,
            'expected_check_out_at' => $expected_check_out_at,
            'base_price' => $base_price,
            'applied_price' => $applied_price,
            'discount_reason' => $discount_reason,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var Stay */
        return $this->findById($id);
    }

    public function updateExpectedCheckout(int $stay_id, string $new_expected_check_out_at): void
    {
        $stmt = $this->db->prepare(
            'UPDATE stays
             SET expected_check_out_at = :expected_check_out_at
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $stay_id,
            'expected_check_out_at' => $new_expected_check_out_at,
        ]);
    }

    public function lockById(int $stay_id): bool
    {
        $sql = 'SELECT id FROM stays WHERE id = :stay_id';
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['stay_id' => $stay_id]);

        return $statement->fetchColumn() !== false;
    }

    public function updateRoomId(int $stay_id, int $room_id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE stays
             SET room_id = :room_id
             WHERE id = :stay_id'
        );

        $stmt->execute([
            'stay_id' => $stay_id,
            'room_id' => $room_id,
        ]);
    }

    public function completeStay(int $stay_id, string $actual_check_out_at): void
    {
        $stmt = $this->db->prepare(
            'UPDATE stays
             SET status = "completed", actual_check_out_at = :actual_check_out_at
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $stay_id,
            'actual_check_out_at' => $actual_check_out_at,
        ]);
    }
}
