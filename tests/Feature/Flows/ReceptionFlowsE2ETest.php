<?php

declare(strict_types=1);

namespace Tests\Feature\Flows;

use App\Database\Database;
use App\Models\Room;
use App\Models\RoomCategory;
use App\Models\RoomTariff;
use App\Repositories\CashboxShiftRepository;
use App\Repositories\FolioChargeRepository;
use App\Repositories\FolioPaymentRepository;
use App\Repositories\FolioRepository;
use App\Repositories\GuestRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\StayExtensionRepository;
use App\Repositories\StayRepository;
use App\Services\CashboxService;
use App\Services\FolioService;
use App\Services\HousekeepingService;
use App\Services\RoomService;
use App\Services\StoreService;
use DomainException;
use InvalidArgumentException;
use Tests\Support\MySqlTestCase;

final class ReceptionFlowsE2ETest extends MySqlTestCase
{
    private RoomService $room_service;
    private FolioService $folio_service;
    private StoreService $store_service;
    private RoomRepository $room_repository;
    private RoomTariffRepository $tariff_repository;
    private RoomCategoryRepository $category_repository;
    private GuestRepository $guest_repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUserFixture(1, 'reception');
        $this->room_repository = new RoomRepository($this->pdo);
        $this->tariff_repository = new RoomTariffRepository($this->pdo);
        $this->category_repository = new RoomCategoryRepository($this->pdo);
        $this->guest_repository = new GuestRepository($this->pdo);
        $cashbox_service = new CashboxService($this->pdo);
        $cashbox_service->openShift(user_id: 1, initial_cash: 100.00);
        $this->folio_service = new FolioService($this->pdo, null, null, null, null, $cashbox_service);
        $this->store_service = new StoreService($this->pdo);

