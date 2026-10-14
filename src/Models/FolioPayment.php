<?php

declare(strict_types=1);

namespace App\Models;

final readonly class FolioPayment
{
    public function __construct(
        public int $id,
        public int $folio_id,
        public string $payment_type,
        public float $amount,
        public string $payment_method,
        public ?string $reference_number,
        public ?string $notes,
        public int $created_by_user_id,
        public string $created_at,
        public ?int $cashbox_shift_id = null,
        public ?int $reservation_payment_id = null,
    ) {
    }
}
