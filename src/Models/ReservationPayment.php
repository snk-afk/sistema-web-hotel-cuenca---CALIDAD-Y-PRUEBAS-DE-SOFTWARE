<?php

declare(strict_types=1);

namespace App\Models;

final readonly class ReservationPayment
{
    public function __construct(
        public int $id,
        public int $reservation_id,
        public int $cashbox_shift_id,
        public float $amount,
        public string $payment_method,
        public ?string $reference_number,
        public ?string $notes,
        public int $created_by_user_id,
        public string $created_at,
    ) {
    }
}
