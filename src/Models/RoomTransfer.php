<?php

declare(strict_types=1);

namespace App\Models;

final readonly class RoomTransfer
{
    public function __construct(
        public int $id,
        public int $stay_id,
        public int $from_room_id,
        public int $to_room_id,
        public string $reason,
        public int $created_by_user_id,
        public string $created_at,
    ) {
    }
}
