<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Room
{
    public function __construct(
        public int $id,
        public int $room_category_id,
        public string $number,
        public string $status,
        public ?string $floor,
        public ?string $description,
        public string $created_at,
        public string $updated_at,
        public ?RoomCategory $category = null,
        public ?Stay $current_stay = null,
    ) {
    }
}
