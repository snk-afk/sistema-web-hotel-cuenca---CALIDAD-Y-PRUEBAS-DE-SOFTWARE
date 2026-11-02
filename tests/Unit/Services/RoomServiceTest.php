<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomTariff;
use App\Models\Stay;
use App\Models\StayExtension;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\StayExtensionRepository;
use App\Repositories\StayRepository;
use App\Services\RoomService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class RoomServiceTest extends TestCase
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
        $this->pdo->exec("INSERT INTO cashbox_shifts (user_id, opened_at, initial_cash, status) VALUES (1, datetime('now'), 100.0, 'open')");
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

            CREATE TABLE reservations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_id INTEGER NOT NULL,
                guest_id INTEGER NOT NULL,
                room_tariff_id INTEGER NOT NULL,
                start_time TEXT NOT NULL,
                end_time TEXT NOT NULL,
                deposit_amount REAL DEFAULT 0,
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

            CREATE TABLE room_transfers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER NOT NULL,
                from_room_id INTEGER NOT NULL,
                to_room_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
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
        ');
    }

    public function testOccupyRoomChangesStatusToOccupiedAndCalculatesCheckoutTime(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Matrimonial', 'Habitación doble');

        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '102', 'piso 1');

        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 45.00);

        $service = new RoomService($this->pdo, $room_repo, $tariff_repo);

        $stay = $service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff->id,
            applied_price: 45.00,
            discount_reason: null,
            created_by_user_id: 1,
            custom_check_in_time: '2026-08-21 10:00:00'
        );

        $updated_room = $room_repo->findById($room->id);

        $this->assertNotNull($updated_room);
        $this->assertEquals('occupied', $updated_room->status);
        $this->assertEquals('2026-08-21 10:00:00', $stay->check_in_at);
        $this->assertEquals('2026-08-21 22:00:00', $stay->expected_check_out_at);
        $this->assertEquals(45.00, $stay->applied_price);
    }

    public function testOccupyRoomThrowsExceptionWhenRoomIsNotFree(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Matrimonial');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '103');
        $room_repo->updateStatus($room->id, 'occupied');

        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 45.00);

        $service = new RoomService($this->pdo, $room_repo, $tariff_repo);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('no está libre');

        $service->occupyRoom($room->id, $tariff->id, 45.00, null, 1);
    }

    public function testOccupyRoomThrowsExceptionWhenDiscountHasNoReason(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Matrimonial');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '104');

        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 60.00);

        $service = new RoomService($this->pdo, $room_repo, $tariff_repo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('motivo');

        $service->occupyRoom($room->id, $tariff->id, 50.00, null, 1);
    }

    public function testExtendStayUpdatesExpectedCheckoutAndCreatesExtensionRecord(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Matrimonial');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '105');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 45.00);

        $service = new RoomService($this->pdo, $room_repo, $tariff_repo);

        $stay = $service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff->id,
            applied_price: 45.00,
            discount_reason: null,
            created_by_user_id: 1,
            custom_check_in_time: '2026-08-21 10:00:00'
        );

        $extension = $service->extendStay(
            room_id: $room->id,
            added_hours: 2,
            added_price: 10.00,
            created_by_user_id: 1
        );

        $updated_room = $room_repo->findById($room->id);

        $this->assertNotNull($updated_room->current_stay);
        $this->assertEquals('2026-08-22 00:00:00', $updated_room->current_stay->expected_check_out_at);
        $this->assertEquals(2, $extension->added_hours);
        $this->assertEquals(10.00, $extension->added_price);
    }

    public function testCheckoutRoomChangesStatusToCleaning(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Matrimonial');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '106');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 45.00);

        $service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $service->occupyRoom($room->id, $tariff->id, 45.00, null, 1);

        $service->checkoutRoom($room->id);

        $updated_room = $room_repo->findById($room->id);
        $this->assertEquals('cleaning', $updated_room->status);
    }

    public function testDirectCheckInCreatesFolioAndRecordsMixedInitialPayment(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Suite');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '109');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 45, 45.00);

        $service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff->id,
            applied_price: 45.00,
            discount_reason: null,
            created_by_user_id: 1,
            initial_payment_amount: 45.00,
            payment_parts: [
                ['amount' => 20.00, 'payment_method' => 'yape', 'reference_number' => 'YAPE-1'],
                ['amount' => 25.00, 'payment_method' => 'card', 'reference_number' => 'POS-1'],
            ]
        );

        $folio = $service->getFolioService()->getOrCreateFolio($stay->id);
        $this->assertSame(45.00, $folio->total_charges);
        $this->assertSame(45.00, $folio->total_payments);
        $this->assertSame(0.00, $folio->pending_balance);
        $this->assertCount(2, $folio->payments);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
    }

    public function testCheckoutRequiresExactPaymentAndClosesFolio(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Suite');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '110');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 50, 50.00);

        $service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $service->occupyRoom($room->id, $tariff->id, 50.00, null, 1);

        $service->checkoutRoom(
            room_id: $room->id,
            user_id: 1,
            payment_parts: [
                ['amount' => 30.00, 'payment_method' => 'cash'],
                ['amount' => 20.00, 'payment_method' => 'card', 'reference_number' => 'POS-2'],
            ]
        );

        $updated_room = $room_repo->findById($room->id);
        $this->assertSame('cleaning', $updated_room->status);
        $folio = $service->getFolioService()->getOrCreateFolio($stay->id);
        $this->assertSame('closed', $folio->status);
        $this->assertSame(0.00, $folio->pending_balance);
        $this->assertSame(2, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
    }

    public function testCheckoutRejectsUnderpaymentAndKeepsStayOpen(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Suite');
        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room = $room_repo->create($category->id, '111');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 50, 50.00);

        $service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $service->occupyRoom($room->id, $tariff->id, 50.00, null, 1);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('exactamente');

        try {
            $service->checkoutRoom(
                room_id: $room->id,
                user_id: 1,
                payment_parts: [['amount' => 20.00, 'payment_method' => 'cash']]
            );
        } finally {
            $updated_room = $room_repo->findById($room->id);
            $this->assertSame('occupied', $updated_room->status);
            $folio = $service->getFolioService()->getOrCreateFolio($stay->id);
            $this->assertSame('open', $folio->status);
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM folio_payments')->fetchColumn());
            $this->assertSame(0, (int) $this->pdo->query('SELECT COUNT(*) FROM cash_movements')->fetchColumn());
        }
    }

    public function testCalculateSecondsRemainingReturnsPositiveOrNegativeTime(): void
    {
        $service = new RoomService($this->pdo);

        $remaining = $service->calculateSecondsRemaining(
            expected_check_out_at: '2026-08-21 22:00:00',
            now: '2026-08-21 20:00:00'
        );

        $this->assertEquals(7200, $remaining);

        $overdue = $service->calculateSecondsRemaining(
            expected_check_out_at: '2026-08-21 22:00:00',
            now: '2026-08-21 22:15:00'
        );

        $this->assertEquals(-900, $overdue);
    }

    public function testCreateAndUpdateRoomSuccessfully(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo);
        $cat1 = $category_repo->create('Simple Standard');
        $cat2 = $category_repo->create('Suite Presidencial');

        $service = new RoomService($this->pdo);

        $room = $service->createRoom(
            category_id: $cat1->id,
            number: '301',
            floor: '3',
            description: 'Habitación con vista al jardín'
        );

        $this->assertSame('301', $room->number);
        $this->assertSame('3', $room->floor);
        $this->assertSame('free', $room->status);

        $updated = $service->updateRoom(
            id: $room->id,
            category_id: $cat2->id,
            number: '301-B',
            floor: '3',
            description: 'Habitación remodelada'
        );

        $this->assertSame('301-B', $updated->number);
        $this->assertSame($cat2->id, $updated->room_category_id);
    }

    public function testCreateAndUpdateCategoryAndTariff(): void
    {
        $service = new RoomService($this->pdo);

        $category = $service->createCategory('Bungalow Familiar', 'Cabaña independiente');
        $this->assertSame('Bungalow Familiar', $category->name);

        $updatedCat = $service->updateCategory($category->id, 'Bungalow Deluxe', 'Cabaña VIP', 1);
        $this->assertSame('Bungalow Deluxe', $updatedCat->name);

        $tariff = $service->createTariff($category->id, 'Fin de Semana', 24, 180.00);
        $this->assertSame(180.00, $tariff->price);
        $this->assertSame(24, $tariff->duration_hours);

        $updatedTariff = $service->updateTariff($tariff->id, $category->id, 'Fin de Semana Promo', 24, 160.00, 1);
        $this->assertSame(160.00, $updatedTariff->price);
        $this->assertSame('Fin de Semana Promo', $updatedTariff->name);
    }

    public function testCheckInBlocksWhenOverlappingWithFutureReservation(): void
    {
        $catRepo = new RoomCategoryRepository($this->pdo);
        $roomRepo = new RoomRepository($this->pdo);
        $tariffRepo = new RoomTariffRepository($this->pdo);

        $cat = $catRepo->create('Simple', 'Básica');
        $room = $roomRepo->create($cat->id, '401', '4');
        $tariff12h = $tariffRepo->create($cat->id, 'Tarifa 12 Horas', 12, 60.0);

        // Agendar una reserva futura hoy a las 20:00 hasta mañana 08:00
        $today = date('Y-m-d');
        $resStart = "{$today} 20:00:00";
        $resEnd = date('Y-m-d H:i:s', strtotime("{$today} 20:00:00 +12 hours"));

        $this->pdo->exec("
            INSERT INTO reservations (room_id, guest_id, room_tariff_id, start_time, end_time, deposit_amount, status, created_by_user_id)
            VALUES ({$room->id}, 1, {$tariff12h->id}, '{$resStart}', '{$resEnd}', 20.0, 'confirmed', 1)
        ");

        $service = new RoomService($this->pdo);

        // Intento de check-in hoy a las 14:00 por 12h (terminaría a las 02:00 de mañana -> choca con las 20:00)
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('se cruza con una reserva activa o futura');

        $service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff12h->id,
            applied_price: 60.0,
            discount_reason: null,
            created_by_user_id: 1,
            guest_id: 1,
            custom_check_in_time: "{$today} 14:00:00",
            custom_check_out_time: null
        );
    }
}
