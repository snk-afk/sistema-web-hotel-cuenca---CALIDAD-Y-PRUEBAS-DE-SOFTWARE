<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Folio
{
    /**
     * @param array<int, FolioCharge> $charges
     * @param array<int, FolioPayment> $payments
     */
    public function __construct(
        public int $id,
        public int $stay_id,
        public string $status,
        public string $created_at,
        public string $updated_at,
        public array $charges = [],
        public array $payments = [],
        public float $total_charges = 0.0,
        public float $total_payments = 0.0,
        public float $pending_balance = 0.0,
    ) {
    }
}
