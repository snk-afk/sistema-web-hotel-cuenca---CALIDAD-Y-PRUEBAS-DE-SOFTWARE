<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use Tests\Support\MySqlTestCase;

final class RoomRepositoryTest extends MySqlTestCase
{
    public function testCreateAndFindRoom(): void
    {
        $category_repo = new RoomCategoryRepository($this->pdo());
        $category = $category_repo->create('Jacuzzi', 'Habitación con Jacuzzi');

        $room_repo = new RoomRepository($this->pdo(), $category_repo);
        $room = $room_repo->create($category->id, '205', 'piso 2', 'Vista panorámica');

        $this->assertEquals('205', $room->number);
        $this->assertEquals('free', $room->status);
        $this->assertEquals('Jacuzzi', $room->category?->name);

        $found = $room_repo->findById($room->id);
        $this->assertNotNull($found);
        $this->assertEquals('205', $found->number);
    }
}
