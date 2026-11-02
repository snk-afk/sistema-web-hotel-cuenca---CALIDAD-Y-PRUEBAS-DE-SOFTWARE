<?php

declare(strict_types=1);

namespace App\Models;

final readonly class InvoiceItem
{
    public function __construct(
        public int $id,
        public int $invoice_id,
        public string $description,
        public int $quantity,
        public float $unit_price,
        public float $subtotal,
    ) {
    }
}
