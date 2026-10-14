<?php

declare(strict_types=1);

namespace App\Models;

final readonly class RoomTariff
{
    public function __construct(
        public int $id,
        public int $room_category_id,
        public string $name,
        public int $duration_hours,
        public float $price,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {
    }
}
