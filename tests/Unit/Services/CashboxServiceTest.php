<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\CashMovementRepository;
use App\Repositories\CashboxShiftRepository;
use App\Services\CashboxService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class CashboxServiceTest extends TestCase
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
                status TEXT DEFAULT "open",
                balance_status TEXT DEFAULT "pending",
                notes TEXT,
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
        ');
    }

    public function testOpenShiftAndPreventDuplicateActiveShift(): void
    {
        $service = new CashboxService($this->pdo);

        $shift = $service->openShift(user_id: 1, initial_cash: 100.00);

        $this->assertEquals(1, $shift->user_id);
        $this->assertEquals(100.00, $shift->initial_cash);
        $this->assertEquals('open', $shift->status);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('ya tiene un turno');

        $service->openShift(user_id: 1, initial_cash: 50.00);
    }

    public function testMovementsAndCloseShiftCalculatesAuditDifference(): void
    {
        $service = new CashboxService($this->pdo);
        $service->openShift(user_id: 1, initial_cash: 100.00);

        // Income of S/ 50 cash
        $service->recordMovement(
            user_id: 1,
            type: 'income',
            category: 'accommodation',
            concept: 'Cobro Hab 102',
            amount: 50.00,
            payment_method: 'cash'
        );

        // Expense of S/ 10 cash
        $service->recordMovement(
            user_id: 1,
            type: 'expense',
            category: 'petty_expense',
            concept: 'Compra de lejía',
            amount: 10.00,
            payment_method: 'cash'
        );

        // Expected cash = 100 + 50 - 10 = 140.00
        // Declare S/ 135.00 -> Shortage of S/ -5.00
        $closed = $service->closeShift(user_id: 1, real_cash_declared: 135.00);

        $this->assertEquals('closed', $closed->status);
        $this->assertEquals(140.00, $closed->expected_cash);
        $this->assertEquals(135.00, $closed->real_cash_declared);
        $this->assertEquals(-5.00, $closed->difference);
        $this->assertEquals('shortage', $closed->balance_status);
    }

    public function testCloseShiftReconcilesDigitalMethodsSeparately(): void
    {
        $service = new CashboxService($this->pdo);
        $service->openShift(user_id: 1, initial_cash: 100.00);

        foreach ([
            ['method' => 'yape', 'amount' => 80.00],
            ['method' => 'plin', 'amount' => 40.00],
            ['method' => 'card', 'amount' => 120.00],
            ['method' => 'transfer', 'amount' => 50.00],
        ] as $movement) {
            $service->recordMovement(
                user_id: 1,
                type: 'income',
                category: 'accommodation',
                concept: 'Cobro digital',
                amount: $movement['amount'],
                payment_method: $movement['method'],
            );
        }

        $closed = $service->closeShift(
            user_id: 1,
            real_cash_declared: 100.00,
            real_yape_declared: 80.00,
            real_plin_declared: 40.00,
            real_card_declared: 120.00,
            real_transfer_declared: 50.00,
        );

        self::assertSame(100.0, $closed->expected_cash);
        self::assertSame(80.0, $closed->expected_yape);
        self::assertSame(40.0, $closed->expected_plin);
        self::assertSame(120.0, $closed->expected_card);
        self::assertSame(50.0, $closed->expected_transfer);
        self::assertSame(0.0, $closed->difference_yape);
        self::assertSame(0.0, $closed->difference_plin);
        self::assertSame(0.0, $closed->difference_card);
        self::assertSame(0.0, $closed->difference_transfer);
        self::assertSame('balanced', $closed->balance_status);
    }

    public function testClosedShiftRejectsNewMovement(): void
    {
        $service = new CashboxService($this->pdo);
        $service->openShift(user_id: 1, initial_cash: 100.00);
        $service->closeShift(user_id: 1, real_cash_declared: 100.00);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('turno de caja');

        $service->recordMovement(
            user_id: 1,
            type: 'income',
            category: 'accommodation',
            concept: 'Intento posterior al cierre',
            amount: 10.00,
            payment_method: 'cash'
        );
    }

    public function testCloseShiftRequiresDigitalDeclarationsWhenExpectedExists(): void
    {
        $service = new CashboxService($this->pdo);
        $service->openShift(user_id: 1, initial_cash: 100.00);
        $service->recordMovement(
            user_id: 1,
            type: 'income',
            category: 'accommodation',
            concept: 'Cobro Yape',
            amount: 10.00,
            payment_method: 'yape'
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('yape');

        try {
            $service->closeShift(user_id: 1, real_cash_declared: 100.00);
        } finally {
            $this->assertNotNull($service->getCurrentShift(1));
        }
    }

    public function testReferenceTypeIsRestrictedToKnownFinancialOrigins(): void
    {
        $service = new CashboxService($this->pdo);
        $service->openShift(user_id: 1, initial_cash: 100.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('referencia');

        $service->recordMovement(
            user_id: 1,
            type: 'income',
            category: 'other',
            concept: 'Origen desconocido',
            amount: 10.00,
            payment_method: 'cash',
            reference_id: 99,
            reference_type: 'unknown_origin'
        );
    }

    public function testReservationRefundMovementUpdatesOnlyOriginalPaymentMethod(): void
    {
        $service = new CashboxService($this->pdo);
        $service->openShift(user_id: 1, initial_cash: 100.00);

        $movement = $service->recordMovement(
            user_id: 1,
            type: 'expense',
            category: 'reservation_refund',
            concept: 'Devolución reserva #10',
            amount: 25.00,
            payment_method: 'yape',
            reference_id: 55,
            reference_type: 'reservation_refund',
        );

        self::assertSame('expense', $movement->type);
        self::assertSame('reservation_refund', $movement->reference_type);
        self::assertSame('yape', $movement->payment_method);

        $shift = $service->getCurrentShiftForUpdate(1);
        self::assertNotNull($shift);
        self::assertSame(-25.0, $shift->expected_yape);
        self::assertSame(100.0, $shift->expected_cash);
        self::assertSame(0.0, $shift->expected_card);
    }

    public function testListShiftsDelegatesToRepository(): void
    {
        $service = new CashboxService($this->pdo);

        $service->openShift(user_id: 1, initial_cash: 100.00);
        $service->openShift(user_id: 2, initial_cash: 200.00);

        $all = $service->listShifts();

        $this->assertCount(2, $all);
        // Ordered by id DESC: most recent first
        $this->assertEquals(2, $all[0]->user_id);
        $this->assertEquals(1, $all[1]->user_id);
        // Listado NO incluye movimientos
        $this->assertSame([], $all[0]->movements);
    }

    public function testListShiftsAppliesFilters(): void
    {
        $service = new CashboxService($this->pdo);

        $shift_open = $service->openShift(user_id: 1, initial_cash: 100.00);
        $shift_closed = $service->openShift(user_id: 2, initial_cash: 200.00);
        $service->closeShift(user_id: 2, real_cash_declared: 200.00);

        $closed_only = $service->listShifts(status: 'closed');
        $this->assertCount(1, $closed_only);
        $this->assertEquals('closed', $closed_only[0]->status);
    }

    public function testGetShiftByIdReturnsShiftWithMovements(): void
    {
        $service = new CashboxService($this->pdo);

        $service->openShift(user_id: 1, initial_cash: 100.00);
        $service->recordMovement(
            user_id: 1,
            type: 'income',
            category: 'accommodation',
            concept: 'Cobro Hab 102',
            amount: 50.00,
            payment_method: 'cash'
        );
        $service->recordMovement(
            user_id: 1,
            type: 'expense',
            category: 'petty_expense',
            concept: 'Compra de lejía',
            amount: 10.00,
            payment_method: 'cash'
        );

        $shift = $service->getShiftById(1);

        $this->assertNotNull($shift);
        $this->assertCount(2, $shift->movements);
    }

    public function testGetShiftByIdReturnsNullWhenNotFound(): void
    {
        $service = new CashboxService($this->pdo);

        $shift = $service->getShiftById(999);

        $this->assertNull($shift);
    }

    public function testRejectsAmountsWithMoreThanTwoDecimals(): void
    {
        $service = new CashboxService($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dos decimales');

        $service->openShift(user_id: 1, initial_cash: 100.001);
    }

    public function testRejectsMovementWithMoreThanTwoDecimalsWithoutPersistingIt(): void
    {
        $service = new CashboxService($this->pdo);
        $service->openShift(user_id: 1, initial_cash: 100.00);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('dos decimales');

        try {
            $service->recordMovement(
                user_id: 1,
                type: 'income',
                category: 'accommodation',
                concept: 'Monto con precisión inválida',
                amount: 10.001,
                payment_method: 'cash'
            );
        } finally {
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
        }
    }
}
