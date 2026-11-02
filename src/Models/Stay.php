<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Stay
{
    /**
     * @param array<int, StayExtension> $extensions
     */
    public function __construct(
        public int $id,
        public int $room_id,
        public int $room_tariff_id,
        public ?int $guest_id,
        public string $check_in_at,
        public string $expected_check_out_at,
        public ?string $actual_check_out_at,
        public float $base_price,
        public float $applied_price,
        public ?string $discount_reason,
        public string $status,
        public int $created_by_user_id,
        public string $created_at,
        public string $updated_at,
        public array $extensions = [],
    ) {
    }
}
