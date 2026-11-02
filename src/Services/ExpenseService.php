<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\Expense;
use App\Models\OtherIncome;
use App\Repositories\ExpenseRepository;
use App\Repositories\OtherIncomeRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class ExpenseService
{
    /** @var list<string> */
    private const PAYMENT_METHODS = ['cash', 'yape', 'plin', 'card', 'transfer'];

    private PDO $db;
    private ExpenseRepository $expense_repository;
    private OtherIncomeRepository $income_repository;
    private CashboxService $cashbox_service;

    public function __construct(
        ?PDO $db = null,
        ?ExpenseRepository $expense_repository = null,
        ?OtherIncomeRepository $income_repository = null,
        ?CashboxService $cashbox_service = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->expense_repository = $expense_repository ?? new ExpenseRepository($this->db);
        $this->income_repository = $income_repository ?? new OtherIncomeRepository($this->db);
        $this->cashbox_service = $cashbox_service ?? new CashboxService($this->db);
    }

    public function recordExpense(
        int $user_id,
        string $category,
        string $concept,
        float $amount,
        string $payment_method = 'cash',
        ?string $supplier_name = null,
        ?string $receipt_number = null
    ): Expense {
        if (!$this->isValidMoneyAmount($amount)) {
            throw new InvalidArgumentException("El monto del egreso debe ser mayor a cero y tener como máximo dos decimales.");
        }

        if (trim($concept) === '') {
            throw new InvalidArgumentException("Debe ingresar la justificación/concepto del egreso.");
        }

        if (!in_array($payment_method, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Método de pago inválido.');
        }

        $shift = $this->cashbox_service->getCurrentShift($user_id);
        if ($shift === null) {
            throw new DomainException("Debe abrir un turno de caja antes de registrar egresos.");
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            $expense = $this->expense_repository->create(
                cashbox_shift_id: $shift->id,
                category: $category,
                concept: $concept,
                amount: $amount,
                payment_method: $payment_method,
                created_by_user_id: $user_id,
                supplier_name: $supplier_name,
                receipt_number: $receipt_number
            );

            $this->cashbox_service->recordMovement(
                user_id: $user_id,
                type: 'expense',
                category: 'petty_expense',
                concept: "Egreso: {$concept}",
                amount: $amount,
                payment_method: $payment_method,
                reference_id: $expense->id,
                reference_type: 'expense'
            );

            if ($owns_transaction) {
                $this->db->commit();
            }
            return $expense;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, Expense>
     */
    public function listExpenses(): array
    {
        return $this->expense_repository->findAll();
    }

    public function recordOtherIncome(
        int $user_id,
        string $category,
        string $concept,
        float $amount,
        string $payment_method = 'cash',
        ?string $notes = null
    ): OtherIncome {
        if (!$this->isValidMoneyAmount($amount)) {
            throw new InvalidArgumentException("El monto del ingreso debe ser mayor a cero y tener como máximo dos decimales.");
        }

        if (trim($concept) === '') {
            throw new InvalidArgumentException("Debe ingresar el concepto del ingreso.");
        }

        if (!in_array($payment_method, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Método de pago inválido.');
        }

        $shift = $this->cashbox_service->getCurrentShift($user_id);
        if ($shift === null) {
            throw new DomainException("Debe abrir un turno de caja antes de registrar ingresos.");
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            $income = $this->income_repository->create(
                cashbox_shift_id: $shift->id,
                category: $category,
                concept: $concept,
                amount: $amount,
                payment_method: $payment_method,
                created_by_user_id: $user_id,
                notes: $notes
            );

            $this->cashbox_service->recordMovement(
                user_id: $user_id,
                type: 'income',
                category: 'manual_income',
                concept: "Otro Ingreso: {$concept}",
                amount: $amount,
                payment_method: $payment_method,
                reference_id: $income->id,
                reference_type: 'other_income'
            );

            if ($owns_transaction) {
                $this->db->commit();
            }
            return $income;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, OtherIncome>
     */
    public function listOtherIncomes(): array
    {
        return $this->income_repository->findAll();
    }

    private function isValidMoneyAmount(float $amount): bool
    {
        return is_finite($amount) && $amount > 0 && abs($amount - round($amount, 2)) <= 0.000001;
    }
}
