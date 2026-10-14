<?php

declare(strict_types=1);

namespace App\Models;

final readonly class FolioCharge
{
    public function __construct(
        public int $id,
        public int $folio_id,
        public string $charge_type,
        public string $description,
        public int $quantity,
        public float $unit_price,
        public float $total_amount,
        public bool $is_reversal,
        public ?string $reversal_reason,
        public int $created_by_user_id,
        public string $created_at,
    ) {
    }
}
