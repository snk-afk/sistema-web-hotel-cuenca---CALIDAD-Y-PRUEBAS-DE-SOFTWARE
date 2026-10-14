<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\ReservationController;
use App\Repositories\GuestRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\StayGuestRepository;
use App\Repositories\StayRepository;
use App\Services\ReservationService;
use App\Services\ReservationSettlementService;
use App\Services\CashboxService;
use Tests\Support\MySqlTestCase;

final class ReservationControllerTest extends MySqlTestCase
{
    private ReservationService $service;
    private RoomRepository $room_repo;
    private GuestRepository $guest_repo;
    private RoomTariffRepository $tariff_repo;
    private RoomCategoryRepository $category_repo;
    private ReservationRepository $reservation_repo;
    private ReservationSettlementService $settlement_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUserFixture(1, 'reservation');
        (new CashboxService($this->pdo))->openShift(user_id: 1, initial_cash: 100.00);
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $this->category_repo = new RoomCategoryRepository($this->pdo);
        $this->room_repo = new RoomRepository($this->pdo, $this->category_repo);
        $this->tariff_repo = new RoomTariffRepository($this->pdo);
        $this->guest_repo = new GuestRepository($this->pdo);
        $this->reservation_repo = new ReservationRepository($this->pdo, $this->room_repo, $this->guest_repo, $this->tariff_repo);
        $stay_repo = new StayRepository($this->pdo);
        $stay_guest_repo = new StayGuestRepository($this->pdo, $this->guest_repo);

