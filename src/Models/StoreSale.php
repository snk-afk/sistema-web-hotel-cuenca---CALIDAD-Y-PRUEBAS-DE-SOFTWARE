<?php

declare(strict_types=1);

namespace App\Models;

final readonly class StoreSale
{
    /**
     * @param array<int, StoreSaleItem> $items
     */
    public function __construct(
        public int $id,
        public int $cashbox_shift_id,
        public float $total_amount,
        public string $payment_method,
        public int $created_by_user_id,
        public string $created_at,
        public array $items = [],
    ) {
    }
}
