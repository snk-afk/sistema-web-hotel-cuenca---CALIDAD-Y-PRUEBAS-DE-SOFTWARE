<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\FolioRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\InvoiceSeriesRepository;
use App\Services\InvoiceService;
use App\Support\StorageProvider;
use App\Support\SunatProvider;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class InvoiceServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE invoice_series (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_type TEXT NOT NULL,
                series TEXT NOT NULL UNIQUE,
                current_number INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER,
                store_sale_id INTEGER,
                document_type TEXT NOT NULL,
                series TEXT NOT NULL,
                number INTEGER NOT NULL,
                full_number TEXT NOT NULL UNIQUE,
                customer_document_type TEXT NOT NULL,
                customer_document_number TEXT NOT NULL,
                customer_name TEXT NOT NULL,
                customer_address TEXT,
                taxable_amount REAL NOT NULL,
                igv_amount REAL NOT NULL,
                total_amount REAL NOT NULL,
                sunat_status TEXT DEFAULT "pending",
                sunat_response_code TEXT,
                pdf_url TEXT,
                xml_url TEXT,
                voucher_url TEXT,
                created_by_user_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE invoice_items (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL,
                description TEXT NOT NULL,
                quantity INTEGER DEFAULT 1,
                unit_price REAL NOT NULL,
                subtotal REAL NOT NULL
            );

            CREATE TABLE folios (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                stay_id INTEGER NOT NULL UNIQUE,
                status TEXT DEFAULT "open",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE invoice_transmissions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                invoice_id INTEGER NOT NULL UNIQUE,
                provider TEXT NOT NULL,
                idempotency_key TEXT NOT NULL UNIQUE,
                status TEXT NOT NULL,
                provider_code TEXT,
                provider_message TEXT,
                attempts INTEGER NOT NULL DEFAULT 0,
                last_attempt_at TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function testTaxBreakdownCalculation18Percent(): void
    {
        $service = new InvoiceService($this->pdo);

        $breakdown = $service->calculateTaxBreakdown(118.00);

        $this->assertEquals(100.00, $breakdown['taxable_amount']);
        $this->assertEquals(18.00, $breakdown['igv_amount']);
        $this->assertEquals(118.00, $breakdown['total_amount']);
    }

    public function testIssueBoletaIncrementsCorrelativoAndCalculatesIGV(): void
    {
        $service = new InvoiceService($this->pdo);

        $invoice = $service->issueInvoice(
            document_type: 'BOLETA',
            customer_document_type: 'DNI',
            customer_document_number: '72819283',
            customer_name: 'Juan Pérez',
            total_amount: 118.00,
            items: [
                ['description' => 'Alojamiento 12H', 'quantity' => 1, 'unit_price' => 118.00, 'subtotal' => 118.00],
            ],
            created_by_user_id: 1
        );

        $this->assertEquals('B001', $invoice->series);
        $this->assertEquals(1, $invoice->number);
        $this->assertEquals('B001-00000001', $invoice->full_number);
        $this->assertEquals(100.00, $invoice->taxable_amount);
        $this->assertEquals(18.00, $invoice->igv_amount);
        $this->assertEquals(118.00, $invoice->total_amount);
    }

    public function testFacturaWithout11DigitRucThrowsException(): void
    {
        $service = new InvoiceService($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RUC válido de 11 dígitos');

        $service->issueInvoice(
            document_type: 'FACTURA',
            customer_document_type: 'DNI',
            customer_document_number: '72819283',
            customer_name: 'Empresa Ficticia',
            total_amount: 236.00,
            items: [
                ['description' => 'Hospedaje', 'quantity' => 1, 'unit_price' => 236.00, 'subtotal' => 236.00],
            ],
            created_by_user_id: 1
        );
    }

    public function testRejectsInvoiceWithMismatchedItemTotalOrMoreThanTwoDecimals(): void
    {
        $service = new InvoiceService($this->pdo);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('no cuadran');

        $service->issueInvoice(
            document_type: 'BOLETA',
            customer_document_type: 'DNI',
            customer_document_number: '72819283',
            customer_name: 'Cliente inválido',
            total_amount: 10.00,
            items: [
                ['description' => 'Servicio', 'quantity' => 1, 'unit_price' => 10.00, 'subtotal' => 9.99],
            ],
            created_by_user_id: 1,
        );
    }
}
