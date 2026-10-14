<?php

declare(strict_types=1);

namespace App\Models;

final readonly class ReservationRefund
{
    public function __construct(
        public int $id,
        public int $cancellation_id,
        public int $reservation_payment_id,
        public int $cashbox_shift_id,
        public float $amount,
        public string $payment_method,
        public ?string $reference_number,
        public int $executed_by_user_id,
        public string $reason,
        public string $created_at,
    ) {
    }
}
