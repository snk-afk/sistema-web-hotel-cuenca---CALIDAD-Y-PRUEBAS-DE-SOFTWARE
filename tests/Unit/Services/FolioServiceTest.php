<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\FolioChargeRepository;
use App\Repositories\FolioPaymentRepository;
use App\Repositories\FolioRepository;
use App\Repositories\GuestRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\StayRepository;
use App\Services\CashboxService;
use App\Services\FolioService;
use App\Services\RoomService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class FolioServiceTest extends TestCase
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

        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 100.00);
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

            CREATE TABLE stay_extensions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER NOT NULL,
                added_hours INTEGER NOT NULL,
                added_price REAL NOT NULL,
                created_by_user_id INTEGER NOT NULL,
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

            CREATE TABLE room_transfers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER NOT NULL,
                from_room_id INTEGER NOT NULL,
                to_room_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
                created_by_user_id INTEGER NOT NULL,
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

            CREATE TABLE housekeeping_tasks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                task_type TEXT DEFAULT "checkout_cleaning",
                status TEXT DEFAULT "pending",
                priority TEXT DEFAULT "normal",
                assigned_to_user_id INTEGER,
                started_at TEXT,
                completed_at TEXT,
                notes TEXT,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function testAddChargeAndPaymentCalculatesPendingBalance(): void
    {
        $cat_repo = new RoomCategoryRepository($this->pdo);
        $category = $cat_repo->create('Jacuzzi');

        $room_repo = new RoomRepository($this->pdo, $cat_repo);
        $room = $room_repo->create($category->id, '205');

        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 70.00);

        $room_service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $room_service->occupyRoom($room->id, $tariff->id, 70.00, null, 1);

        $folio_service = new FolioService($this->pdo);

        // Add store item charge
        $folio_service->addCharge(
            stay_id: $stay->id,
            charge_type: 'store_item',
            description: 'Coca-Cola 500ml',
            quantity: 2,
            unit_price: 4.00,
            created_by_user_id: 1
        );

        // Add advance payment
        $folio_service->addPayment(
            stay_id: $stay->id,
            payment_type: 'advance',
            amount: 30.00,
            payment_method: 'cash',
            created_by_user_id: 1
        );

        $folio = $folio_service->getOrCreateFolio($stay->id);

        $this->assertEquals(78.00, $folio->total_charges); // 70.00 accommodation + 8.00 drinks
        $this->assertEquals(30.00, $folio->total_payments);
        $this->assertEquals(48.00, $folio->pending_balance);
    }

    public function testReverseChargeGeneratesCompensatingNegativeCharge(): void
    {
        $cat_repo = new RoomCategoryRepository($this->pdo);
        $category = $cat_repo->create('Simple');
        $room_repo = new RoomRepository($this->pdo, $cat_repo);
        $room = $room_repo->create($category->id, '101');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 35.00);

        $room_service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $room_service->occupyRoom($room->id, $tariff->id, 35.00, null, 1);

        $folio_service = new FolioService($this->pdo);
        $charge = $folio_service->addCharge(
            stay_id: $stay->id,
            charge_type: 'service',
            description: 'Lavandería',
            quantity: 1,
            unit_price: 15.00,
            created_by_user_id: 1
        );

        $reversal = $folio_service->reverseCharge(
            stay_id: $stay->id,
            charge_id: $charge->id,
            reversal_reason: 'Cobro por error',
            created_by_user_id: 1
        );

        $this->assertTrue($reversal->is_reversal);
        $this->assertEquals(-15.00, $reversal->total_amount);

        $folio = $folio_service->getOrCreateFolio($stay->id);
        $this->assertEquals(35.00, $folio->total_charges); // 35.00 + 15.00 - 15.00
    }

    public function testMixedPaymentUpdatesEachCashboxMethodAndClosesBalance(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Suite');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '206');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 50.00);

        $room_service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $room_service->occupyRoom($room->id, $tariff->id, 50.00, null, 1);
        $folio_service = new FolioService($this->pdo);

        $folio_service->addPayment($stay->id, 'partial', 30.00, 'cash', 1);
        $folio_service->addPayment(
            stay_id: $stay->id,
            payment_type: 'final',
            amount: 20.00,
            payment_method: 'card',
            created_by_user_id: 1,
            reference_number: 'POS-55'
        );

        $folio = $folio_service->getOrCreateFolio($stay->id);
        $this->assertSame(0.00, $folio->pending_balance);

        $shift = (new CashboxService($this->pdo))->getCurrentShift(1);
        $this->assertNotNull($shift);
        $this->assertSame(130.00, $shift->expected_cash);
        $this->assertSame(20.00, $shift->expected_card);

        $movement_rows = $this->pdo->query(
            'SELECT payment_method, reference_type FROM cash_movements ORDER BY id ASC'
        )->fetchAll();
        $this->assertCount(2, $movement_rows);
        $this->assertSame('cash', $movement_rows[0]['payment_method']);
        $this->assertSame('folio_payment', $movement_rows[0]['reference_type']);
        $this->assertSame('card', $movement_rows[1]['payment_method']);
        $this->assertSame('folio_payment', $movement_rows[1]['reference_type']);
    }

    public function testRejectsOverpaymentWithoutPersistingPaymentOrMovement(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Simple');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '207');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 30.00);

        $room_service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $room_service->occupyRoom($room->id, $tariff->id, 30.00, null, 1);
        $folio_service = new FolioService($this->pdo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('supera el saldo');

        try {
            $folio_service->addPayment($stay->id, 'settlement', 30.01, 'cash', 1);
        } finally {
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM folio_payments')->fetchColumn());
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
        }
    }

    public function testRoomTransferPreservesFolioAndStay(): void
    {
        $cat_repo = new RoomCategoryRepository($this->pdo);
        $category = $cat_repo->create('Matrimonial');

        $room_repo = new RoomRepository($this->pdo, $cat_repo);
        $room_203 = $room_repo->create($category->id, '203');
        $room_305 = $room_repo->create($category->id, '305');

        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 50.00);

        $room_service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $room_service->occupyRoom($room_203->id, $tariff->id, 50.00, null, 1);

        $folio_service = new FolioService($this->pdo);
        $folio_service->addPayment($stay->id, 'advance', 20.00, 'cash', 1);

        $room_service->transferRoom(
            from_room_id: $room_203->id,
            to_room_id: $room_305->id,
            reason: 'Aire acondicionado averiado',
            created_by_user_id: 1
        );

        $updated_203 = $room_repo->findById($room_203->id);
        $updated_305 = $room_repo->findById($room_305->id);

        $this->assertEquals('cleaning', $updated_203->status);
        $this->assertEquals('occupied', $updated_305->status);

        $preserved_folio = $folio_service->getOrCreateFolio($stay->id);
        $this->assertEquals(50.00, $preserved_folio->total_charges);
        $this->assertEquals(20.00, $preserved_folio->total_payments);
        $this->assertEquals(30.00, $preserved_folio->pending_balance);
    }
}
