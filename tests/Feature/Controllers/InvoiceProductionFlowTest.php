<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Services\InvoiceService;
use App\Support\StorageProvider;
use App\Support\SunatProvider;
use App\Support\Config;
use PDO;
use PHPUnit\Framework\TestCase;

final class InvoiceProductionFlowTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['SUNAT_MODE'] = 'real';
        Config::clear();
        Config::load(dirname(__DIR__, 3));
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE invoice_series ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, document_type TEXT NOT NULL, series TEXT NOT NULL UNIQUE, '
            . 'current_number INTEGER DEFAULT 0, is_active INTEGER DEFAULT 1, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE invoices ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, stay_id INTEGER, store_sale_id INTEGER, document_type TEXT NOT NULL, '
            . 'series TEXT NOT NULL, number INTEGER NOT NULL, full_number TEXT NOT NULL UNIQUE, customer_document_type TEXT NOT NULL, '
            . 'customer_document_number TEXT NOT NULL, customer_name TEXT NOT NULL, customer_address TEXT, taxable_amount REAL NOT NULL, '
            . 'igv_amount REAL NOT NULL, total_amount REAL NOT NULL, sunat_status TEXT DEFAULT "pending", sunat_response_code TEXT, '
            . 'pdf_url TEXT, xml_url TEXT, voucher_url TEXT, created_by_user_id INTEGER NOT NULL, created_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE invoice_items ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL, description TEXT NOT NULL, quantity INTEGER DEFAULT 1, '
            . 'unit_price REAL NOT NULL, subtotal REAL NOT NULL);'
            . 'CREATE TABLE folios ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, stay_id INTEGER NOT NULL UNIQUE, status TEXT DEFAULT "open", '
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
            . 'CREATE TABLE invoice_transmissions ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, invoice_id INTEGER NOT NULL UNIQUE, provider TEXT NOT NULL, '
            . 'idempotency_key TEXT NOT NULL UNIQUE, status TEXT NOT NULL, provider_code TEXT, provider_message TEXT, '
            . 'attempts INTEGER NOT NULL DEFAULT 0, last_attempt_at TEXT, created_at TEXT DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT DEFAULT CURRENT_TIMESTAMP);'
        );
        $this->pdo->exec("INSERT INTO invoice_series (document_type, series, current_number) VALUES ('BOLETA', 'B001', 0)");
    }

    public function testRepeatedIdempotencyKeyDoesNotSendOrNumberTwice(): void
    {
        $sunat_calls = 0;
        $sunat = new SunatProvider(static function (array $payload) use (&$sunat_calls): array {
            $sunat_calls++;
            return [
                'status_code' => 200,
                'body' => '{"status":"accepted","code":"0","message":"Aceptado"}',
            ];
        });
        $storage_root = sys_get_temp_dir() . '/cs-hotel-invoice-' . bin2hex(random_bytes(5));
        $storage = new StorageProvider(root_path: $storage_root);

        try {
            $service = new InvoiceService(
                db: $this->pdo,
                sunat_provider: $sunat,
                storage_provider: $storage,
            );
            $arguments = [
                'document_type' => 'BOLETA',
                'customer_document_type' => 'DNI',
                'customer_document_number' => '72819283',
                'customer_name' => 'Cliente de prueba',
                'total_amount' => 118.00,
                'items' => [['description' => 'Alojamiento', 'quantity' => 1, 'unit_price' => 118.00, 'subtotal' => 118.00]],
                'created_by_user_id' => 1,
                'idempotency_key' => 'stable-request-key',
            ];

            $first = $service->issueInvoice(...$arguments);
            $second = $service->issueInvoice(...$arguments);

            self::assertSame($first->id, $second->id);
            self::assertSame(1, $sunat_calls);
            self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM invoices')->fetchColumn());
            self::assertSame(1, (int) $this->pdo->query('SELECT current_number FROM invoice_series')->fetchColumn());
            self::assertSame('accepted', (string) $this->pdo->query('SELECT status FROM invoice_transmissions')->fetchColumn());
        } finally {
            foreach (glob($storage_root . '/*') ?: [] as $file) {
                unlink($file);
            }
            @rmdir($storage_root);
            unset($_ENV['SUNAT_MODE']);
            Config::clear();
        }
    }
}
