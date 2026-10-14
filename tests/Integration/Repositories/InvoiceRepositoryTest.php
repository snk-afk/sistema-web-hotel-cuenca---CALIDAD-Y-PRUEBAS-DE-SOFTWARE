<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\InvoiceRepository;
use Tests\Support\MySqlTestCase;

final class InvoiceRepositoryTest extends MySqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUserFixture(1, 'invoice');
    }

    public function testCreateAndFindInvoiceWithItems(): void
    {
        $repo = new InvoiceRepository($this->pdo());

        $invoice = $repo->create(
            stay_id: null,
            store_sale_id: null,
            document_type: 'BOLETA',
            series: 'B001',
            number: 1,
            full_number: 'B001-00000001',
            customer_document_type: 'DNI',
            customer_document_number: '12345678',
            customer_name: 'Ana Torres',
            customer_address: null,
            taxable_amount: 50.00,
            igv_amount: 9.00,
            total_amount: 59.00,
            sunat_status: 'accepted',
            sunat_response_code: '0',
            pdf_url: 'http://cdn/B001-00000001.pdf',
            xml_url: 'http://cdn/B001-00000001.xml',
            voucher_url: 'http://cdn/B001-00000001_voucher.pdf',
            created_by_user_id: 1,
            items: [
                ['description' => 'Servicio de Lavandería', 'quantity' => 1, 'unit_price' => 59.00, 'subtotal' => 59.00],
            ]
        );

        $this->assertEquals('B001-00000001', $invoice->full_number);
        $this->assertCount(1, $invoice->items);
        $this->assertEquals('Servicio de Lavandería', $invoice->items[0]->description);
    }
}
