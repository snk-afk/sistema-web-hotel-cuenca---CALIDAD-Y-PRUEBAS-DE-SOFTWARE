<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Invoice
{
    /**
     * @param array<int, InvoiceItem> $items
     */
    public function __construct(
        public int $id,
        public ?int $stay_id,
        public ?int $store_sale_id,
        public string $document_type,
        public string $series,
        public int $number,
        public string $full_number,
        public string $customer_document_type,
        public string $customer_document_number,
        public string $customer_name,
        public ?string $customer_address,
        public float $taxable_amount,
        public float $igv_amount,
        public float $total_amount,
        public string $sunat_status,
        public ?string $sunat_response_code,
        public ?string $pdf_url,
        public ?string $xml_url,
        public ?string $voucher_url,
        public int $created_by_user_id,
        public string $created_at,
        public array $items = [],
    ) {
    }
}
