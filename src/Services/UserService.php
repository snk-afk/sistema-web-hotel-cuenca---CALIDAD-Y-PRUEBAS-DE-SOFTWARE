<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\User;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class UserService
{
    private PDO $db;
    private UserRepository $user_repository;
    private RoleRepository $role_repository;

    public function __construct(
        ?PDO $db = null,
        ?UserRepository $user_repository = null,
        ?RoleRepository $role_repository = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->user_repository = $user_repository ?? new UserRepository($this->db);
        $this->role_repository = $role_repository ?? new RoleRepository($this->db);
    }

    public function authenticate(string $username, string $password): User
    {
        if (trim($username) === '' || trim($password) === '') {
            throw new InvalidArgumentException("Debe ingresar usuario y contraseña.");
        }

        $user = $this->user_repository->findByUsername($username);
        if ($user === null) {
            throw new InvalidArgumentException("Credenciales inválidas.");
        }

        if (!$user->is_active) {
            throw new DomainException("La cuenta de usuario está desactivada.");
        }

        if (!password_verify($password, $user->password_hash)) {
            throw new InvalidArgumentException("Credenciales inválidas.");
        }

        return $user;
    }

    public function createUser(
        int $role_id,
        string $username,
        string $email,
        string $password,
        string $full_name
    ): User {
        if (trim($username) === '' || trim($email) === '' || trim($password) === '') {
            throw new InvalidArgumentException("Campos requeridos incompletos.");
        }

        $role = $this->role_repository->findById($role_id);
        if ($role === null) {
            throw new InvalidArgumentException("Rol con ID {$role_id} no encontrado.");
        }

        $existing = $this->user_repository->findByUsername($username);
        if ($existing !== null) {
            throw new DomainException("El nombre de usuario '{$username}' ya está registrado.");
        }

        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        return $this->user_repository->create(
            role_id: $role_id,
            username: $username,
            email: $email,
            password_hash: $password_hash,
            full_name: $full_name,
            is_active: true
        );
    }

    /**
     * @return array<int, User>
     */
    public function listUsers(): array
    {
        return $this->user_repository->findAll();
    }
}
