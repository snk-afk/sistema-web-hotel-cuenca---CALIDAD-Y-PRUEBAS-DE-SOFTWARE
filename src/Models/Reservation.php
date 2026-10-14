<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Reservation
{
    public function __construct(
        public int $id,
        public int $room_id,
        public int $guest_id,
        public int $room_tariff_id,
        public string $reserved_check_in_at,
        public string $reserved_check_out_at,
        public float $advance_payment,
        public string $status,
        public ?string $notes,
        public int $created_by_user_id,
        public string $created_at,
        public string $updated_at,
        public ?Room $room = null,
        public ?Guest $guest = null,
        public ?RoomTariff $tariff = null,
    ) {
    }
}