        $this->service = new ReservationService(
            db: $this->pdo,
            reservation_repository: $this->reservation_repo,
            room_repository: $this->room_repo,
            guest_repository: $this->guest_repo,
            tariff_repository: $this->tariff_repo,
            stay_repository: $stay_repo,
            stay_guest_repository: $stay_guest_repo
        );
        $this->settlement_service = new ReservationSettlementService($this->pdo);
    }

    public function testIndexReturnsReservationsAndMetrics(): void
    {
        $cat = $this->category_repo->create('Doble');
        $room = $this->room_repo->create($cat->id, '202');
        $tariff = $this->tariff_repo->create($cat->id, '12h', 12, 60.0);
        $guest = $this->guest_repo->create('DNI', '99112233', 'Mariana Paz');

        $this->service->createReservation(
            $room->id,
            $guest->id,
            $tariff->id,
            '2026-08-25 10:00:00',
            30.0,
            1,
            null,
            [['amount' => 30.0, 'payment_method' => 'cash']]
        );

        $controller = new ReservationController(
            reservation_service: $this->service,
            settlement_service: $this->settlement_service,
        );

        ob_start();
        $controller->index();
        $output = (string) ob_get_clean();

        $json = json_decode($output, true);
        $this->assertEquals('success', $json['status']);
        $this->assertCount(1, $json['data']);
        $this->assertEquals('Mariana Paz', $json['data'][0]['guest_name']);
        $this->assertArrayHasKey('metrics', $json);
        $this->assertEquals(1, $json['metrics']['total_reservations']);
    }

    public function testHandlePostCreatesReservation(): void
    {
        $_SESSION['user_id'] = 1;

        $cat = $this->category_repo->create('Suite');
        $room = $this->room_repo->create($cat->id, '305');
        $tariff = $this->tariff_repo->create($cat->id, '24h', 24, 150.0);
        $guest = $this->guest_repo->create('DNI', '88223344', 'Gonzalo Vargas');

        $_POST = [
            'action' => 'create',
            'room_id' => $room->id,
            'guest_id' => $guest->id,
            'tariff_id' => $tariff->id,
            'check_in_at' => '2026-08-26 14:00:00',
            'advance_payment' => '50.00',
            'payment_parts' => [[
                'amount' => '50.00',
                'payment_method' => 'cash',
            ]],
            'notes' => 'Piso alto',
        ];

        $controller = new ReservationController(
            reservation_service: $this->service,
            settlement_service: $this->settlement_service,
        );

        ob_start();
        $controller->handlePost();
        $output = (string) ob_get_clean();

        $json = json_decode($output, true);
        $this->assertEquals('success', $json['status']);
        $this->assertEquals('confirmed', $json['data']['status']);

        unset($_SESSION['user_id']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        parent::tearDown();
    }

    public function testHandlePostCheckinAndCancel(): void
    {
        $_SESSION['user_id'] = 1;

        $cat = $this->category_repo->create('Simple');
        $room = $this->room_repo->create($cat->id, '109');
        $tariff = $this->tariff_repo->create($cat->id, '12h', 12, 40.0);
        $guest = $this->guest_repo->create('DNI', '77334455', 'Sonia Ortiz');

        $res = $this->service->createReservation(
            $room->id,
            $guest->id,
            $tariff->id,
            date('Y-m-d H:i:s'),
            20.0,
            1,
            null,
            [['amount' => 20.0, 'payment_method' => 'cash']]
        );

        $controller = new ReservationController(
            reservation_service: $this->service,
            settlement_service: $this->settlement_service,
        );

        // Checkin action
        $_POST = [
            'action' => 'checkin',
            'reservation_id' => $res->id,
        ];

        ob_start();
        $controller->handlePost();
        $output = (string) ob_get_clean();

        $json = json_decode($output, true);
        $this->assertEquals('success', $json['status']);
        $this->assertArrayHasKey('stay', $json['data']);

        // Create another and cancel
        $res2 = $this->service->createReservation($room->id, $guest->id, $tariff->id, '2026-10-30 12:00:00', 0.0, 1);
        $_POST = [
            'action' => 'cancel',
            'reservation_id' => $res2->id,
            'reason' => 'Cancelación solicitada por huésped',
        ];

        ob_start();
        $controller->handlePost();
        $output2 = (string) ob_get_clean();

        $json2 = json_decode($output2, true);
        $this->assertEquals('success', $json2['status']);

        unset($_SESSION['user_id']);
    }

    public function testHandlePostNoShowDuringGraceReturnsConflict(): void
    {
        $_SESSION['user_id'] = 1;

        $cat = $this->category_repo->create('No Show');
        $room = $this->room_repo->create($cat->id, '210');
        $tariff = $this->tariff_repo->create($cat->id, '12h', 12, 40.0);
        $guest = $this->guest_repo->create('DNI', '77334456', 'No Show Guest');
        $res = $this->service->createReservation(
            $room->id,
            $guest->id,
            $tariff->id,
            date('Y-m-d H:i:s', time() - 3600),
            0.0,
            1,
        );

        $_POST = [
            'action' => 'no_show',
            'reservation_id' => $res->id,
            'reason' => 'No se presentó aún en ventana de gracia',
        ];

        $controller = new ReservationController(
            reservation_service: $this->service,
            settlement_service: $this->settlement_service,
        );

        ob_start();
        $controller->handlePost();
        $output = (string) ob_get_clean();
        $json = json_decode($output, true);

        self::assertSame(409, http_response_code());
        self::assertSame('error', $json['status']);
        self::assertStringContainsString('dos horas', $json['message']);
    }

    public function testApproveRefundReturnsSettlementAndMovements(): void
    {
        $_SESSION['user_id'] = 1;

        $cat = $this->category_repo->create('Refund Controller');
        $room = $this->room_repo->create($cat->id, '211');
        $tariff = $this->tariff_repo->create($cat->id, '24h', 24, 120.0);
        $guest = $this->guest_repo->create('DNI', '77334457', 'Refund Controller Guest');
        $res = $this->service->createReservation(
            $room->id,
            $guest->id,
            $tariff->id,
            date('Y-m-d H:i:s', time() + 172800),
            30.0,
            1,
            null,
            [['amount' => 30.0, 'payment_method' => 'cash']]
        );
        $settlement = $this->settlement_service->requestCancellation(
            $res->id,
            1,
            'Cancelación para probar devolución'
        );

        $_POST = [
            'cancellation_id' => $settlement->id,
            'reason' => 'Autorización de supervisor',
            'reference_number' => 'REF-CONTROLLER-001',
        ];

        $controller = new ReservationController(
            reservation_service: $this->service,
            settlement_service: $this->settlement_service,
        );

        ob_start();
        $controller->approveRefund();
        $output = (string) ob_get_clean();
        $json = json_decode($output, true);

        self::assertSame(200, http_response_code());
        self::assertSame('success', $json['status']);
        self::assertSame('completed', $json['data']['cancellation']['status']);
        self::assertCount(1, $json['data']['refunds']);
        self::assertCount(1, $json['data']['movements']);
    }
}
