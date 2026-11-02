<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\OtherIncome;
use PDO;

final class OtherIncomeRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?OtherIncome
    {
        $stmt = $this->db->prepare(
            'SELECT id, cashbox_shift_id, category, concept, amount, payment_method, notes, created_by_user_id, created_at
             FROM other_incomes
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, cashbox_shift_id: int|string, category: string, concept: string, amount: float|string, payment_method: string, notes: ?string, created_by_user_id: int|string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new OtherIncome(
            id: (int) $row['id'],
            cashbox_shift_id: (int) $row['cashbox_shift_id'],
            category: $row['category'],
            concept: $row['concept'],
            amount: (float) $row['amount'],
            payment_method: $row['payment_method'],
            notes: $row['notes'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
        );
    }

    /**
     * @return array<int, OtherIncome>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, cashbox_shift_id, category, concept, amount, payment_method, notes, created_by_user_id, created_at
             FROM other_incomes
             ORDER BY id DESC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, cashbox_shift_id: int|string, category: string, concept: string, amount: float|string, payment_method: string, notes: ?string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): OtherIncome => new OtherIncome(
                id: (int) $row['id'],
                cashbox_shift_id: (int) $row['cashbox_shift_id'],
                category: $row['category'],
                concept: $row['concept'],
                amount: (float) $row['amount'],
                payment_method: $row['payment_method'],
                notes: $row['notes'],
                created_by_user_id: (int) $row['created_by_user_id'],
                created_at: $row['created_at'],
            ),
            $rows
        );
    }

    public function create(
        int $cashbox_shift_id,
        string $category,
        string $concept,
        float $amount,
        string $payment_method,
        int $created_by_user_id,
        ?string $notes = null
    ): OtherIncome {
        $stmt = $this->db->prepare(
            'INSERT INTO other_incomes (cashbox_shift_id, category, concept, amount, payment_method, notes, created_by_user_id)
             VALUES (:cashbox_shift_id, :category, :concept, :amount, :payment_method, :notes, :created_by_user_id)'
        );

        $stmt->execute([
            'cashbox_shift_id' => $cashbox_shift_id,
            'category' => $category,
            'concept' => $concept,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'notes' => $notes,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var OtherIncome */
        return $this->findById($id);
    }
}
