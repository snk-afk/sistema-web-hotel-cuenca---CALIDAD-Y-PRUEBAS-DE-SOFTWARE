<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Expense;
use PDO;

final class ExpenseRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?Expense
    {
        $stmt = $this->db->prepare(
            'SELECT id, cashbox_shift_id, category, concept, amount, payment_method, supplier_name, receipt_number, created_by_user_id, created_at
             FROM expenses
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, cashbox_shift_id: int|string, category: string, concept: string, amount: float|string, payment_method: string, supplier_name: ?string, receipt_number: ?string, created_by_user_id: int|string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new Expense(
            id: (int) $row['id'],
            cashbox_shift_id: (int) $row['cashbox_shift_id'],
            category: $row['category'],
            concept: $row['concept'],
            amount: (float) $row['amount'],
            payment_method: $row['payment_method'],
            supplier_name: $row['supplier_name'],
            receipt_number: $row['receipt_number'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
        );
    }

    /**
     * @return array<int, Expense>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, cashbox_shift_id, category, concept, amount, payment_method, supplier_name, receipt_number, created_by_user_id, created_at
             FROM expenses
             ORDER BY id DESC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, cashbox_shift_id: int|string, category: string, concept: string, amount: float|string, payment_method: string, supplier_name: ?string, receipt_number: ?string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): Expense => new Expense(
                id: (int) $row['id'],
                cashbox_shift_id: (int) $row['cashbox_shift_id'],
                category: $row['category'],
                concept: $row['concept'],
                amount: (float) $row['amount'],
                payment_method: $row['payment_method'],
                supplier_name: $row['supplier_name'],
                receipt_number: $row['receipt_number'],
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
        ?string $supplier_name = null,
        ?string $receipt_number = null
    ): Expense {
        $stmt = $this->db->prepare(
            'INSERT INTO expenses (cashbox_shift_id, category, concept, amount, payment_method, supplier_name, receipt_number, created_by_user_id)
             VALUES (:cashbox_shift_id, :category, :concept, :amount, :payment_method, :supplier_name, :receipt_number, :created_by_user_id)'
        );

        $stmt->execute([
            'cashbox_shift_id' => $cashbox_shift_id,
            'category' => $category,
            'concept' => $concept,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'supplier_name' => $supplier_name,
            'receipt_number' => $receipt_number,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var Expense */
        return $this->findById($id);
    }
}
