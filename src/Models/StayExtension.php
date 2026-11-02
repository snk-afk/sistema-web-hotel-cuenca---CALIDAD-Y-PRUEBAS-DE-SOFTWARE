<?php

declare(strict_types=1);

namespace App\Models;

final readonly class StayExtension
{
    public function __construct(
        public int $id,
        public int $stay_id,
        public int $added_hours,
        public float $added_price,
        public int $created_by_user_id,
        public string $created_at,
    ) {
    }
}
