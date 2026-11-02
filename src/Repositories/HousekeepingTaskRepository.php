<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\HousekeepingTask;
use PDO;

final class HousekeepingTaskRepository
{
    private PDO $db;
    private RoomRepository $room_repository;

    public function __construct(?PDO $db = null, ?RoomRepository $room_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->room_repository = $room_repository ?? new RoomRepository($this->db);
    }

    public function findById(int $id): ?HousekeepingTask
    {
        $stmt = $this->db->prepare(
            'SELECT id, room_id, task_type, status, priority, assigned_to_user_id, started_at, completed_at, notes, created_by_user_id, created_at, updated_at
             FROM housekeeping_tasks
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, room_id: int|string, task_type: string, status: string, priority: string, assigned_to_user_id: int|string|null, started_at: ?string, completed_at: ?string, notes: ?string, created_by_user_id: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $room = $this->room_repository->findById((int) $row['room_id']);

        return new HousekeepingTask(
            id: (int) $row['id'],
            room_id: (int) $row['room_id'],
            task_type: $row['task_type'],
            status: $row['status'],
            priority: $row['priority'],
            assigned_to_user_id: $row['assigned_to_user_id'] !== null ? (int) $row['assigned_to_user_id'] : null,
            started_at: $row['started_at'],
            completed_at: $row['completed_at'],
            notes: $row['notes'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            room: $room,
        );
    }

    /**
     * @return array<int, HousekeepingTask>
     */
    public function findAllActive(): array
    {
        $stmt = $this->db->query(
            'SELECT id, room_id, task_type, status, priority, assigned_to_user_id, started_at, completed_at, notes, created_by_user_id, created_at, updated_at
             FROM housekeeping_tasks
             WHERE status IN ("pending", "in_progress")
             ORDER BY priority DESC, id ASC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, room_id: int|string, task_type: string, status: string, priority: string, assigned_to_user_id: int|string|null, started_at: ?string, completed_at: ?string, notes: ?string, created_by_user_id: int|string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): HousekeepingTask {
                $room = $this->room_repository->findById((int) $row['room_id']);
                return new HousekeepingTask(
                    id: (int) $row['id'],
                    room_id: (int) $row['room_id'],
                    task_type: $row['task_type'],
                    status: $row['status'],
                    priority: $row['priority'],
                    assigned_to_user_id: $row['assigned_to_user_id'] !== null ? (int) $row['assigned_to_user_id'] : null,
                    started_at: $row['started_at'],
                    completed_at: $row['completed_at'],
                    notes: $row['notes'],
                    created_by_user_id: (int) $row['created_by_user_id'],
                    created_at: $row['created_at'],
                    updated_at: $row['updated_at'],
                    room: $room,
                );
            },
            $rows
        );
    }

    public function create(
        int $room_id,
        string $task_type,
        string $priority,
        int $created_by_user_id,
        ?int $assigned_to_user_id = null,
        ?string $notes = null
    ): HousekeepingTask {
        $stmt = $this->db->prepare(
            'INSERT INTO housekeeping_tasks (room_id, task_type, status, priority, assigned_to_user_id, notes, created_by_user_id)
             VALUES (:room_id, :task_type, "pending", :priority, :assigned_to_user_id, :notes, :created_by_user_id)'
        );

        $stmt->execute([
            'room_id' => $room_id,
            'task_type' => $task_type,
            'priority' => $priority,
            'assigned_to_user_id' => $assigned_to_user_id,
            'notes' => $notes,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var HousekeepingTask */
        return $this->findById($id);
    }

    public function startTask(int $task_id, int $user_id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE housekeeping_tasks
             SET status = "in_progress", started_at = :started_at, assigned_to_user_id = COALESCE(assigned_to_user_id, :user_id)
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $task_id,
            'started_at' => date('Y-m-d H:i:s'),
            'user_id' => $user_id,
        ]);
    }

    public function completeTask(int $task_id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE housekeeping_tasks
             SET status = "completed", completed_at = :completed_at
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $task_id,
            'completed_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
