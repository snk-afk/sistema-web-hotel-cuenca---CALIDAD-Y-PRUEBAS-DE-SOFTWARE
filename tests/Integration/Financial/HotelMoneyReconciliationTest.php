<?php

declare(strict_types=1);

namespace Tests\Integration\Financial;

use App\Repositories\GuestRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Services\CashboxService;
use App\Services\ExpenseService;
use App\Services\FolioService;
use App\Services\ReservationService;
use App\Services\RoomService;
use App\Services\StoreService;
use Tests\Support\MySqlTestCase;

final class HotelMoneyReconciliationTest extends MySqlTestCase
{
    private CashboxService $cashbox_service;
    private FolioService $folio_service;
    private RoomRepository $room_repository;
    private RoomCategoryRepository $category_repository;
    private RoomTariffRepository $tariff_repository;
    private GuestRepository $guest_repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUserFixture(1, 'hotel-money');
        $this->cashbox_service = new CashboxService($this->pdo());
        $this->cashbox_service->openShift(1, 100.00);
        $this->folio_service = new FolioService($this->pdo(), null, null, null, null, $this->cashbox_service);
        $this->room_repository = new RoomRepository($this->pdo());
        $this->category_repository = new RoomCategoryRepository($this->pdo());
        $this->tariff_repository = new RoomTariffRepository($this->pdo());
        $this->guest_repository = new GuestRepository($this->pdo());
    }

    public function testReservationCheckinFolioCheckoutAndCloseReconcileByPaymentMethod(): void
    {
        $category = $this->category_repository->create('Conciliación', 'Prueba financiera');
        $room = $this->room_repository->create($category->id, '701', '7');
        $tariff = $this->tariff_repository->create($category->id, '24 horas', 24, 100.00);
        $guest = $this->guest_repository->create('DNI', '70000001', 'Huésped', 'Conciliación');
        $reservation_service = new ReservationService($this->pdo(), null, null, $this->guest_repository, null, null, null, null, $this->cashbox_service, $this->folio_service);

        $reservation = $reservation_service->createReservation(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: '2030-01-10 15:00:00',
            advance_payment: 40.00,
            created_by_user_id: 1,
            payment_parts: [
                ['amount' => 20.00, 'payment_method' => 'cash'],
                ['amount' => 20.00, 'payment_method' => 'yape', 'reference_number' => 'YAPE-ADV-1'],
            ],
        );

        $stay = $reservation_service->convertReservationToStay($reservation->id, 1);
        $this->folio_service->addCharge($stay->id, 'service', 'Servicio adicional', 1, 15.00, 1);
        $room_service = new RoomService(db: $this->pdo(), folio_service: $this->folio_service);
        $room_service->checkoutRoom(
            room_id: $room->id,
            user_id: 1,
            payment_parts: [
                ['amount' => 50.00, 'payment_method' => 'card', 'reference_number' => 'CARD-CHK-1'],
                ['amount' => 25.00, 'payment_method' => 'transfer', 'reference_number' => 'TRF-CHK-1'],
            ],
        );

        $closed_shift = $this->cashbox_service->closeShift(
            user_id: 1,
            real_cash_declared: 120.00,
            real_yape_declared: 20.00,
            real_card_declared: 50.00,
            real_transfer_declared: 25.00,
        );

        self::assertSame('balanced', $closed_shift->balance_status);
        self::assertSame(0.0, $closed_shift->difference);
        self::assertSame(2, (int) $this->pdo()->query('SELECT COUNT(*) FROM reservation_payments')->fetchColumn());
        self::assertSame(4, (int) $this->pdo()->query('SELECT COUNT(*) FROM folio_payments')->fetchColumn());

        $movement_totals = $this->pdo()->query(
            "SELECT payment_method, ROUND(SUM(CASE WHEN type = 'income' THEN amount ELSE -amount END), 2) AS total
             FROM cash_movements GROUP BY payment_method ORDER BY payment_method"
        )->fetchAll();
        $totals = [];
        foreach ($movement_totals as $row) {
            $totals[$row['payment_method']] = (float) $row['total'];
        }

        self::assertSame(20.0, $totals['cash']);
        self::assertSame(20.0, $totals['yape']);
        self::assertSame(50.0, $totals['card']);
        self::assertSame(25.0, $totals['transfer']);
        self::assertSame('closed', $this->folio_service->getOrCreateFolio($stay->id)->status);
    }

    public function testPosExpenseAndOtherIncomeAreIncludedExactlyOnceInCashbox(): void
    {
        $product = (new ProductRepository($this->pdo()))->create(
            name: 'Agua conciliación',
            sale_price: 10.00,
            cost_price: 4.00,
            stock: 5,
            min_stock: 1,
            sku: 'CONC-001',
        );
        (new StoreService($this->pdo()))->sellDirectPOS(1, [['product_id' => $product->id, 'quantity' => 2]], 'cash');
        $expense_service = new ExpenseService($this->pdo());
        $expense_service->recordExpense(1, 'supplies', 'Insumos', 5.00, 'yape');
        $expense_service->recordOtherIncome(1, 'other_income', 'Ingreso adicional', 5.00, 'yape');

        $shift = $this->cashbox_service->closeShift(
            user_id: 1,
            real_cash_declared: 120.00,
            real_yape_declared: 0.00,
        );

        self::assertSame('balanced', $shift->balance_status);
        self::assertSame(1, (int) $this->pdo()->query("SELECT COUNT(*) FROM inventory_movements WHERE movement_type = 'out'")->fetchColumn());
        self::assertSame(3, (int) $this->pdo()->query('SELECT stock FROM products WHERE id = ' . $product->id)->fetchColumn());
    }
}
