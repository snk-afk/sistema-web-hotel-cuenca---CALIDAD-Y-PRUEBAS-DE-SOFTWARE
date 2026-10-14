<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\AuditLogRepository;
use App\Repositories\CashMovementRepository;
use App\Repositories\CashboxShiftRepository;
use App\Repositories\GuestRepository;
use App\Repositories\ReservationCancellationRepository;
use App\Repositories\ReservationPaymentRepository;
use App\Repositories\ReservationRefundRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Services\AuditLogService;
use App\Services\CashboxService;
use App\Services\ReservationSettlementService;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class ReservationSettlementServiceTest extends TestCase
{
    private PDO $pdo;
    private ReservationSettlementService $service;
    private ReservationRepository $reservation_repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->createTables();
        $this->pdo->exec(
            "INSERT INTO roles (id, name) VALUES (1, 'receptionist');
             INSERT INTO users (id, role_id, username, email, password_hash, full_name) VALUES
                 (1, 1, 'settlement-user', 'settlement@example.test', 'hash', 'Settlement User');"
        );

        $category_repository = new RoomCategoryRepository($this->pdo);
        $room_repository = new RoomRepository($this->pdo, $category_repository);
        $guest_repository = new GuestRepository($this->pdo);
        $tariff_repository = new RoomTariffRepository($this->pdo);
        $this->reservation_repository = new ReservationRepository(
            $this->pdo,
            $room_repository,
            $guest_repository,
            $tariff_repository,
        );

        $this->service = new ReservationSettlementService(
            db: $this->pdo,
            reservation_repository: $this->reservation_repository,
            reservation_payment_repository: new ReservationPaymentRepository($this->pdo),
            cancellation_repository: new ReservationCancellationRepository($this->pdo),
            refund_repository: new ReservationRefundRepository($this->pdo),
            cashbox_service: new CashboxService(
                $this->pdo,
                new CashboxShiftRepository($this->pdo, new CashMovementRepository($this->pdo)),
                new CashMovementRepository($this->pdo),
            ),
            audit_log_service: new AuditLogService($this->pdo, new AuditLogRepository($this->pdo)),
            clock: new DateTimeImmutable('2026-09-07 12:00:00', new DateTimeZone('America/Lima')),
        );
    }

    public function testCancellationAtLeast24HoursCreatesFullRefundSettlement(): void
    {
        $reservation_id = $this->createReservation('2026-09-08 12:00:00', 100.00, 1);

        $settlement = $this->service->requestCancellation(
            reservation_id: $reservation_id,
            user_id: 1,
            reason: 'Cancelación anticipada',
        );

        self::assertSame('cancelled', $this->reservation_repository->findById($reservation_id)?->status);
        self::assertSame('cancelled', $settlement->event_type);
        self::assertSame('full_refund', $settlement->policy_code);
        self::assertSame('pending_refund', $settlement->status);
        self::assertSame(100.0, $settlement->refund_amount);
        self::assertSame(0.0, $settlement->penalty_amount);
        self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
    }

    public function testCancellationWithin24HoursCreatesPartialRefundSettlement(): void
    {
        $reservation_id = $this->createReservation('2026-09-08 10:00:00', 100.00, 2);

        $settlement = $this->service->requestCancellation(
            reservation_id: $reservation_id,
            user_id: 1,
            reason: 'Cancelación tardía',
        );

        self::assertSame('partial_refund', $settlement->policy_code);
        self::assertSame(50.0, $settlement->refund_amount);
        self::assertSame(50.0, $settlement->penalty_amount);
    }

    public function testNoShowAfterTwoHoursCreatesNoRefundSettlement(): void
    {
        $reservation_id = $this->createReservation('2026-09-07 09:00:00', 100.00, 3);

        $settlement = $this->service->declareNoShow(
            reservation_id: $reservation_id,
            user_id: 1,
            reason: 'Huésped no llegó',
        );

        self::assertSame('no_show', $settlement->event_type);
        self::assertSame('no_refund', $settlement->policy_code);
        self::assertSame('no_refund', $settlement->status);
        self::assertSame(0.0, $settlement->refund_amount);
        self::assertSame(100.0, $settlement->penalty_amount);
        self::assertSame('no_show', $this->reservation_repository->findById($reservation_id)?->status);
    }

    public function testNoShowDuringGracePeriodDoesNotMutateReservation(): void
    {
        $reservation_id = $this->createReservation('2026-09-07 11:00:00', 100.00, 4);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('dos horas');

        try {
            $this->service->declareNoShow($reservation_id, 1, 'No llegó');
        } finally {
            self::assertSame('confirmed', $this->reservation_repository->findById($reservation_id)?->status);
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reservation_cancellations')->fetchColumn());
        }
    }

    public function testCancellationRequiresReason(): void
    {
        $reservation_id = $this->createReservation('2026-09-10 12:00:00', 0.00, 5);

        $this->expectException(InvalidArgumentException::class);
        $this->service->requestCancellation($reservation_id, 1, '   ');
    }

    public function testCheckedInReservationCannotBeSettled(): void
    {
        $reservation_id = $this->createReservation('2026-09-08 12:00:00', 0.00, 6);
        $this->pdo->prepare('UPDATE reservations SET status = :status WHERE id = :id')->execute([
            'status' => 'checked_in',
            'id' => $reservation_id,
        ]);

        $this->expectException(DomainException::class);
        $this->service->requestCancellation($reservation_id, 1, 'No procede');
    }

    public function testCancellationWithoutAdvanceIsMarkedNoRefund(): void
    {
        $reservation_id = $this->createReservation('2026-09-10 12:00:00', 0.00, 7);

        $settlement = $this->service->requestCancellation($reservation_id, 1, 'Sin adelanto');

        self::assertSame('no_refund', $settlement->status);
        self::assertSame(0.0, $settlement->gross_paid_amount);
        self::assertSame(0.0, $settlement->refund_amount);
        self::assertSame(0.0, $settlement->penalty_amount);
    }

    public function testAlreadySettledReservationCannotBeSettledTwice(): void
    {
        $reservation_id = $this->createReservation('2026-09-10 12:00:00', 0.00, 8);
        $this->service->requestCancellation($reservation_id, 1, 'Primera solicitud');

        $this->expectException(DomainException::class);
        $this->service->requestCancellation($reservation_id, 1, 'Segunda solicitud');
    }

    public function testAppliedAdvanceRejectsCancellation(): void
    {
        $reservation_id = $this->createReservation('2026-09-10 12:00:00', 40.00, 9);
        $payment_id = (int) $this->pdo->query(
            'SELECT id FROM reservation_payments WHERE reservation_id = ' . $reservation_id
        )->fetchColumn();
        $statement = $this->pdo->prepare(
            'INSERT INTO folio_payments '
            . '(folio_id, payment_type, amount, payment_method, created_by_user_id, reservation_payment_id) '
            . 'VALUES (1, :payment_type, :amount, :payment_method, :created_by_user_id, :reservation_payment_id)'
        );
        $statement->execute([
            'payment_type' => 'advance',
            'amount' => 40.00,
            'payment_method' => 'cash',
            'created_by_user_id' => 1,
            'reservation_payment_id' => $payment_id,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('aplicado a un folio');
        $this->service->requestCancellation($reservation_id, 1, 'No procede');
    }

    public function testAuditFailureRollsBackReservationAndSettlement(): void
    {
        $reservation_id = $this->createReservation('2026-09-10 12:00:00', 0.00, 10);
        $this->pdo->exec('DROP TABLE audit_logs');

        $this->expectException(PDOException::class);
        try {
            $this->service->requestCancellation($reservation_id, 1, 'Debe revertirse');
        } finally {
            self::assertSame('pending', $this->reservation_repository->findById($reservation_id)?->status);
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reservation_cancellations')->fetchColumn());
        }
    }

    public function testExecuteRefundCreatesOneInverseMovementPerOriginalPayment(): void
    {
        $reservation_id = $this->createReservation('2026-09-30 12:00:00', 100.00, 11);
        $shift_id = (int) $this->pdo->query(
            'SELECT id FROM cashbox_shifts WHERE user_id = 1 ORDER BY id DESC LIMIT 1'
        )->fetchColumn();
        $payment_statement = $this->pdo->prepare(
            'INSERT INTO reservation_payments '
            . '(reservation_id, cashbox_shift_id, amount, payment_method, created_by_user_id) '
            . 'VALUES (:reservation_id, :cashbox_shift_id, :amount, :payment_method, :created_by_user_id)'
        );
        $payment_statement->execute([
            'reservation_id' => $reservation_id,
            'cashbox_shift_id' => $shift_id,
            'amount' => 40.00,
            'payment_method' => 'yape',
            'created_by_user_id' => 1,
        ]);
        $settlement = $this->service->requestCancellation($reservation_id, 1, 'Devolución total');

        $result = $this->service->executeReservationRefund(
            cancellation_id: $settlement->id,
            user_id: 1,
            reason: 'Autorización contable',
            reference_number: 'REF-SETTLEMENT-001',
        );

        self::assertSame('completed', $result['cancellation']->status);
        self::assertCount(2, $result['refunds']);
        self::assertCount(2, $result['movements']);
        self::assertSame(['cash', 'yape'], array_map(
            static fn($refund): string => $refund->payment_method,
            $result['refunds']
        ));
        self::assertSame(
            2,
            (int) $this->pdo->query(
                "SELECT COUNT(*) FROM cash_movements WHERE reference_type = 'reservation_refund'"
            )->fetchColumn()
        );

        $shift_statement = $this->pdo->query(
            'SELECT expected_cash, expected_yape FROM cashbox_shifts WHERE user_id = 1 ORDER BY id DESC LIMIT 1'
        );
        $shift = $shift_statement !== false ? $shift_statement->fetch() : false;
        self::assertIsArray($shift);
        self::assertSame(-100.0, (float) $shift['expected_cash']);
        self::assertSame(-40.0, (float) $shift['expected_yape']);
    }

    public function testRefundCannotBeExecutedTwice(): void
    {
        $reservation_id = $this->createReservation('2026-09-30 12:00:00', 20.00, 12);
        $settlement = $this->service->requestCancellation($reservation_id, 1, 'Devolución');
        $this->service->executeReservationRefund($settlement->id, 1, 'Primera ejecución', null);

        $this->expectException(DomainException::class);
        $this->service->executeReservationRefund($settlement->id, 1, 'Segunda ejecución', null);
    }

    public function testClosedShiftRejectsRefundWithoutMutatingSettlement(): void
    {
        $reservation_id = $this->createReservation('2026-09-30 12:00:00', 20.00, 13);
        $settlement = $this->service->requestCancellation($reservation_id, 1, 'Turno de prueba');
        $this->pdo->exec("UPDATE cashbox_shifts SET status = 'closed' WHERE user_id = 1");

        $this->expectException(DomainException::class);
        try {
            $this->service->executeReservationRefund($settlement->id, 1, 'Sin turno', null);
        } finally {
            self::assertSame('pending_refund', $settlement->status);
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reservation_refunds')->fetchColumn());
        }
    }

    public function testRefundAuditFailureRollsBackRefundsMovementsAndExpectedBalance(): void
    {
        $reservation_id = $this->createReservation('2026-09-30 12:00:00', 20.00, 14);
        $settlement = $this->service->requestCancellation($reservation_id, 1, 'Rollback de devolución');
        $this->pdo->exec('DROP TABLE audit_logs');

        $this->expectException(PDOException::class);
        try {
            $this->service->executeReservationRefund($settlement->id, 1, 'Falla de auditoría', null);
        } finally {
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reservation_refunds')->fetchColumn());
            self::assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
            self::assertSame('pending_refund', $settlement->status);
            $expected_cash = (float) $this->pdo->query(
                'SELECT expected_cash FROM cashbox_shifts WHERE user_id = 1 ORDER BY id DESC LIMIT 1'
            )->fetchColumn();
            self::assertSame(0.0, $expected_cash);
        }
    }

    public function testRefundRejectsPaymentsAddedAfterSettlementSnapshot(): void
    {
        $reservation_id = $this->createReservation('2026-09-30 12:00:00', 20.00, 15);
        $settlement = $this->service->requestCancellation($reservation_id, 1, 'Snapshot financiero');
        $shift_id = (int) $this->pdo->query(
            'SELECT id FROM cashbox_shifts WHERE user_id = 1 ORDER BY id DESC LIMIT 1'
        )->fetchColumn();
        $payment_statement = $this->pdo->prepare(
            'INSERT INTO reservation_payments '
            . '(reservation_id, cashbox_shift_id, amount, payment_method, created_by_user_id) '
            . 'VALUES (:reservation_id, :cashbox_shift_id, :amount, :payment_method, :created_by_user_id)'
        );
        $payment_statement->execute([
            'reservation_id' => $reservation_id,
            'cashbox_shift_id' => $shift_id,
            'amount' => 10.00,
            'payment_method' => 'cash',
            'created_by_user_id' => 1,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('monto');
        $this->service->executeReservationRefund($settlement->id, 1, 'No coincide', null);
    }

    private function createReservation(string $check_in_at, float $amount, int $fixture_number): int
    {
        $category_repository = new RoomCategoryRepository($this->pdo);
        $category = $category_repository->create('Settlement Category ' . $fixture_number);
        $room_repository = new RoomRepository($this->pdo, $category_repository);
        $room = $room_repository->create($category->id, 'RS-' . $fixture_number);
        $tariff_repository = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repository->create($category->id, '24 Horas', 24, 150.00);
        $guest_repository = new GuestRepository($this->pdo);
        $guest = $guest_repository->create('DNI', 'RS-' . $fixture_number, 'Settlement Guest');
        $reservation = $this->reservation_repository->create(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: $check_in_at,
            reserved_check_out_at: '2026-09-09 12:00:00',
            advance_payment: $amount,
            created_by_user_id: 1,
        );

        if ($amount > 0) {
            $shift_statement = $this->pdo->prepare(
                'INSERT INTO cashbox_shifts '
                . '(user_id, opened_at, initial_cash, expected_cash, status, balance_status) '
                . 'VALUES (:user_id, :opened_at, 0, 0, :status, :balance_status)'
            );
            $shift_statement->execute([
                'user_id' => 1,
                'opened_at' => '2026-09-07 08:00:00',
                'status' => 'open',
                'balance_status' => 'pending',
            ]);
            $shift_id = (int) $this->pdo->lastInsertId();
            $payment_statement = $this->pdo->prepare(
                'INSERT INTO reservation_payments '
                . '(reservation_id, cashbox_shift_id, amount, payment_method, created_by_user_id) '
                . 'VALUES (:reservation_id, :cashbox_shift_id, :amount, :payment_method, :created_by_user_id)'
            );
            $payment_statement->execute([
                'reservation_id' => $reservation->id,
                'cashbox_shift_id' => $shift_id,
                'amount' => $amount,
                'payment_method' => 'cash',
                'created_by_user_id' => 1,
            ]);
        }

        return $reservation->id;
    }

    private function createTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT);'
            . 'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, role_id INTEGER NOT NULL, username TEXT NOT NULL, email TEXT NOT NULL, password_hash TEXT NOT NULL, full_name TEXT NOT NULL, is_active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE room_categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT, is_active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE rooms (id INTEGER PRIMARY KEY AUTOINCREMENT, room_category_id INTEGER NOT NULL, number TEXT NOT NULL, floor TEXT, status TEXT DEFAULT "free", description TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE room_tariffs (id INTEGER PRIMARY KEY AUTOINCREMENT, room_category_id INTEGER NOT NULL, name TEXT NOT NULL, duration_hours INTEGER NOT NULL, price REAL NOT NULL, is_active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE guests (id INTEGER PRIMARY KEY AUTOINCREMENT, document_type TEXT NOT NULL, document_number TEXT NOT NULL UNIQUE, name TEXT NOT NULL, address TEXT, phone TEXT, email TEXT, notes TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE reservations (id INTEGER PRIMARY KEY AUTOINCREMENT, room_id INTEGER NOT NULL, guest_id INTEGER NOT NULL, room_tariff_id INTEGER NOT NULL, start_time TEXT NOT NULL, end_time TEXT NOT NULL, deposit_amount REAL DEFAULT 0, status TEXT DEFAULT "pending", notes TEXT, created_by_user_id INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE cashbox_shifts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, opened_at TEXT NOT NULL, closed_at TEXT, initial_cash REAL DEFAULT 0, expected_cash REAL DEFAULT 0, real_cash_declared REAL, difference REAL, status TEXT DEFAULT "open", balance_status TEXT DEFAULT "pending", notes TEXT, expected_yape REAL DEFAULT 0, expected_plin REAL DEFAULT 0, expected_card REAL DEFAULT 0, expected_transfer REAL DEFAULT 0, real_yape_declared REAL, real_plin_declared REAL, real_card_declared REAL, real_transfer_declared REAL, difference_yape REAL, difference_plin REAL, difference_card REAL, difference_transfer REAL, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE cash_movements (id INTEGER PRIMARY KEY AUTOINCREMENT, cashbox_shift_id INTEGER NOT NULL, type TEXT NOT NULL, category TEXT NOT NULL, concept TEXT NOT NULL, amount REAL NOT NULL, payment_method TEXT DEFAULT "cash", reference_id INTEGER, reference_type TEXT, created_by_user_id INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE reservation_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, reservation_id INTEGER NOT NULL, cashbox_shift_id INTEGER NOT NULL, amount REAL NOT NULL, payment_method TEXT NOT NULL, reference_number TEXT, notes TEXT, created_by_user_id INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE folio_payments (id INTEGER PRIMARY KEY AUTOINCREMENT, reservation_payment_id INTEGER, folio_id INTEGER NOT NULL, payment_type TEXT NOT NULL, amount REAL NOT NULL, payment_method TEXT NOT NULL, reference_number TEXT, notes TEXT, created_by_user_id INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE reservation_cancellations (id INTEGER PRIMARY KEY AUTOINCREMENT, reservation_id INTEGER NOT NULL UNIQUE, event_type TEXT NOT NULL, policy_code TEXT NOT NULL, refund_percentage REAL NOT NULL, gross_paid_amount REAL NOT NULL, refund_amount REAL NOT NULL, penalty_amount REAL NOT NULL, status TEXT NOT NULL, reason TEXT NOT NULL, requested_by_user_id INTEGER NOT NULL, approved_by_user_id INTEGER, approved_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE reservation_refunds (id INTEGER PRIMARY KEY AUTOINCREMENT, cancellation_id INTEGER NOT NULL, reservation_payment_id INTEGER NOT NULL UNIQUE, cashbox_shift_id INTEGER NOT NULL, amount REAL NOT NULL, payment_method TEXT NOT NULL, reference_number TEXT, executed_by_user_id INTEGER NOT NULL, reason TEXT NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE audit_logs (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, action TEXT NOT NULL, entity_name TEXT NOT NULL, entity_id INTEGER NOT NULL, old_values TEXT, new_values TEXT, ip_address TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
        );
    }
}
