<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\HousekeepingService;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class HousekeepingController
{
    private HousekeepingService $housekeeping_service;

    public function __construct(?HousekeepingService $housekeeping_service = null)
    {
        $this->housekeeping_service = $housekeeping_service ?? new HousekeepingService();
    }

    public function index(): void
    {
        $tasks = $this->housekeeping_service->listActiveTasks();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $tasks,
        ]);
    }

    public function handlePost(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $action = is_string($body['action'] ?? null) ? $body['action'] : 'create';
        $task_id = is_numeric($body['task_id'] ?? null) ? (int) $body['task_id'] : (is_numeric($body['id'] ?? null) ? (int) $body['id'] : 0);

        match ($action) {
            'start' => $this->start($task_id),
            'complete' => $this->complete($task_id),
            'create', '' => $this->create(),
            default => (function (): void {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Acción no válida o no especificada.']);
            })(),
        };
    }

    public function create(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $room_id = is_numeric($body['room_id'] ?? null) ? (int) $body['room_id'] : 0;
        $task_type = is_string($body['task_type'] ?? null) ? $body['task_type'] : 'checkout_cleaning';
        $priority = is_string($body['priority'] ?? null) ? $body['priority'] : 'normal';
        $user_id = Session::requireAuth();
        $assigned_to_user_id = is_numeric($body['assigned_to_user_id'] ?? null) ? (int) $body['assigned_to_user_id'] : null;
        $notes = is_string($body['notes'] ?? null) ? $body['notes'] : null;

        try {
            $task = $this->housekeeping_service->createTask(
                room_id: $room_id,
                task_type: $task_type,
                priority: $priority,
                created_by_user_id: $user_id,
                assigned_to_user_id: $assigned_to_user_id,
                notes: $notes
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Tarea de limpieza/mantenimiento registrada exitosamente',
                'data' => $task,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (DomainException $e) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function start(int $id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $user_id = Session::requireAuth();

        try {
            $task = $this->housekeeping_service->startTask($id, $user_id);

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Tarea iniciada exitosamente',
                'data' => $task,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (DomainException $e) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function complete(int $id): void
    {
        try {
            $task = $this->housekeeping_service->completeTask($id);

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Tarea completada. Habitación liberada.',
                'data' => $task,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (DomainException $e) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
