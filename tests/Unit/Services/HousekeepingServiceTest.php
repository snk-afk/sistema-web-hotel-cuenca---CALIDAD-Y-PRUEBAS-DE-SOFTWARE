<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\HousekeepingTaskRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Services\HousekeepingService;
use DomainException;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class HousekeepingServiceTest extends TestCase
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
        ');
    }

    public function testCompleteCheckoutCleaningFreesRoom(): void
    {
        $cat_repo = new RoomCategoryRepository($this->pdo);
        $category = $cat_repo->create('Simple');

        $room_repo = new RoomRepository($this->pdo, $cat_repo);
        $room = $room_repo->create($category->id, '108');
        $room_repo->updateStatus($room->id, 'cleaning');

        $housekeeping_service = new HousekeepingService($this->pdo);
        $task = $housekeeping_service->createTask(
            room_id: $room->id,
            task_type: 'checkout_cleaning',
            priority: 'normal',
            created_by_user_id: 1
        );

        $this->assertEquals('pending', $task->status);

        $started = $housekeeping_service->startTask($task->id, 2);
        $this->assertEquals('in_progress', $started->status);

        $completed = $housekeeping_service->completeTask($task->id);
        $this->assertEquals('completed', $completed->status);

        $updated_room = $room_repo->findById($room->id);
        $this->assertNotNull($updated_room);
        $this->assertEquals('free', $updated_room->status);
    }

    public function testMaintenanceRepairTaskLocksRoomToMaintenance(): void
    {
        $cat_repo = new RoomCategoryRepository($this->pdo);
        $category = $cat_repo->create('Jacuzzi');

        $room_repo = new RoomRepository($this->pdo, $cat_repo);
        $room = $room_repo->create($category->id, '304');

        $housekeeping_service = new HousekeepingService($this->pdo);
        $housekeeping_service->createTask(
            room_id: $room->id,
            task_type: 'maintenance_repair',
            priority: 'urgent',
            created_by_user_id: 1,
            notes: 'Fuga de agua en lavadero'
        );

        $updated_room = $room_repo->findById($room->id);
        $this->assertNotNull($updated_room);
        $this->assertEquals('maintenance', $updated_room->status);
    }
}
