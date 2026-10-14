<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\CashMovement;
use PDO;

final class CashMovementRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?CashMovement
    {
        $stmt = $this->db->prepare(
            'SELECT id, cashbox_shift_id, type, category, concept, amount, payment_method, reference_id, reference_type, created_by_user_id, created_at
             FROM cash_movements
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, cashbox_shift_id: int|string, type: string, category: string, concept: string, amount: float|string, payment_method: string, reference_id: int|string|null, reference_type: ?string, created_by_user_id: int|string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new CashMovement(
            id: (int) $row['id'],
            cashbox_shift_id: (int) $row['cashbox_shift_id'],
            type: $row['type'],
            category: $row['category'],
            concept: $row['concept'],
            amount: (float) $row['amount'],
            payment_method: $row['payment_method'],
            reference_id: $row['reference_id'] !== null ? (int) $row['reference_id'] : null,
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            reference_type: $row['reference_type'],
        );
    }

    /**
     * @return array<int, CashMovement>
     */
    public function findByShiftId(int $shift_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, cashbox_shift_id, type, category, concept, amount, payment_method, reference_id, reference_type, created_by_user_id, created_at
             FROM cash_movements
             WHERE cashbox_shift_id = :shift_id
             ORDER BY id ASC'
        );

        $stmt->execute(['shift_id' => $shift_id]);
        /** @var array<int, array{id: int|string, cashbox_shift_id: int|string, type: string, category: string, concept: string, amount: float|string, payment_method: string, reference_id: int|string|null, reference_type: ?string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): CashMovement => new CashMovement(
                id: (int) $row['id'],
                cashbox_shift_id: (int) $row['cashbox_shift_id'],
                type: $row['type'],
                category: $row['category'],
                concept: $row['concept'],
                amount: (float) $row['amount'],
                payment_method: $row['payment_method'],
                reference_id: $row['reference_id'] !== null ? (int) $row['reference_id'] : null,
                created_by_user_id: (int) $row['created_by_user_id'],
                created_at: $row['created_at'],
                reference_type: $row['reference_type'],
            ),
            $rows
        );
    }

    public function create(
        int $cashbox_shift_id,
        string $type,
        string $category,
        string $concept,
        float $amount,
        string $payment_method,
        int $created_by_user_id,
        ?int $reference_id = null,
        ?string $reference_type = null
    ): CashMovement {
        $stmt = $this->db->prepare(
            'INSERT INTO cash_movements (cashbox_shift_id, type, category, concept, amount, payment_method, reference_id, reference_type, created_by_user_id)
             VALUES (:cashbox_shift_id, :type, :category, :concept, :amount, :payment_method, :reference_id, :reference_type, :created_by_user_id)'
        );

        $stmt->execute([
            'cashbox_shift_id' => $cashbox_shift_id,
            'type' => $type,
            'category' => $category,
            'concept' => $concept,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'reference_id' => $reference_id,
            'reference_type' => $reference_type,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var CashMovement */
        return $this->findById($id);
    }
}
