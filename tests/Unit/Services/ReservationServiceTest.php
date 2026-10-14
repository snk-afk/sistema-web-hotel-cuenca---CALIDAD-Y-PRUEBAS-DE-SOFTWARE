<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\GuestRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\StayGuestRepository;
use App\Repositories\StayRepository;
use App\Services\ReservationService;
use App\Services\ReservationSettlementService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReservationServiceTest extends TestCase
{
    private PDO $pdo;
    private ReservationService $service;
    private RoomRepository $room_repo;
    private GuestRepository $guest_repo;
    private RoomTariffRepository $tariff_repo;
    private RoomCategoryRepository $category_repo;
    private ReservationRepository $reservation_repo;
    private StayRepository $stay_repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();

        $this->category_repo = new RoomCategoryRepository($this->pdo);
        $this->room_repo = new RoomRepository($this->pdo, $this->category_repo);
        $this->tariff_repo = new RoomTariffRepository($this->pdo);
        $this->guest_repo = new GuestRepository($this->pdo);
        $this->reservation_repo = new ReservationRepository($this->pdo, $this->room_repo, $this->guest_repo, $this->tariff_repo);
        $this->stay_repo = new StayRepository($this->pdo);
        $stay_guest_repo = new StayGuestRepository($this->pdo, $this->guest_repo);
        $this->pdo->exec(
            'INSERT INTO cashbox_shifts (user_id, opened_at, initial_cash, expected_cash, status) '
            . 'VALUES (1, CURRENT_TIMESTAMP, 100.00, 100.00, "open")'
        );

        $this->service = new ReservationService(
            db: $this->pdo,
            reservation_repository: $this->reservation_repo,
            room_repository: $this->room_repo,
            guest_repository: $this->guest_repo,
            tariff_repository: $this->tariff_repo,
            stay_repository: $this->stay_repo,
            stay_guest_repository: $stay_guest_repo
        );
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE room_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_category_id INTEGER NOT NULL,
                number TEXT NOT NULL UNIQUE,
                status TEXT DEFAULT "free",
                floor TEXT,
                description TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE room_tariffs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_category_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                duration_hours INTEGER NOT NULL,
                price REAL NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE guests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_type TEXT DEFAULT "DNI",
                document_number TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                phone TEXT,
                email TEXT,
                address TEXT,
                notes TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE stays (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                room_tariff_id INTEGER NOT NULL,
                guest_id INTEGER,
                check_in_at TEXT NOT NULL,
                expected_check_out_at TEXT NOT NULL,
                actual_check_out_at TEXT,
                base_price REAL NOT NULL,
                applied_price REAL NOT NULL,
                discount_reason TEXT,
                status TEXT DEFAULT "active",
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE stay_guests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER NOT NULL,
                guest_id INTEGER NOT NULL,
                is_primary INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE stay_extensions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER NOT NULL,
                added_hours INTEGER NOT NULL,
                added_price REAL NOT NULL,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                guest_id INTEGER NOT NULL,
                room_tariff_id INTEGER NOT NULL,
                start_time TEXT NOT NULL,
                end_time TEXT NOT NULL,
                deposit_amount REAL DEFAULT 0.0,
                status TEXT DEFAULT "pending",
                notes TEXT,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE reservation_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_id INTEGER NOT NULL,
                cashbox_shift_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                payment_method TEXT NOT NULL,
                reference_number TEXT,
                notes TEXT,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE reservation_cancellations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reservation_id INTEGER NOT NULL UNIQUE,
                event_type TEXT NOT NULL,
                policy_code TEXT NOT NULL,
                refund_percentage REAL NOT NULL,
                gross_paid_amount REAL NOT NULL,
                refund_amount REAL NOT NULL,
                penalty_amount REAL NOT NULL,
                status TEXT NOT NULL,
                reason TEXT NOT NULL,
                requested_by_user_id INTEGER NOT NULL,
                approved_by_user_id INTEGER,
                approved_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE reservation_refunds (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cancellation_id INTEGER NOT NULL,
                reservation_payment_id INTEGER NOT NULL UNIQUE,
                cashbox_shift_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                payment_method TEXT NOT NULL,
                reference_number TEXT,
                executed_by_user_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE folios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER NOT NULL UNIQUE,
                status TEXT DEFAULT "open",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE folio_charges (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                folio_id INTEGER NOT NULL,
                charge_type TEXT NOT NULL,
                description TEXT NOT NULL,
                quantity INTEGER DEFAULT 1,
                unit_price REAL NOT NULL,
                total_amount REAL NOT NULL,
                is_reversal INTEGER DEFAULT 0,
                reversal_reason TEXT,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE folio_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                folio_id INTEGER NOT NULL,
                payment_type TEXT NOT NULL,
                amount REAL NOT NULL,
                payment_method TEXT DEFAULT "cash",
                reference_number TEXT,
                notes TEXT,
                created_by_user_id INTEGER NOT NULL,
                cashbox_shift_id INTEGER,
                reservation_payment_id INTEGER,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

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

            CREATE TABLE audit_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                action TEXT NOT NULL,
                entity_name TEXT NOT NULL,
                entity_id INTEGER NOT NULL,
                old_values TEXT,
                new_values TEXT,
                ip_address TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
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

    public function testCreateReservationWithAdvanceSetsConfirmedStatus(): void
    {
        $category = $this->category_repo->create('Matrimonial');
        $room = $this->room_repo->create($category->id, '201');
        $tariff = $this->tariff_repo->create($category->id, '12 Horas', 12, 60.00);
        $guest = $this->guest_repo->create('DNI', '70809010', 'Andrea Medina');

        $reservation = $this->service->createReservation(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: '2026-08-25 14:00:00',
            advance_payment: 30.00,
            created_by_user_id: 1,
            notes: 'Decoración de bienvenida',
            payment_parts: [[
                'amount' => 30.00,
                'payment_method' => 'cash',
            ]]
        );

        $this->assertEquals('confirmed', $reservation->status);
        $this->assertEquals(30.00, $reservation->advance_payment);
        $this->assertEquals('2026-08-26 02:00:00', $reservation->reserved_check_out_at);
        $this->assertEquals('Decoración de bienvenida', $reservation->notes);
    }

    public function testCreateReservationPersistsAdvancePaymentAndCashMovement(): void
    {
        $category = $this->category_repo->create('Matrimonial');
        $room = $this->room_repo->create($category->id, '202');
        $tariff = $this->tariff_repo->create($category->id, '12 Horas', 60, 60.00);
        $guest = $this->guest_repo->create('DNI', '70809011', 'Andrea Medina');

        $reservation = $this->service->createReservation(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: '2026-08-25 14:00:00',
            advance_payment: 20.00,
            created_by_user_id: 1,
            payment_parts: [[
                'amount' => 20.00,
                'payment_method' => 'yape',
                'reference_number' => 'YAPE-RES-1',
            ]]
        );

        $payment_stmt = $this->pdo->prepare(
            'SELECT reservation_id, cashbox_shift_id, amount, payment_method, reference_number,
                    created_by_user_id
             FROM reservation_payments
             WHERE reservation_id = :reservation_id'
        );
        $payment_stmt->execute(['reservation_id' => $reservation->id]);
        /** @var array{reservation_id: int|string, cashbox_shift_id: int|string, amount: float|string, payment_method: string, reference_number: ?string, created_by_user_id: int|string}|false $payment */
        $payment = $payment_stmt->fetch();

        $movement_stmt = $this->pdo->prepare(
            'SELECT cashbox_shift_id, type, category, concept, amount, payment_method,
                    reference_id, reference_type, created_by_user_id
             FROM cash_movements
             WHERE reference_id = :reference_id AND reference_type = :reference_type'
        );
        $movement_stmt->execute([
            'reference_id' => 1,
            'reference_type' => 'reservation_payment',
        ]);
        /** @var array{cashbox_shift_id: int|string, type: string, category: string, concept: string, amount: float|string, payment_method: string, reference_id: int|string, reference_type: string, created_by_user_id: int|string}|false $movement */
        $movement = $movement_stmt->fetch();

        $this->assertIsArray($payment);
        $this->assertSame($reservation->id, (int) $payment['reservation_id']);
        $this->assertSame(20.00, (float) $payment['amount']);
        $this->assertSame('yape', $payment['payment_method']);
        $this->assertSame('YAPE-RES-1', $payment['reference_number']);
        $this->assertIsArray($movement);
        $this->assertSame('income', $movement['type']);
        $this->assertSame('yape', $movement['payment_method']);
        $this->assertSame($payment['cashbox_shift_id'], $movement['cashbox_shift_id']);
        $this->assertSame('reservation_payment', $movement['reference_type']);
        $this->assertSame(1, (int) $movement['reference_id']);
    }

    public function testCreateReservationWithoutAdvanceSetsPendingStatus(): void
    {
        $category = $this->category_repo->create('Simple');
        $room = $this->room_repo->create($category->id, '101');
        $tariff = $this->tariff_repo->create($category->id, '24 Horas', 24, 80.00);
        $guest = $this->guest_repo->create('DNI', '71829304', 'Jorge Valdivia');

        $reservation = $this->service->createReservation(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: '2026-08-26 12:00:00',
            advance_payment: 0.00,
            created_by_user_id: 1
        );

        $this->assertEquals('pending', $reservation->status);
        $this->assertEquals(0.00, $reservation->advance_payment);
        $this->assertEquals('2026-08-27 12:00:00', $reservation->reserved_check_out_at);
    }

    public function testAuditFailureRollsBackReservationPaymentAndMovement(): void
    {
        $category = $this->category_repo->create('Simple');
        $room = $this->room_repo->create($category->id, '203');
        $tariff = $this->tariff_repo->create($category->id, '12 Horas', 50, 50.00);
        $guest = $this->guest_repo->create('DNI', '70809012', 'Auditoria Fallida');

        $this->pdo->exec('DROP TABLE audit_logs');

        $service = new ReservationService(
            db: $this->pdo,
            reservation_repository: $this->reservation_repo,
            room_repository: $this->room_repo,
            guest_repository: $this->guest_repo,
            tariff_repository: $this->tariff_repo,
            stay_repository: $this->stay_repo,
            stay_guest_repository: new StayGuestRepository($this->pdo, $this->guest_repo)
        );

        $this->expectException(\PDOException::class);
        try {
            $service->createReservation(
                room_id: $room->id,
                guest_id: $guest->id,
                room_tariff_id: $tariff->id,
                reserved_check_in_at: '2026-08-29 14:00:00',
                advance_payment: 20.00,
                created_by_user_id: 1,
                payment_parts: [[
                    'amount' => 20.00,
                    'payment_method' => 'cash',
                ]]
            );
        } finally {
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn());
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reservation_payments')->fetchColumn());
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
        }
    }

    public function testAdvanceWithoutOpenShiftDoesNotPersistReservation(): void
    {
        $cashbox_service = new \App\Services\CashboxService($this->pdo);
        $cashbox_service->closeShift(user_id: 1, real_cash_declared: 100.00);

        $category = $this->category_repo->create('Simple');
        $room = $this->room_repo->create($category->id, '204');
        $tariff = $this->tariff_repo->create($category->id, '12 Horas', 50, 50.00);
        $guest = $this->guest_repo->create('DNI', '70809013', 'Sin Caja');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('turno de caja');

        try {
            $this->service->createReservation(
                room_id: $room->id,
                guest_id: $guest->id,
                room_tariff_id: $tariff->id,
                reserved_check_in_at: '2026-08-29 16:00:00',
                advance_payment: 20.00,
                created_by_user_id: 1,
                payment_parts: [['amount' => 20.00, 'payment_method' => 'cash']]
            );
        } finally {
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn());
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM reservation_payments')->fetchColumn());
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
        }
    }

    public function testOverlapMatrixScenarios(): void
    {
        $category = $this->category_repo->create('Suite');
        $room = $this->room_repo->create($category->id, '301');
        $tariff = $this->tariff_repo->create($category->id, '10 Horas', 10, 100.00);
        $guest1 = $this->guest_repo->create('DNI', '44556677', 'Cliente Base');
        $guest2 = $this->guest_repo->create('DNI', '55667788', 'Cliente Conflicto');

        // Existing reservation from 2026-08-25 10:00:00 to 2026-08-25 20:00:00
        $this->service->createReservation(
            room_id: $room->id,
            guest_id: $guest1->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: '2026-08-25 10:00:00',
            advance_payment: 50.00,
            created_by_user_id: 1,
            payment_parts: [[
                'amount' => 50.00,
                'payment_method' => 'cash',
            ]]
        );

        // Case A: Exact same interval
        try {
            $this->service->createReservation($room->id, $guest2->id, $tariff->id, '2026-08-25 10:00:00', 0.00, 1);
            $this->fail('Should throw DomainException for exact overlap');
        } catch (DomainException $e) {
            $this->assertStringContainsString('rango de fechas', $e->getMessage());
        }

        // Case B: Starts inside [12:00 -> 22:00]
        try {
            $this->service->createReservation($room->id, $guest2->id, $tariff->id, '2026-08-25 12:00:00', 0.00, 1);
            $this->fail('Should throw DomainException for overlapping start');
        } catch (DomainException $e) {
            $this->assertStringContainsString('rango de fechas', $e->getMessage());
        }

        // Case C: Ends inside [08:00 -> 18:00]
        try {
            $this->service->createReservation($room->id, $guest2->id, $tariff->id, '2026-08-25 08:00:00', 0.00, 1);
            $this->fail('Should throw DomainException for overlapping end');
        } catch (DomainException $e) {
            $this->assertStringContainsString('rango de fechas', $e->getMessage());
        }

        // Case D: Completely adjacent non-overlapping [20:00 -> 06:00 next day] -> SHOULD SUCCEED
        $adjacent = $this->service->createReservation(
            $room->id,
            $guest2->id,
            $tariff->id,
            '2026-08-25 20:00:00',
            20.00,
            1,
            null,
            [[
                'amount' => 20.00,
                'payment_method' => 'cash',
            ]]
        );
        $this->assertNotNull($adjacent->id);
    }

    public function testConvertReservationToStayExecution(): void
    {
        $category = $this->category_repo->create('Matrimonial VIP');
        $room = $this->room_repo->create($category->id, '205');
        $tariff = $this->tariff_repo->create($category->id, '12 Horas', 12, 70.00);
        $guest = $this->guest_repo->create('DNI', '99887766', 'Lucia Ramirez');

        $res = $this->service->createReservation(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: date('Y-m-d H:i:s'),
            advance_payment: 70.00,
            created_by_user_id: 1,
            payment_parts: [[
                'amount' => 70.00,
                'payment_method' => 'cash',
            ]]
        );

        $stay = $this->service->convertReservationToStay($res->id, 1);

        $this->assertNotNull($stay->id);
        $this->assertEquals($room->id, $stay->room_id);

        $updated_room = $this->room_repo->findById($room->id);
        $this->assertEquals('occupied', $updated_room->status);

        $updated_res = $this->reservation_repo->findById($res->id);
        $this->assertEquals('checked_in', $updated_res->status);

        $folio_stmt = $this->pdo->prepare(
            'SELECT id FROM folios WHERE stay_id = :stay_id'
        );
        $folio_stmt->execute(['stay_id' => $stay->id]);
        /** @var int|string|false $folio_id */
        $folio_id = $folio_stmt->fetchColumn();
        $this->assertNotFalse($folio_id);

        $charge_stmt = $this->pdo->prepare(
            'SELECT COUNT(*), COALESCE(SUM(total_amount), 0)
             FROM folio_charges
             WHERE folio_id = :folio_id'
        );
        $charge_stmt->execute(['folio_id' => $folio_id]);
        /** @var array{0: int|string, 1: float|string} $charge_summary */
        $charge_summary = $charge_stmt->fetch(PDO::FETCH_NUM);
        $this->assertSame(1, (int) $charge_summary[0]);
        $this->assertSame(70.00, (float) $charge_summary[1]);

        $folio_payment_stmt = $this->pdo->prepare(
            'SELECT reservation_payment_id, amount, payment_method, cashbox_shift_id
             FROM folio_payments
             WHERE folio_id = :folio_id'
        );
        $folio_payment_stmt->execute(['folio_id' => $folio_id]);
        /** @var array{reservation_payment_id: int|string, amount: float|string, payment_method: string, cashbox_shift_id: int|string}|false $folio_payment */
        $folio_payment = $folio_payment_stmt->fetch();
        $this->assertIsArray($folio_payment);
        $this->assertSame(70.00, (float) $folio_payment['amount']);
        $this->assertSame('cash', $folio_payment['payment_method']);
        $this->assertGreaterThan(0, (int) $folio_payment['reservation_payment_id']);
        $this->assertSame(1, (int) $folio_payment['cashbox_shift_id']);

        $movement_count = (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn();
        $this->assertSame(1, $movement_count);

        // Trying to convert again should throw DomainException
        $this->expectException(DomainException::class);
        $this->service->convertReservationToStay($res->id, 1);
    }

    public function testConvertReservationFailsIfRoomNotFree(): void
    {
        $category = $this->category_repo->create('Simple');
        $room = $this->room_repo->create($category->id, '108');
        $this->room_repo->updateStatus($room->id, 'occupied');

        $tariff = $this->tariff_repo->create($category->id, '12 Horas', 12, 45.00);
        $guest = $this->guest_repo->create('DNI', '66554433', 'Marco Polo');

        $res = $this->service->createReservation(
            $room->id,
            $guest->id,
            $tariff->id,
            date('Y-m-d H:i:s'),
            20.00,
            1,
            null,
            [[
                'amount' => 20.00,
                'payment_method' => 'cash',
            ]]
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no está libre');
        $this->service->convertReservationToStay($res->id, 1);
    }

    public function testCancelReservation(): void
    {
        $category = $this->category_repo->create('Familiar');
        $room = $this->room_repo->create($category->id, '401');
        $tariff = $this->tariff_repo->create($category->id, '24 Horas', 24, 120.00);
        $guest = $this->guest_repo->create('DNI', '12344321', 'Familia Perez');

        $res = $this->service->createReservation($room->id, $guest->id, $tariff->id, '2026-09-30 12:00:00', 0.00, 1);

        $settlement_service = new ReservationSettlementService($this->pdo);
        $settlement = $settlement_service->requestCancellation($res->id, 1, 'Cancelación de prueba');

        $updated_res = $this->reservation_repo->findById($res->id);
        $this->assertEquals('cancelled', $updated_res->status);
        $this->assertEquals('no_refund', $settlement->status);

        // Cancelling again throws DomainException
        $this->expectException(DomainException::class);
        $settlement_service->requestCancellation($res->id, 1, 'Segunda cancelación');
    }

    public function testCatalogAndMetrics(): void
    {
        $category = $this->category_repo->create('Standard');
        $room1 = $this->room_repo->create($category->id, '102');
        $room2 = $this->room_repo->create($category->id, '103');
        $tariff = $this->tariff_repo->create($category->id, '12 Horas', 12, 50.00);
        $guest1 = $this->guest_repo->create('DNI', '22334455', 'Elena Silva');
        $guest2 = $this->guest_repo->create('DNI', '33445566', 'Roberto Diaz');

        $this->service->createReservation(
            $room1->id,
            $guest1->id,
            $tariff->id,
            date('Y-m-d 15:00:00'),
            25.00,
            1,
            null,
            [[
                'amount' => 25.00,
                'payment_method' => 'cash',
            ]]
        );
        $this->service->createReservation($room2->id, $guest2->id, $tariff->id, '2026-08-30 10:00:00', 0.00, 1);

        $catalog = $this->service->getReservationsCatalog();
        $this->assertCount(2, $catalog);

        $filtered_search = $this->service->getReservationsCatalog('Elena');
        $this->assertCount(1, $filtered_search);
        $this->assertEquals('Elena Silva', $filtered_search[0]['guest_name']);

        $filtered_status = $this->service->getReservationsCatalog(status: 'pending');
        $this->assertCount(1, $filtered_status);
        $this->assertEquals('Roberto Diaz', $filtered_status[0]['guest_name']);

        $metrics = $this->service->getReservationMetrics();
        $this->assertEquals(2, $metrics['total_reservations']);
        $this->assertEquals(2, $metrics['active_reservations']);
        $this->assertEquals(1, $metrics['confirmed_count']);
        $this->assertEquals(1, $metrics['pending_count']);
        $this->assertEquals(25.00, $metrics['total_advance_amount']);
    }
}
