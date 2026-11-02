<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\FolioCharge;
use PDO;

final class FolioChargeRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?FolioCharge
    {
        $stmt = $this->db->prepare(
            'SELECT id, folio_id, charge_type, description, quantity, unit_price, total_amount, is_reversal, reversal_reason, created_by_user_id, created_at
             FROM folio_charges
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, folio_id: int|string, charge_type: string, description: string, quantity: int|string, unit_price: float|string, total_amount: float|string, is_reversal: int|string, reversal_reason: ?string, created_by_user_id: int|string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new FolioCharge(
            id: (int) $row['id'],
            folio_id: (int) $row['folio_id'],
            charge_type: $row['charge_type'],
            description: $row['description'],
            quantity: (int) $row['quantity'],
            unit_price: (float) $row['unit_price'],
            total_amount: (float) $row['total_amount'],
            is_reversal: (bool) $row['is_reversal'],
            reversal_reason: $row['reversal_reason'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
        );
    }

    /**
     * @return array<int, FolioCharge>
     */
    public function findByFolioId(int $folio_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, folio_id, charge_type, description, quantity, unit_price, total_amount, is_reversal, reversal_reason, created_by_user_id, created_at
             FROM folio_charges
             WHERE folio_id = :folio_id
             ORDER BY id ASC'
        );

        $stmt->execute(['folio_id' => $folio_id]);
        /** @var array<int, array{id: int|string, folio_id: int|string, charge_type: string, description: string, quantity: int|string, unit_price: float|string, total_amount: float|string, is_reversal: int|string, reversal_reason: ?string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): FolioCharge => new FolioCharge(
                id: (int) $row['id'],
                folio_id: (int) $row['folio_id'],
                charge_type: $row['charge_type'],
                description: $row['description'],
                quantity: (int) $row['quantity'],
                unit_price: (float) $row['unit_price'],
                total_amount: (float) $row['total_amount'],
                is_reversal: (bool) $row['is_reversal'],
                reversal_reason: $row['reversal_reason'],
                created_by_user_id: (int) $row['created_by_user_id'],
                created_at: $row['created_at'],
            ),
            $rows
        );
    }

    public function create(
        int $folio_id,
        string $charge_type,
        string $description,
        int $quantity,
        float $unit_price,
        float $total_amount,
        int $created_by_user_id,
        bool $is_reversal = false,
        ?string $reversal_reason = null
    ): FolioCharge {
        $stmt = $this->db->prepare(
            'INSERT INTO folio_charges (folio_id, charge_type, description, quantity, unit_price, total_amount, is_reversal, reversal_reason, created_by_user_id)
             VALUES (:folio_id, :charge_type, :description, :quantity, :unit_price, :total_amount, :is_reversal, :reversal_reason, :created_by_user_id)'
        );

        $stmt->execute([
            'folio_id' => $folio_id,
            'charge_type' => $charge_type,
            'description' => $description,
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'total_amount' => $total_amount,
            'is_reversal' => $is_reversal ? 1 : 0,
            'reversal_reason' => $reversal_reason,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var FolioCharge */
        return $this->findById($id);
    }
}
