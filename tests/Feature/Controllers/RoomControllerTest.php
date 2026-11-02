<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\RoomController;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\StayExtensionRepository;
use App\Repositories\StayRepository;
use App\Services\RoomService;
use Tests\Support\MySqlTestCase;

final class RoomControllerTest extends MySqlTestCase
{
    private RoomService $room_service;

    protected function setUp(): void
    {
        parent::setUp();

        $category_repo = new RoomCategoryRepository($this->pdo);
        $category = $category_repo->create('Simple', 'Habitación Simple');

        $room_repo = new RoomRepository($this->pdo, $category_repo);
        $room_repo->create($category->id, '101', 'piso 1');

        $tariff_repo = new RoomTariffRepository($this->pdo);
        $tariff_repo->create($category->id, '12 Horas', 12, 35.00);

        $stay_repo = new StayRepository($this->pdo);
        $extension_repo = new StayExtensionRepository($this->pdo);

        $this->room_service = new RoomService(
            $this->pdo,
            $room_repo,
            $tariff_repo,
            $stay_repo,
            $extension_repo
        );
    }

    public function testIndexReturnsDashboardMapJson(): void
    {
        $controller = new RoomController($this->room_service);

        ob_start();
        $controller->index();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('101', $data['data'][0]['number']);
        $this->assertEquals('free', $data['data'][0]['status']);
    }
}
