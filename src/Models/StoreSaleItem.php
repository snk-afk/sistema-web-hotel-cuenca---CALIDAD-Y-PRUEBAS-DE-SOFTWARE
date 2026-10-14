<?php

declare(strict_types=1);

namespace App\Models;

final readonly class StoreSaleItem
{
    public function __construct(
        public int $id,
        public int $store_sale_id,
        public int $product_id,
        public int $quantity,
        public float $unit_price,
        public float $subtotal,
        public ?Product $product = null,
    ) {
    }
}
