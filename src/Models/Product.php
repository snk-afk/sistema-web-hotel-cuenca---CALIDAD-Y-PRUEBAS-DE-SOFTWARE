<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Product
{
    public function __construct(
        public int $id,
        public ?string $sku,
        public string $name,
        public ?string $description,
        public float $sale_price,
        public float $cost_price,
        public int $stock,
        public int $min_stock,
        public bool $is_active,
        public string $created_at,
        public string $updated_at,
    ) {
    }
}
