<?php

declare(strict_types=1);

namespace App\Models;

final readonly class ReservationCancellation
{
    public function __construct(
        public int $id,
        public int $reservation_id,
        public string $event_type,
        public string $policy_code,
        public float $refund_percentage,
        public float $gross_paid_amount,
        public float $refund_amount,
        public float $penalty_amount,
        public string $status,
        public string $reason,
        public int $requested_by_user_id,
        public ?int $approved_by_user_id,
        public ?string $approved_at,
        public string $created_at,
        public string $updated_at,
    ) {
    }
}
