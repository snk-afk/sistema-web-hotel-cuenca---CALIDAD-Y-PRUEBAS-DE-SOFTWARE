<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\HousekeepingTask;
use App\Repositories\HousekeepingTaskRepository;
use App\Repositories\RoomRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class HousekeepingService
{
    private PDO $db;
    private HousekeepingTaskRepository $task_repository;
    private RoomRepository $room_repository;

    public function __construct(
        ?PDO $db = null,
        ?HousekeepingTaskRepository $task_repository = null,
        ?RoomRepository $room_repository = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->task_repository = $task_repository ?? new HousekeepingTaskRepository($this->db);
        $this->room_repository = $room_repository ?? new RoomRepository($this->db);
    }

    public function createTask(
        int $room_id,
        string $task_type,
        string $priority,
        int $created_by_user_id,
        ?int $assigned_to_user_id = null,
        ?string $notes = null
    ): HousekeepingTask {
        $allowed_types = ['checkout_cleaning', 'deep_cleaning', 'maintenance_repair', 'routine_inspection'];
        if (!in_array($task_type, $allowed_types, true)) {
            throw new InvalidArgumentException("Tipo de tarea de limpieza/mantenimiento inválido: {$task_type}");
        }

        $allowed_priorities = ['low', 'normal', 'high', 'urgent'];
        if (!in_array($priority, $allowed_priorities, true)) {
            throw new InvalidArgumentException("Prioridad inválida: {$priority}");
        }

        $room = $this->room_repository->findById($room_id);
        if ($room === null) {
            throw new InvalidArgumentException("Habitación con ID {$room_id} no encontrada.");
        }

        $is_nested = $this->db->inTransaction();
        if (!$is_nested) {
            $this->db->beginTransaction();
        }

        try {
            if ($task_type === 'maintenance_repair') {
                $this->room_repository->updateStatus($room_id, 'maintenance');
            } elseif ($task_type === 'checkout_cleaning' && $room->status === 'free') {
                $this->room_repository->updateStatus($room_id, 'cleaning');
            }

            $task = $this->task_repository->create(
                room_id: $room_id,
                task_type: $task_type,
                priority: $priority,
                created_by_user_id: $created_by_user_id,
                assigned_to_user_id: $assigned_to_user_id,
                notes: $notes
            );

            if (!$is_nested) {
                $this->db->commit();
            }

            return $task;
        } catch (\Throwable $e) {
            if (!$is_nested && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function startTask(int $task_id, int $user_id): HousekeepingTask
    {
        $task = $this->task_repository->findById($task_id);
        if ($task === null) {
            throw new InvalidArgumentException("Tarea con ID {$task_id} no encontrada.");
        }

        if ($task->status !== 'pending') {
            throw new DomainException("La tarea ya se encuentra en progreso o finalizada.");
        }

        $this->task_repository->startTask($task_id, $user_id);
        /** @var HousekeepingTask */
        return $this->task_repository->findById($task_id);
    }

    public function completeTask(int $task_id): HousekeepingTask
    {
        $task = $this->task_repository->findById($task_id);
        if ($task === null) {
            throw new InvalidArgumentException("Tarea con ID {$task_id} no encontrada.");
        }

        if ($task->status === 'completed') {
            throw new DomainException("La tarea ya fue completada anteriormente.");
        }

        $is_nested = $this->db->inTransaction();
        if (!$is_nested) {
            $this->db->beginTransaction();
        }

        try {
            $this->task_repository->completeTask($task_id);

            if (in_array($task->task_type, ['checkout_cleaning', 'deep_cleaning'], true)) {
                $this->room_repository->updateStatus($task->room_id, 'free');
            } elseif ($task->task_type === 'maintenance_repair') {
                $this->room_repository->updateStatus($task->room_id, 'cleaning');
            }

            if (!$is_nested) {
                $this->db->commit();
            }

            /** @var HousekeepingTask */
            return $this->task_repository->findById($task_id);
        } catch (\Throwable $e) {
            if (!$is_nested && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, HousekeepingTask>
     */
    public function listActiveTasks(): array
    {
        return $this->task_repository->findAllActive();
    }
}