        $this->room_service = new RoomService(
            db: $this->pdo,
            room_repository: $this->room_repository,
            tariff_repository: $this->tariff_repository,
            stay_repository: new StayRepository($this->pdo),
            extension_repository: new StayExtensionRepository($this->pdo),
            transfer_repository: null,
            folio_service: $this->folio_service,
            housekeeping_service: new HousekeepingService($this->pdo),
            category_repository: $this->category_repository,
            guest_repository: $this->guest_repository
        );
    }

    public function testCheckinWithAllRoomCategories(): void
    {
        // 1. Simple Room
        $simple_cat = $this->category_repository->create('Simple VIP', 'Individual');
        $simple_room = $this->room_repository->create($simple_cat->id, '901', '9');
        $simple_tariff = $this->tariff_repository->create($simple_cat->id, '12 Horas', 12, 40.00);

        $stay_simple = $this->room_service->occupyRoom(
            room_id: $simple_room->id,
            tariff_id: $simple_tariff->id,
            applied_price: 40.00,
            discount_reason: null,
            created_by_user_id: 1
        );

        $this->assertSame('occupied', $this->room_repository->findById($simple_room->id)?->status);
        $this->assertSame(40.00, $stay_simple->applied_price);

        // 2. Matrimonial Room
        $matri_cat = $this->category_repository->create('Matrimonial VIP', 'Doble');
        $matri_room = $this->room_repository->create($matri_cat->id, '902', '9');
        $matri_tariff = $this->tariff_repository->create($matri_cat->id, '24 Horas', 24, 85.00);

        $stay_matri = $this->room_service->occupyRoom(
            room_id: $matri_room->id,
            tariff_id: $matri_tariff->id,
            applied_price: 85.00,
            discount_reason: null,
            created_by_user_id: 1
        );

        $this->assertSame('occupied', $this->room_repository->findById($matri_room->id)?->status);
        $this->assertSame(85.00, $stay_matri->applied_price);

        // 3. Jacuzzi Room
        $jacuzzi_cat = $this->category_repository->create('Jacuzzi Suite VIP', 'Lujo');
        $jacuzzi_room = $this->room_repository->create($jacuzzi_cat->id, '903', '9');
        $jacuzzi_tariff = $this->tariff_repository->create($jacuzzi_cat->id, '12 Horas', 12, 120.00);

        $stay_jacuzzi = $this->room_service->occupyRoom(
            room_id: $jacuzzi_room->id,
            tariff_id: $jacuzzi_tariff->id,
            applied_price: 120.00,
            discount_reason: null,
            created_by_user_id: 1
        );

        $this->assertSame('occupied', $this->room_repository->findById($jacuzzi_room->id)?->status);
        $this->assertSame(120.00, $stay_jacuzzi->applied_price);
    }

    public function testCheckinWithPaymentAdvance(): void
    {
        $cat = $this->category_repository->create('Standard Advance', 'Demo');
        $room = $this->room_repository->create($cat->id, '904', '9');
        $tariff = $this->tariff_repository->create($cat->id, '24 Horas', 24, 100.00);

        $guest = $this->guest_repository->create(
            document_type: 'DNI',
            document_number: '77665544',
            first_name: 'Roberto',
            last_name: 'Palacios'
        );

        $stay = $this->room_service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff->id,
            applied_price: 100.00,
            discount_reason: null,
            created_by_user_id: 1,
            guest_id: $guest->id,
            initial_payment_amount: 40.00,
            payment_method: 'cash'
        );

        $folio = $this->folio_service->getOrCreateFolio($stay->id);

        $this->assertSame(100.00, $folio->total_charges);
        $this->assertSame(40.00, $folio->total_payments);
        $this->assertSame(60.00, $folio->pending_balance);
        $this->assertCount(1, $folio->payments);
        $this->assertSame('advance', $folio->payments[0]->payment_type);
    }

    public function testCheckinWithFullPayment(): void
    {
        $cat = $this->category_repository->create('Standard Full', 'Demo');
        $room = $this->room_repository->create($cat->id, '905', '9');
        $tariff = $this->tariff_repository->create($cat->id, '12 Horas', 12, 50.00);

        $stay = $this->room_service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff->id,
            applied_price: 50.00,
            discount_reason: null,
            created_by_user_id: 1,
            initial_payment_amount: 50.00,
            payment_method: 'yape'
        );

        $folio = $this->folio_service->getOrCreateFolio($stay->id);

        $this->assertSame(50.00, $folio->total_charges);
        $this->assertSame(50.00, $folio->total_payments);
        $this->assertSame(0.00, $folio->pending_balance);
    }

    public function testCheckinIncompatibleTariffCategoryThrowsDomainException(): void
    {
        $cat1 = $this->category_repository->create('Cat A', 'A');
        $cat2 = $this->category_repository->create('Cat B', 'B');

        $room = $this->room_repository->create($cat1->id, '906', '9');
        $tariff_of_cat2 = $this->tariff_repository->create($cat2->id, 'Tariff B', 12, 50.00);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('La tarifa no corresponde a la categoría de esta habitación.');

        $this->room_service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff_of_cat2->id,
            applied_price: 50.00,
            discount_reason: null,
            created_by_user_id: 1
        );
    }

    public function testFullReceptionCycleE2E(): void
    {
        // 1. Setup Room and Guest
        $cat = $this->category_repository->create('Executive Suite', 'Full Cycle');
        $room1 = $this->room_repository->create($cat->id, '910', '9');
        $room2 = $this->room_repository->create($cat->id, '911', '9');
        $tariff = $this->tariff_repository->create($cat->id, '12 Horas', 12, 70.00);

        $guest = $this->guest_repository->create(
            document_type: 'DNI',
            document_number: '88990011',
            first_name: 'Elena',
            last_name: 'Ramos'
        );

        // 2. Check-in Room 910 with S/ 20.00 advance
        $stay = $this->room_service->occupyRoom(
            room_id: $room1->id,
            tariff_id: $tariff->id,
            applied_price: 70.00,
            discount_reason: null,
            created_by_user_id: 1,
            guest_id: $guest->id,
            initial_payment_amount: 20.00,
            payment_method: 'cash'
        );

        $this->assertSame('occupied', $this->room_repository->findById($room1->id)?->status);

        // 3. POS Product Store Charge to Room
        $product_repo = new ProductRepository($this->pdo);
        $product = $product_repo->create(
            name: 'Bebida Energizante',
            sale_price: 7.50,
            cost_price: 3.00,
            stock: 10,
            min_stock: 2,
            sku: 'E2E-BEV'
        );

        $this->store_service->chargeToRoom(
            room_id: $room1->id,
            items_payload: [['product_id' => $product->id, 'quantity' => 2]],
            user_id: 1
        );

        // 4. Extend Stay +2 Hours (S/ 25.00)
        $this->room_service->extendStay(
            room_id: $room1->id,
            added_hours: 2,
            added_price: 25.00,
            created_by_user_id: 1
        );

        // Folio balance should now be: 70 (room) + 15 (store) + 25 (extension) = 110.00 charges - 20 (advance) = 90.00 pending
        $folio = $this->folio_service->getOrCreateFolio($stay->id);
        $this->assertSame(110.00, $folio->total_charges);
        $this->assertSame(20.00, $folio->total_payments);
        $this->assertSame(90.00, $folio->pending_balance);

        // 5. Transfer Room from 910 to 911
        $this->room_service->transferRoom(
            from_room_id: $room1->id,
            to_room_id: $room2->id,
            reason: 'Huésped solicita habitación con mejor vista',
            created_by_user_id: 1
        );

        $this->assertSame('cleaning', $this->room_repository->findById($room1->id)?->status);
        $this->assertSame('occupied', $this->room_repository->findById($room2->id)?->status);

        // 6. Complete Checkout of Room 911
        $this->room_service->checkoutRoom($room2->id);

        $this->assertSame('cleaning', $this->room_repository->findById($room2->id)?->status);
        $updated_folio = $this->folio_service->getOrCreateFolio($stay->id);
        $this->assertSame('closed', $updated_folio->status);

        // 7. Quick Release from Cleaning to Free
        $this->room_service->updateRoomStatus($room2->id, 'free');
        $this->assertSame('free', $this->room_repository->findById($room2->id)?->status);
    }

    public function testOccupiedRoomDashboardMapDisplaysRealGuestName(): void
    {
        $cat = $this->category_repository->create('Deluxe Room', 'Map Test');
        $room = $this->room_repository->create($cat->id, '950', '9');
        $tariff = $this->tariff_repository->create($cat->id, '12 Horas', 12, 55.00);

        $guest = $this->guest_repository->create(
            document_type: 'DNI',
            document_number: '74125896',
            first_name: 'Alejandro',
            last_name: 'Toledo'
        );

        $this->room_service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff->id,
            applied_price: 55.00,
            discount_reason: null,
            created_by_user_id: 1,
            guest_id: $guest->id
        );

        $map = $this->room_service->getDashboardMap();
        $room_entry = null;
        foreach ($map as $entry) {
            if ($entry['id'] === $room->id) {
                $room_entry = $entry;
                break;
            }
        }

        $this->assertNotNull($room_entry);
        $this->assertIsArray($room_entry['current_stay']);
        $this->assertSame('Alejandro Toledo', $room_entry['current_stay']['guest_name']);
        $this->assertSame('74125896', $room_entry['current_stay']['guest_doc']);
        $this->assertSame('DNI', $room_entry['current_stay']['guest_doc_type']);
    }

    public function testCheckoutSettlesPendingBalanceAutomatically(): void
    {
        $cat = $this->category_repository->create('Matrimonial', 'Checkout Settle');
        $room = $this->room_repository->create($cat->id, '960', '9');
        $tariff = $this->tariff_repository->create($cat->id, '12 Horas', 12, 60.00);

        $stay = $this->room_service->occupyRoom(
            room_id: $room->id,
            tariff_id: $tariff->id,
            applied_price: 60.00,
            discount_reason: null,
            created_by_user_id: 1,
            initial_payment_amount: 20.00,
            payment_method: 'cash'
        );

        $folio = $this->folio_service->getOrCreateFolio($stay->id);
        $this->assertSame(40.00, $folio->pending_balance);

        // Checkout with settlement via Yape
        $this->room_service->checkoutRoom(
            room_id: $room->id,
            payment_method: 'yape',
            payment_reference: 'YAPE-998811',
            user_id: 1
        );

        $this->assertSame('cleaning', $this->room_repository->findById($room->id)?->status);
        $closed_folio = $this->folio_service->getOrCreateFolio($stay->id);
        $this->assertSame('closed', $closed_folio->status);
        $this->assertSame(60.00, $closed_folio->total_payments);
        $this->assertSame(0.00, $closed_folio->pending_balance);
    }

    public function testRoomsAndCategoriesAndTariffsManagementFlows(): void
    {
        // 1. Create Category
        $cat = $this->room_service->createCategory('Suite Presidencial Imperial', 'Suite de máximo lujo');
        $this->assertSame('Suite Presidencial Imperial', $cat->name);

        // 2. Create Tariff for Category
        $tariff = $this->room_service->createTariff($cat->id, 'Tarifa 24h VIP', 24, 250.00);
        $this->assertSame(250.00, $tariff->price);

        // 3. Create Room in Category
        $room = $this->room_service->createRoom(
            category_id: $cat->id,
            number: '501',
            floor: '5',
            description: 'Piso 5 con terraza y jacuzzi'
        );
        $this->assertSame('501', $room->number);
        $this->assertSame('free', $room->status);

        // 4. Update Room
        $updatedRoom = $this->room_service->updateRoom(
            id: $room->id,
            category_id: $cat->id,
            number: '501-A',
            floor: '5',
            description: 'Terraza ampliada'
        );
        $this->assertSame('501-A', $updatedRoom->number);

        // 5. Check Catalog has the new entities
        $categories_catalog = $this->room_service->getCategoriesCatalog();
        $this->assertTrue(array_any($categories_catalog, fn($c) => $c['name'] === 'Suite Presidencial Imperial'));

        $tariffs_catalog = $this->room_service->getTariffsCatalog();
        $this->assertTrue(array_any($tariffs_catalog, fn($t) => $t['name'] === 'Tarifa 24h VIP'));
    }
}
