<?php

declare(strict_types=1);

namespace App\Models;

final readonly class InvoiceSeries
{
    public function __construct(
        public int $id,
        public string $document_type,
        public string $series,
        public int $current_number,
        public bool $is_active,
        public string $created_at,
    ) {
    }
}
