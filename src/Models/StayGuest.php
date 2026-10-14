<?php

declare(strict_types=1);

namespace App\Models;

final readonly class StayGuest
{
    public function __construct(
        public int $id,
        public int $stay_id,
        public int $guest_id,
        public bool $is_primary,
        public string $created_at,
        public ?Guest $guest = null,
    ) {
    }
}
