<?php

declare(strict_types=1);

namespace App\Models;

final readonly class User
{
    public function __construct(
        public int $id,
        public int $role_id,
        public string $username,
        public string $email,
        public string $password_hash,
        public string $full_name,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
        public ?Role $role = null,
    ) {
    }
}
