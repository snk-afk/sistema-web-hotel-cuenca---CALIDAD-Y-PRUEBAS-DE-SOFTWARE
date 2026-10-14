<?php

declare(strict_types=1);

namespace App\Models;

final readonly class CashboxShift
{
    /**
     * @param array<int, CashMovement> $movements
     */
    public function __construct(
        public int $id,
        public int $user_id,
        public string $opened_at,
        public ?string $closed_at,
        public float $initial_cash,
        public float $expected_cash,
        public ?float $real_cash_declared,
        public ?float $difference,
        public string $status,
        public string $balance_status,
        public ?string $notes,
        public string $created_at,
        public string $updated_at,
        public array $movements = [],
        public float $expected_yape = 0.0,
        public float $expected_plin = 0.0,
        public float $expected_card = 0.0,
        public float $expected_transfer = 0.0,
        public ?float $real_yape_declared = null,
        public ?float $real_plin_declared = null,
        public ?float $real_card_declared = null,
        public ?float $real_transfer_declared = null,
        public ?float $difference_yape = null,
        public ?float $difference_plin = null,
        public ?float $difference_card = null,
        public ?float $difference_transfer = null,
    ) {
    }
}
