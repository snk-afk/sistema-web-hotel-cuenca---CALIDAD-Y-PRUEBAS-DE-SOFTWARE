<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PdfGenerator;
use PHPUnit\Framework\TestCase;

final class PdfGeneratorTest extends TestCase
{
    public function testGenerateInvoicePdfProducesValidPdfWithQrImage(): void
    {
        $data = [
            'title' => 'FACTURA ELECTRONICA',
            'number' => 'F001-00000001',
            'date' => '2026-08-22 19:08:00',
            'customer_name' => 'EMPRESA TURISMO SAC',
            'customer_doc_type' => 'RUC',
            'customer_doc_number' => '20601234567',
            'customer_address' => 'Av. Javier Prado 1234, Lima',
            'taxable_amount' => 508.47,
            'igv_amount' => 91.53,
            'total_amount' => 600.00,
            'items' => [
                [
                    'description' => 'Hospedaje Habitacion Doble - 3 Noches',
                    'quantity' => 3,
                    'unit_price' => 150.00,
                    'subtotal' => 450.00,
                ],
            ],
        ];

        $pdf = PdfGenerator::generateInvoicePdf($data);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);
        $this->assertStringContainsString('/QR1', $pdf);
        $this->assertStringEndsWith("%%EOF", trim($pdf));

        // Extract object 7 & 8 image stream to ensure it contains white/non-zero pixels
        preg_match('/stream\n([\s\S]*?)\nendstream\nendobj\n/m', $pdf, $matches);
        // Specifically look for FlateDecode image stream or decompress it
        if (preg_match('/\/ColorSpace \/DeviceRGB \/BitsPerComponent 8 \/Filter \/FlateDecode \/Length (\d+) >>\nstream\n(.*?)\nendstream/s', $pdf, $stream_match)) {
            $raw_compressed = $stream_match[2];
            $decompressed = gzuncompress($raw_compressed);
            $this->assertIsString($decompressed);
            // Must contain white pixels (\xFF\xFF\xFF)
            $this->assertStringContainsString("\xFF\xFF\xFF", $decompressed, 'QR image must contain white pixels, not just solid black');
            // Must contain black pixels (\x00\x00\x00)
            $this->assertStringContainsString("\x00\x00\x00", $decompressed, 'QR image must contain black pixels');
        } else {
            $this->fail('Could not locate QR image stream in generated PDF');
        }
    }

    public function testGenerateInvoicePdfUsesWinAnsiEncodingForAccentedChars(): void
    {
        $data = [
            'title' => 'BOLETA DE VENTA ELECTRONICA',
            'number' => 'B001-00000001',
            'date' => '2026-08-22 19:08:00',
            'customer_name' => 'María José Ñahuincopa',
            'customer_doc_type' => 'DNI',
            'customer_doc_number' => '72345678',
            'customer_address' => 'Av. Los Piños 123, San Isidro',
            'taxable_amount' => 84.75,
            'igv_amount' => 15.25,
            'total_amount' => 100.00,
            'items' => [
                [
                    'description' => 'Hospedaje Simple - 1 Noche',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'subtotal' => 100.00,
                ],
            ],
        ];

        $pdf = PdfGenerator::generateInvoicePdf($data);

        // Fonts must have WinAnsiEncoding
        $this->assertStringContainsString('/Encoding /WinAnsiEncoding', $pdf);
        // Accented text must be converted to ISO-8859-1 (not remain as UTF-8 multibyte)
        // "Señores" in ISO-8859-1: "Se\xf1ores"
        $this->assertStringContainsString("Se\xf1ores", $pdf);
        // "Dirección" in ISO-8859-1: "Direcci\xf3n"
        $this->assertStringContainsString("Direcci\xf3n", $pdf);
        // "DESCRIPCIÓN" (uppercase header) in ISO-8859-1: "DESCRIPCI\xd3N"
        $this->assertStringContainsString("DESCRIPCI\xd3N", $pdf);
        // "Representación" in ISO-8859-1: "Representaci\xf3n"
        $this->assertStringContainsString("Representaci\xf3n", $pdf);
    }

    public function testGenerateThermalVoucherPdfProducesValidPdfWith80mmWidth(): void
    {
        $data = [
            'title' => 'BOLETA DE VENTA ELECTRONICA',
            'number' => 'B001-00000001',
            'date' => '2026-08-22 19:08:00',
            'customer_name' => 'María José Ñahuincopa',
            'customer_doc_type' => 'DNI',
            'customer_doc_number' => '72345678',
            'customer_address' => 'Av. Los Piños 123, San Isidro',
            'taxable_amount' => 84.75,
            'igv_amount' => 15.25,
            'total_amount' => 100.00,
            'items' => [
                [
                    'description' => 'Hospedaje Simple - 1 Noche',
                    'quantity' => 1,
                    'unit_price' => 100.00,
                    'subtotal' => 100.00,
                ],
            ],
        ];

        $pdf = PdfGenerator::generateThermalVoucherPdf($data);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringEndsWith("%%EOF", trim($pdf));

        // Voucher must have WinAnsiEncoding
        $this->assertStringContainsString('/Encoding /WinAnsiEncoding', $pdf);

        // Voucher must have 80mm width (~226pt) as the first MediaBox dimension
        $this->assertStringContainsString('/MediaBox [0 0 226', $pdf);

        // Voucher must contain QR image
        $this->assertStringContainsString('/QR1', $pdf);
        $this->assertStringContainsString('/Subtype /Image', $pdf);

        // Voucher must contain totals
        $this->assertStringContainsString('S/ 84.75', $pdf);
        $this->assertStringContainsString('S/ 15.25', $pdf);
        $this->assertStringContainsString('S/ 100.00', $pdf);
    }

    public function testGenerateExecutiveReportPdfProducesCorporateLayout(): void
    {
        $data = [
            'period_label' => 'Del 2026-08-01 al 2026-08-22',
            'generated_at' => '2026-08-22 21:00:00',
            'occupancy_rate' => 75.5,
            'occupied_rooms' => 15,
            'total_rooms' => 20,
            'adr' => 120.00,
            'revpar' => 90.00,
            'accommodation_income' => 15000.00,
            'store_income' => 3500.00,
            'other_income' => 800.00,
            'total_income' => 19300.00,
            'total_expenses' => 4200.00,
            'net_profit' => 15100.00,
        ];

        $pdf = PdfGenerator::generateExecutiveReportPdf($data);

        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica', $pdf);
        $this->assertStringContainsString('/BaseFont /Helvetica-Bold', $pdf);
        $this->assertStringContainsString('HOTEL CS-HOTEL', $pdf);
        $this->assertStringContainsString('S/ 15,100.00', $pdf);
        $this->assertStringContainsString('75.5 %', $pdf);
        $this->assertStringEndsWith('%%EOF', trim($pdf));
    }
}
