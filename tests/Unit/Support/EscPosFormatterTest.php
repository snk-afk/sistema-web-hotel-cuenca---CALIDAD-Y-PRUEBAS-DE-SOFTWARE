<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Support\EscPosFormatter;
use PHPUnit\Framework\TestCase;

final class EscPosFormatterTest extends TestCase
{
    public function testFormatInvoiceGeneratesValidEscPosCommands(): void
    {
        $items = [
            new InvoiceItem(
                id: 1,
                invoice_id: 10,
                description: 'Alojamiento 12H Hab 101',
                quantity: 1,
                unit_price: 118.00,
                subtotal: 118.00
            ),
        ];

        $invoice = new Invoice(
            id: 10,
            stay_id: 5,
            store_sale_id: null,
            document_type: 'BOLETA',
            series: 'B001',
            number: 1,
            full_number: 'B001-00000001',
            customer_document_type: 'DNI',
            customer_document_number: '72819283',
            customer_name: 'Juan Perez',
            customer_address: 'Av. Las Flores 123',
            taxable_amount: 100.00,
            igv_amount: 18.00,
            total_amount: 118.00,
            sunat_status: 'ACCEPTED',
            sunat_response_code: '0',
            pdf_url: '/storage/comprobantes/B001-00000001.pdf',
            xml_url: '/storage/comprobantes/B001-00000001.xml',
            voucher_url: '/storage/comprobantes/B001-00000001_voucher.pdf',
            created_by_user_id: 1,
            created_at: '2026-08-23 01:07:20',
            items: $items
        );

        $binary = EscPosFormatter::formatInvoice($invoice, open_drawer: true, cut_paper: true);

        // ESC @ (Initialize)
        $this->assertStringContainsString("\x1B\x40", $binary);
        // Header text
        $this->assertStringContainsString('HOTEL CS-HOTEL', $binary);
        $this->assertStringContainsString('B001-00000001', $binary);
        $this->assertStringContainsString('Juan Perez', $binary);
        $this->assertStringContainsString('TOTAL:', $binary);
        $this->assertStringContainsString('118.00', $binary);
        // Cash drawer pulse (ESC p 0 25 250)
        $this->assertStringContainsString("\x1B\x70\x00\x19\xFA", $binary);
        // Paper cut (GS V 66 0)
        $this->assertStringContainsString("\x1D\x56\x42\x00", $binary);
    }
}
