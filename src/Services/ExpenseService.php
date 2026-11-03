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
        $shiftId = $this->requireShiftForEntry($user_id, $concept, $amount, $payment_method, true);

        return $this->inTransaction(function () use ($shiftId, $user_id, $category, $concept, $amount, $payment_method, $supplier_name, $receipt_number): Expense {
            $expense = $this->expense_repository->create(
                cashbox_shift_id: $shiftId,
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

            return $expense;
        });
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
        $shiftId = $this->requireShiftForEntry($user_id, $concept, $amount, $payment_method, false);

        return $this->inTransaction(function () use ($shiftId, $user_id, $category, $concept, $amount, $payment_method, $notes): OtherIncome {
            $income = $this->income_repository->create(
                cashbox_shift_id: $shiftId,
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

            return $income;
        });
    }

    /**
     * @return array<int, OtherIncome>
     */
    public function listOtherIncomes(): array
    {
        return $this->income_repository->findAll();
    }

    private function requireShiftForEntry(int $userId, string $concept, float $amount, string $paymentMethod, bool $isExpense): int
    {
        $entry = $isExpense ? 'egreso' : 'ingreso';
        if (!$this->isValidMoneyAmount($amount)) {
            throw new InvalidArgumentException("El monto del {$entry} debe ser mayor a cero y tener como máximo dos decimales.");
        }
        if (trim($concept) === '') {
            throw new InvalidArgumentException($isExpense
                ? 'Debe ingresar la justificación/concepto del egreso.'
                : 'Debe ingresar el concepto del ingreso.');
        }
        if (!in_array($paymentMethod, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException('Método de pago inválido.');
        }
        $shift = $this->cashbox_service->getCurrentShift($userId);
        if ($shift === null) {
            throw new DomainException("Debe abrir un turno de caja antes de registrar {$entry}s.");
        }
        return $shift->id;
    }

    /**
     * @template T of Expense|OtherIncome
     * @param callable(): T $operation
     * @return T
     */
    private function inTransaction(callable $operation): Expense|OtherIncome
    {
        if ($this->db->inTransaction()) {
            return $operation();
        }

        $this->db->beginTransaction();
        try {
            $result = $operation();
            $this->db->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function isValidMoneyAmount(float $amount): bool
    {
        return is_finite($amount) && $amount > 0 && abs($amount - round($amount, 2)) <= 0.000001;
    }
}
