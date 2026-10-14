<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\FolioController;
use App\Repositories\FolioChargeRepository;
use App\Repositories\FolioPaymentRepository;
use App\Repositories\FolioRepository;
use App\Repositories\GuestRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\StayRepository;
use App\Services\FolioService;
use App\Services\RoomService;
use Tests\Support\MySqlTestCase;

final class FolioControllerTest extends MySqlTestCase
{
    private FolioService $folio_service;
    private int $stay_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUserFixture(1, 'folio');
        $cat_repo = new RoomCategoryRepository($this->pdo);
        $cat = $cat_repo->create('Jacuzzi');

        $room_repo = new RoomRepository($this->pdo, $cat_repo);
        $room = $room_repo->create($cat->id, '301');

        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff = $tariff_repo->create($cat->id, '12 Horas', 12, 100.00);

        $room_service = new RoomService($this->pdo, $room_repo, $tariff_repo);
        $stay = $room_service->occupyRoom($room->id, $tariff->id, 100.00, null, 1);
        $this->stay_id = $stay->id;

        $this->folio_service = new FolioService($this->pdo);
    }

    public function testShowReturnsFolioDetailsJson(): void
    {
        $controller = new FolioController($this->folio_service);

        ob_start();
        $controller->show($this->stay_id);
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(100.00, $data['data']['total_charges']);
        $this->assertEquals(100.00, $data['data']['pending_balance']);
    }
}
