<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Permission
{
    public function __construct(
        public int $id,
        public string $key_name,
        public ?string $description,
        public string $created_at,
    ) {
    }
}
