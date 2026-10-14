<?php

declare(strict_types=1);

namespace App\Models;

final readonly class RoomCategory
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {
    }
}
