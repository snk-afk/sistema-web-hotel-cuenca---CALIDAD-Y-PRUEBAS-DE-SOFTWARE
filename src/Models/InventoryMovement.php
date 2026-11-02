<?php

declare(strict_types=1);

namespace App\Models;

final readonly class InventoryMovement
{
    public function __construct(
        public int $id,
        public int $product_id,
        public string $movement_type,
        public int $quantity,
        public int $resulting_stock,
        public string $concept,
        public int $created_by_user_id,
        public string $created_at,
    ) {
    }
}
