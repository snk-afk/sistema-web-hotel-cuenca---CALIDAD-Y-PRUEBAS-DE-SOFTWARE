<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use App\Services\UserService;
use DomainException;
use InvalidArgumentException;

final class UserController
{
    private UserService $user_service;

    public function __construct(?UserService $user_service = null)
    {
        $this->user_service = $user_service ?? new UserService();
    }

    public function index(): void
    {
        $users = $this->user_service->listUsers();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => array_map(fn (User $u) => $this->sanitizeUser($u), $users),
        ]);
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

        $role_id = is_numeric($body['role_id'] ?? null) ? (int) $body['role_id'] : 0;
        $username = is_string($body['username'] ?? null) ? $body['username'] : '';
        $email = is_string($body['email'] ?? null) ? $body['email'] : '';
        $password = is_string($body['password'] ?? null) ? $body['password'] : '';
        $full_name = is_string($body['full_name'] ?? null) ? $body['full_name'] : '';

        try {
            $user = $this->user_service->createUser(
                role_id: $role_id,
                username: $username,
                email: $email,
                password: $password,
                full_name: $full_name
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Usuario creado exitosamente',
                'data' => $this->sanitizeUser($user),
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

    /**
     * Elimina el password_hash de la respuesta JSON (AGENTS.md §5.9 — no filtrar secretos).
     *
     * @return array<string, mixed>
     */
    private function sanitizeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'role_id' => $user->role_id,
            'username' => $user->username,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'is_active' => $user->is_active,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
            'role' => $user->role?->name,
        ];
    }
}
