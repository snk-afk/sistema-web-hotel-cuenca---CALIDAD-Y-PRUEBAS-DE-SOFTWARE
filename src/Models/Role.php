<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Role
{
    /**
     * @param array<int, Permission> $permissions
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public string $created_at,
        public array $permissions = [],
    ) {
    }
}
