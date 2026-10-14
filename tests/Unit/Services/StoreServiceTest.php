<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\ProductRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Services\CashboxService;
use App\Services\RoomService;
use App\Services\StoreService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class StoreServiceTest extends TestCase
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
            CREATE TABLE products (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sku TEXT UNIQUE,
                name TEXT NOT NULL,
                description TEXT,
                sale_price REAL NOT NULL,
                cost_price REAL DEFAULT 0.0,
                stock INTEGER DEFAULT 0,
                min_stock INTEGER DEFAULT 5,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE store_sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                cashbox_shift_id INTEGER NOT NULL,
                total_amount REAL NOT NULL,
                payment_type TEXT DEFAULT "cash",
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE store_sale_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_sale_id INTEGER NOT NULL,
                product_id INTEGER NOT NULL,
                quantity INTEGER NOT NULL,
                unit_price REAL NOT NULL,
                subtotal REAL NOT NULL
            );

            CREATE TABLE inventory_movements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                product_id INTEGER NOT NULL,
                movement_type TEXT NOT NULL,
                quantity INTEGER NOT NULL,
                previous_stock INTEGER NOT NULL,
                new_stock INTEGER NOT NULL,
                reference TEXT,
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

            CREATE TABLE room_transfers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER NOT NULL,
                from_room_id INTEGER NOT NULL,
                to_room_id INTEGER NOT NULL,
                reason TEXT NOT NULL,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function testSellDirectPOSDeductsStockAndRecordsCashMovement(): void
    {
        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 50.00);

        $store_service = new StoreService($this->pdo);
        $product = $store_service->createProduct('Coca-Cola 500ml', 4.00, 2.00, 10, 2);

        $sale = $store_service->sellDirectPOS(
            user_id: 1,
            items_payload: [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            payment_method: 'cash'
        );

        $this->assertEquals(8.00, $sale->total_amount);

        $prod_repo = new ProductRepository($this->pdo);
        $updated_product = $prod_repo->findById($product->id);
        $this->assertNotNull($updated_product);
        $this->assertEquals(8, $updated_product->stock); // 10 - 2

        $current_shift = $cashbox_service->getCurrentShift(1);
        $this->assertNotNull($current_shift);
        $this->assertEquals(58.00, $current_shift->expected_cash); // 50 + 8
    }

    public function testSellDirectPOSThrowsExceptionWhenStockIsInsufficient(): void
    {
        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 50.00);

        $store_service = new StoreService($this->pdo);
        $product = $store_service->createProduct('Agua 1L', 3.00, 1.00, 1, 2);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('insuficiente');

        $store_service->sellDirectPOS(
            user_id: 1,
            items_payload: [
                ['product_id' => $product->id, 'quantity' => 5],
            ]
        );
    }

    public function testSellDirectPOSRecordsDigitalMethodInCashbox(): void
    {
        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 50.00);

        $store_service = new StoreService($this->pdo);
        $product = $store_service->createProduct('Agua 1L', 8.00, 2.00, 2, 1);

        $store_service->sellDirectPOS(
            user_id: 1,
            items_payload: [['product_id' => $product->id, 'quantity' => 1]],
            payment_method: 'yape'
        );

        $shift = $cashbox_service->getCurrentShift(1);
        $this->assertNotNull($shift);
        $this->assertSame(8.00, $shift->expected_yape);
        $movement = $this->pdo->query(
            'SELECT payment_method, reference_type FROM cash_movements'
        )->fetch();
        $this->assertSame('yape', $movement['payment_method']);
        $this->assertSame('store_sale', $movement['reference_type']);
    }

    public function testChargeToRoomDeductsStockAndAddsFolioCharge(): void
    {
        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 50.00);

        $cat_repo = new RoomCategoryRepository($this->pdo);
        $category = $cat_repo->create('Jacuzzi');
        $room_repo = new RoomRepository($this->pdo, $cat_repo);
        $room = $room_repo->create($category->id, '302');
        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($category->id, '12 Horas', 12, 80.00);

        $room_service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $room_service->occupyRoom($room->id, $tariff->id, 80.00, null, 1);

        $store_service = new StoreService($this->pdo);
        $product = $store_service->createProduct('Cerveza Cusqueña', 8.00, 4.00, 20, 5);

        $store_service->chargeToRoom(
            room_id: $room->id,
            items_payload: [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
            user_id: 1
        );

        $prod_repo = new ProductRepository($this->pdo);
        $updated_product = $prod_repo->findById($product->id);
        $this->assertNotNull($updated_product);
        $this->assertEquals(18, $updated_product->stock); // 20 - 2
    }
}
