<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\User;
use PDO;

final class UserRepository
{
    private PDO $db;
    private RoleRepository $role_repository;

    public function __construct(?PDO $db = null, ?RoleRepository $role_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->role_repository = $role_repository ?? new RoleRepository($this->db);
    }

    public function findById(int $id): ?User
    {
        $stmt = $this->db->prepare(
            'SELECT id, role_id, username, email, password_hash, full_name, is_active, created_at, updated_at
             FROM users
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, role_id: int|string, username: string, email: string, password_hash: string, full_name: string, is_active: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $role = $this->role_repository->findById((int) $row['role_id']);

        return new User(
            id: (int) $row['id'],
            role_id: (int) $row['role_id'],
            username: $row['username'],
            email: $row['email'],
            password_hash: $row['password_hash'],
            full_name: $row['full_name'],
            is_active: (bool) $row['is_active'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            role: $role,
        );
    }

    public function findByUsername(string $username): ?User
    {
        $stmt = $this->db->prepare(
            'SELECT id, role_id, username, email, password_hash, full_name, is_active, created_at, updated_at
             FROM users
             WHERE username = :username'
        );

        $stmt->execute(['username' => $username]);
        /** @var array{id: int|string, role_id: int|string, username: string, email: string, password_hash: string, full_name: string, is_active: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->findById((int) $row['id']);
    }

    /**
     * @return array<int, User>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, role_id, username, email, password_hash, full_name, is_active, created_at, updated_at
             FROM users
             ORDER BY id ASC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, role_id: int|string, username: string, email: string, password_hash: string, full_name: string, is_active: int|string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): User {
                $role = $this->role_repository->findById((int) $row['role_id']);
                return new User(
                    id: (int) $row['id'],
                    role_id: (int) $row['role_id'],
                    username: $row['username'],
                    email: $row['email'],
                    password_hash: $row['password_hash'],
                    full_name: $row['full_name'],
                    is_active: (bool) $row['is_active'],
                    created_at: $row['created_at'],
                    updated_at: $row['updated_at'],
                    role: $role,
                );
            },
            $rows
        );
    }

    public function create(
        int $role_id,
        string $username,
        string $email,
        string $password_hash,
        string $full_name,
        bool $is_active = true
    ): User {
        $stmt = $this->db->prepare(
            'INSERT INTO users (role_id, username, email, password_hash, full_name, is_active)
             VALUES (:role_id, :username, :email, :password_hash, :full_name, :is_active)'
        );

        $stmt->execute([
            'role_id' => $role_id,
            'username' => $username,
            'email' => $email,
            'password_hash' => $password_hash,
            'full_name' => $full_name,
            'is_active' => $is_active ? 1 : 0,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var User */
        return $this->findById($id);
    }
}
