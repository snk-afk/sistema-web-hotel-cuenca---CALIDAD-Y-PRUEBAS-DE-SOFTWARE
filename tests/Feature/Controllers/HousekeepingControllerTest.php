<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\HousekeepingController;
use App\Repositories\HousekeepingTaskRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Services\HousekeepingService;
use Tests\Support\MySqlTestCase;

final class HousekeepingControllerTest extends MySqlTestCase
{
    private HousekeepingService $housekeeping_service;
    private int $task_id;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUserFixture(1, 'housekeeping');
        $cat_repo = new RoomCategoryRepository($this->pdo);
        $cat = $cat_repo->create('Doble');

        $room_repo = new RoomRepository($this->pdo, $cat_repo);
        $room = $room_repo->create($cat->id, '109');

        $task_repo = new HousekeepingTaskRepository($this->pdo, $room_repo);
        $task = $task_repo->create($room->id, 'checkout_cleaning', 'normal', 1);
        $this->task_id = $task->id;

        $this->housekeeping_service = new HousekeepingService($this->pdo, $task_repo, $room_repo);
    }

    public function testIndexReturnsActiveTasksJson(): void
    {
        $controller = new HousekeepingController($this->housekeeping_service);

        ob_start();
        $controller->index();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('pending', $data['data'][0]['status']);
    }

    public function testHandlePostStartsAndCompletesTask(): void
    {
        $_SESSION['user_id'] = 1;

        $controller = new HousekeepingController($this->housekeeping_service);

        // 1. Start task via handlePost
        $_POST = ['action' => 'start', 'task_id' => $this->task_id];
        ob_start();
        $controller->handlePost();
        $outputStart = (string) ob_get_clean();

        $dataStart = json_decode($outputStart, true);
        $this->assertIsArray($dataStart);
        $this->assertEquals('success', $dataStart['status']);
        $this->assertEquals('in_progress', $dataStart['data']['status']);

        // 2. Complete task via handlePost
        $_POST = ['action' => 'complete', 'task_id' => $this->task_id];
        ob_start();
        $controller->handlePost();
        $outputComplete = (string) ob_get_clean();

        $dataComplete = json_decode($outputComplete, true);
        $this->assertIsArray($dataComplete);
        $this->assertEquals('success', $dataComplete['status']);
        $this->assertEquals('completed', $dataComplete['data']['status']);

        $_POST = [];
        unset($_SESSION['user_id']);
    }
}
