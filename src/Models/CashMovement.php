<?php

declare(strict_types=1);

namespace App\Models;

final readonly class CashMovement
{
    public function __construct(
        public int $id,
        public int $cashbox_shift_id,
        public string $type,
        public string $category,
        public string $concept,
        public float $amount,
        public string $payment_method,
        public ?int $reference_id,
        public int $created_by_user_id,
        public string $created_at,
        public ?string $reference_type = null,
    ) {
    }
}
