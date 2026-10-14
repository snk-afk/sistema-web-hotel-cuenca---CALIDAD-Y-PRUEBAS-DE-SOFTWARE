<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\HousekeepingTaskRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use Tests\Support\MySqlTestCase;

final class HousekeepingTaskRepositoryTest extends MySqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUserFixture(1, 'housekeeping');
    }

    public function testCreateAndListActiveTasks(): void
    {
        $cat_repo = new RoomCategoryRepository($this->pdo());
        $cat = $cat_repo->create('Matrimonial');
        $room_repo = new RoomRepository($this->pdo(), $cat_repo);
        $room = $room_repo->create($cat->id, '202');

        $repo = new HousekeepingTaskRepository($this->pdo(), $room_repo);
        $task = $repo->create($room->id, 'checkout_cleaning', 'normal', 1);

        $this->assertEquals('pending', $task->status);

        $active = $repo->findAllActive();
        $this->assertCount(1, $active);
        $this->assertEquals($task->id, $active[0]->id);
    }
}
