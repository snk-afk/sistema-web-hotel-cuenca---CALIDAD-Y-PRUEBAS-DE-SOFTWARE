<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Expense
{
    public function __construct(
        public int $id,
        public int $cashbox_shift_id,
        public string $category,
        public string $concept,
        public float $amount,
        public string $payment_method,
        public ?string $supplier_name,
        public ?string $receipt_number,
        public int $created_by_user_id,
        public string $created_at,
    ) {
    }
}
