<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CashboxService;
use App\Services\ExpenseService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class ExpenseServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE cashbox_shifts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                opened_at TEXT NOT NULL,
                closed_at TEXT,
                initial_cash REAL DEFAULT 0.0,
                expected_cash REAL DEFAULT 0.0,
                real_cash_declared REAL,
                difference REAL,
                expected_yape REAL DEFAULT 0.0,
                expected_plin REAL DEFAULT 0.0,
                expected_card REAL DEFAULT 0.0,
                expected_transfer REAL DEFAULT 0.0,
                real_yape_declared REAL,
                real_plin_declared REAL,
                real_card_declared REAL,
                real_transfer_declared REAL,
                difference_yape REAL,
                difference_plin REAL,
                difference_card REAL,
                difference_transfer REAL,
                status TEXT DEFAULT "open",
                balance_status TEXT DEFAULT "pending",
                notes TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE cash_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cashbox_shift_id INTEGER NOT NULL,
                type TEXT NOT NULL,
                category TEXT NOT NULL,
                concept TEXT NOT NULL,
                amount REAL NOT NULL,
                payment_method TEXT DEFAULT "cash",
                reference_id INTEGER,
                reference_type TEXT,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cashbox_shift_id INTEGER NOT NULL,
                category TEXT DEFAULT "petty_cash",
                concept TEXT NOT NULL,
                amount REAL NOT NULL,
                payment_method TEXT DEFAULT "cash",
                supplier_name TEXT,
                receipt_number TEXT,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE other_incomes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cashbox_shift_id INTEGER NOT NULL,
                category TEXT DEFAULT "other_income",
                concept TEXT NOT NULL,
                amount REAL NOT NULL,
                payment_method TEXT DEFAULT "cash",
                notes TEXT,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function testRecordExpenseDeductsExpectedCashInActiveShift(): void
    {
        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 100.00);

        $expense_service = new ExpenseService($this->pdo);
        $expense = $expense_service->recordExpense(
            user_id: 1,
            category: 'supplies',
            concept: 'Compra de lejía',
            amount: 15.00,
            payment_method: 'cash',
            supplier_name: 'Bodega San José'
        );

        $this->assertEquals(15.00, $expense->amount);

        $shift = $cashbox_service->getCurrentShift(1);
        $this->assertNotNull($shift);
        $this->assertEquals(85.00, $shift->expected_cash); // 100 - 15
    }

    public function testRecordOtherIncomeIncreasesExpectedCashInActiveShift(): void
    {
        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 85.00);

        $expense_service = new ExpenseService($this->pdo);
        $income = $expense_service->recordOtherIncome(
            user_id: 1,
            category: 'parking',
            concept: 'Alquiler cochera por 2 horas',
            amount: 10.00,
            payment_method: 'cash'
        );

        $this->assertEquals(10.00, $income->amount);

        $shift = $cashbox_service->getCurrentShift(1);
        $this->assertNotNull($shift);
        $this->assertEquals(95.00, $shift->expected_cash); // 85 + 10
    }

    public function testRecordExpenseUpdatesDigitalExpectedAndOrigin(): void
    {
        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 100.00);

        $expense_service = new ExpenseService($this->pdo);
        $expense_service->recordExpense(
            user_id: 1,
            category: 'supplies',
            concept: 'Compra digital',
            amount: 15.00,
            payment_method: 'card'
        );

        $shift = $cashbox_service->getCurrentShift(1);
        $this->assertNotNull($shift);
        $this->assertSame(-15.00, $shift->expected_card);
        $movement = $this->pdo->query(
            'SELECT payment_method, reference_type FROM cash_movements'
        )->fetch();
        $this->assertSame('card', $movement['payment_method']);
        $this->assertSame('expense', $movement['reference_type']);
    }
}
