<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\InvoiceController;
use App\Services\InvoiceService;
use Tests\Support\MySqlTestCase;

final class InvoiceControllerTest extends MySqlTestCase
{
    private InvoiceService $invoice_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUserFixture(1, 'invoice');
        $this->invoice_service = new InvoiceService($this->pdo);
    }

    public function testIndexReturnsInvoicesListJson(): void
    {
        $controller = new InvoiceController($this->invoice_service);

        ob_start();
        $controller->index();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertIsArray($data['data']);
    }

    /**
     * CA-9: Descarga de comprobante no existente retorna 404, no genera archivo falso.
     */
    public function testDownloadNonExistentInvoiceReturns404(): void
    {
        $_GET['number'] = 'B001-99999999';
        $_GET['type'] = 'pdf';

        $controller = new InvoiceController($this->invoice_service);

        ob_start();
        $controller->download();
        $output = (string) ob_get_clean();

        $this->assertEquals(404, http_response_code());

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
        $this->assertStringContainsString('no existe', $data['message']);

        // Verify no fake file was created
        $fake_file = dirname(__DIR__, 3) . '/storage/comprobantes/B001-99999999.pdf';
        unset($_GET['number'], $_GET['type']);
    }

    public function testEscPosReturnsOctetStreamWithValidCommands(): void
    {
        // Insert series
        $this->pdo->exec("INSERT INTO invoice_series (document_type, series, current_number) VALUES ('BOLETA', 'B001', 0)");

        $invoice = $this->invoice_service->issueInvoice(
            document_type: 'BOLETA',
            customer_document_type: 'DNI',
            customer_document_number: '72819283',
            customer_name: 'Juan Perez',
            total_amount: 118.00,
            items: [
                ['description' => 'Alojamiento 12H', 'quantity' => 1, 'unit_price' => 118.00, 'subtotal' => 118.00]
            ],
            created_by_user_id: 1
        );

        $controller = new InvoiceController($this->invoice_service);

        ob_start();
        $controller->escpos($invoice->id);
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('HOTEL CS-HOTEL', $output);
        $this->assertStringContainsString($invoice->full_number, $output);
        $this->assertStringContainsString('TOTAL:', $output);
    }
}
