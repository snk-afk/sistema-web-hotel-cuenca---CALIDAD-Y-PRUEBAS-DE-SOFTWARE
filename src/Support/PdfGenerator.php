<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CashboxShift;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;

/**
 * Pure PHP 8.4 PDF Generator (No external PDF libraries)
 * Generates valid PDF-1.4 documents with embedded QR images.
 */
final class PdfGenerator
{
    /**
     * Generate standard printable A4 invoice/voucher PDF with QR code.
     * Uses WinAnsiEncoding for correct rendering of accented characters (á, é, ñ, etc.).
     *
     * @param array{
     *     title: string,
     *     number: string,
     *     date: string,
     *     customer_name: string,
     *     customer_doc_type: string,
     *     customer_doc_number: string,
     *     customer_address?: ?string,
     *     taxable_amount: float,
     *     igv_amount: float,
     *     total_amount: float,
     *     items: array<int, array{description: string, quantity: int, unit_price: float, subtotal: float}>
     * } $data
     */
    public static function generateInvoicePdf(array $data): string
    {
        $title = $data['title'];
        $number = $data['number'];
        $date = $data['date'];
        $client = $data['customer_name'];
        $doc_type = $data['customer_doc_type'];
        $doc_number = $data['customer_doc_number'];
        $address = $data['customer_address'] ?? null;
        $taxable = $data['taxable_amount'];
        $igv = $data['igv_amount'];
        $total = $data['total_amount'];
        $items = $data['items'];

        // === Generate QR code PNG ===
        $qr_data = self::buildQrPayload($data);
        $qr_png = self::generateQrPng($qr_data);
        $qr_image_obj = self::buildPngImageObject($qr_png);

        // === Page geometry: A4 portrait (595 x 842 pt) ===
        $page_width = 595;
        $page_height = 842;

        $stream = new PdfStream();

        // -- Header band (dark rectangle) --
        $stream->rect(0, $page_height - 90, $page_width, 90, fill: '0.11 0.20 0.38');
        $stream->setFillColor(1, 1, 1);
        $stream->text(40, $page_height - 35, self::encode('HOTEL CS-HOTEL'), 16, 'F2');
        $stream->text(40, $page_height - 52, self::encode('RUC: 20608912345'), 9);
        $stream->text(40, $page_height - 66, self::encode('Av. Principal 123 - Lima, Perú'), 9);
        $stream->text(40, $page_height - 80, self::encode('Tel: (01) 234-5678'), 9);

        // -- Document title box (right side) --
        $stream->rect(380, $page_height - 85, 175, 75, fill: '1 1 1', stroke: '0.11 0.20 0.38');
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text(388, $page_height - 35, self::encode($title), 11, 'F2');
        $stream->text(388, $page_height - 52, self::encode("N° $number"), 14, 'F2');
        $stream->text(388, $page_height - 66, self::encode($date), 9);

        // -- Customer info section --
        $y = $page_height - 120;
        $stream->setFillColor(0.2, 0.2, 0.2);
        $stream->text(40, $y, self::encode('DATOS DEL CLIENTE'), 9, 'F2');
        $stream->line(40, $y - 4, $page_width - 40, $y - 4, 0.8);

        $y -= 18;
        $stream->setFillColor(0.15, 0.15, 0.15);
        $stream->text(40, $y, self::encode('Señores:'), 9);
        $stream->text(100, $y, self::escapePdfString(self::encode($client)), 9, 'F2');
        $y -= 14;
        $stream->text(40, $y, self::encode("$doc_type:"), 9);
        $stream->text(100, $y, self::encode($doc_number), 9);
        if ($address !== null && $address !== '') {
            $y -= 14;
            $stream->text(40, $y, self::encode('Dirección:'), 9);
            $stream->text(100, $y, self::escapePdfString(self::encode($address)), 9);
        }

        // -- Items table header --
        $y -= 30;
        $stream->rect(40, $y - 16, $page_width - 80, 16, fill: '0.11 0.20 0.38');
        $stream->setFillColor(1, 1, 1);
        $stream->text(46, $y - 11, self::encode('CANT.'), 8, 'F2');
        $stream->text(90, $y - 11, self::encode('DESCRIPCIÓN'), 8, 'F2');
        $stream->text(370, $y - 11, self::encode('P. UNIT.'), 8, 'F2');
        $stream->text(450, $y - 11, self::encode('SUBTOTAL'), 8, 'F2');
        $y -= 16;

        // -- Items rows --
        $stream->setFillColor(0.1, 0.1, 0.1);
        $row_alt = false;
        foreach ($items as $item) {
            $qty = $item['quantity'];
            $desc = self::escapePdfString(self::encode($item['description']));
            $unit = number_format($item['unit_price'], 2);
            $sub = number_format($item['subtotal'], 2);

            if ($row_alt) {
                $stream->rect(40, $y - 14, $page_width - 80, 14, fill: '0.95 0.95 0.95');
            }
            $stream->setFillColor(0.1, 0.1, 0.1);
            $stream->text(46, $y - 10, (string) $qty, 8);
            $stream->text(90, $y - 10, $desc, 8);
            $stream->text(370, $y - 10, $unit, 8);
            $stream->text(450, $y - 10, $sub, 8);

            $stream->line(40, $y - 14, $page_width - 40, $y - 14, 0.3);
            $y -= 14;
            $row_alt = ! $row_alt;
        }

        // -- Totals section --
        $y -= 10;
        $stream->setFillColor(0.2, 0.2, 0.2);
        $stream->text(40, $y, self::encode('RESUMEN'), 9, 'F2');
        $stream->line(40, $y - 4, $page_width - 40, $y - 4, 0.8);
        $y -= 18;

        // Totals box
        $totals_x = 350;
        $stream->rect($totals_x, $y - 60, 205, 60, fill: '0.97 0.97 0.97', stroke: '0.11 0.20 0.38');

        $stream->setFillColor(0.15, 0.15, 0.15);
        $stream->text($totals_x + 10, $y - 14, self::encode('OP. GRAVADA:'), 9);
        $stream->text($totals_x + 130, $y - 14, 'S/ ' . number_format($taxable, 2), 9);
        $stream->text($totals_x + 10, $y - 28, self::encode('I.G.V. (18%):'), 9);
        $stream->text($totals_x + 130, $y - 28, 'S/ ' . number_format($igv, 2), 9);
        $stream->line($totals_x + 5, $y - 36, $totals_x + 200, $y - 36, 0.5);
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text($totals_x + 10, $y - 52, self::encode('IMPORTE TOTAL:'), 11, 'F2');
        $stream->text($totals_x + 130, $y - 52, 'S/ ' . number_format($total, 2), 11, 'F2');

        // -- QR code (bottom-left) --
        $qr_size = 90;
        $qr_x = 40;
        $qr_y = $y - 110;
        $stream->drawImage('QR1', $qr_x, $qr_y, $qr_size, $qr_size);

        // -- Legal text (next to QR) ---
        $stream->setFillColor(0.3, 0.3, 0.3);
        $stream->text(140, $qr_y + 75, self::encode('Representación impresa del comprobante electrónico'), 7);
        $stream->text(140, $qr_y + 65, self::encode('Autorizado mediante SUNAT'), 7);
        $stream->text(140, $qr_y + 55, self::encode('Consulte su comprobante en:'), 7);
        $stream->text(140, $qr_y + 45, self::encode('www.sunat.gob.pe'), 7, 'F2');

        // -- Footer band --
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->rect(0, 0, $page_width, 25, fill: '0.11 0.20 0.38');
        $stream->setFillColor(1, 1, 1);
        $stream->text(40, 9, self::encode('HOTEL CS-HOTEL | Sistema de Gestión Hotelera'), 8);
        $stream->text($page_width - 200, 9, self::encode('¡Gracias por su preferencia!'), 8, 'F2');

        $content_stream = $stream->getContent();
        $stream_len = strlen($content_stream);

        // === Build PDF objects ===
        // 1: Catalog, 2: Pages, 3: Page, 4: Content stream,
        // 5: Helvetica, 6: Helvetica-Bold, 7: QR Image XObject, 8: QR image data
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$page_width} {$page_height}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> /XObject << /QR1 7 0 R >> >> >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Length {$stream_len} >>\nstream\n{$content_stream}\nendstream\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";
        $obj7 = $qr_image_obj['header'];
        $obj8 = $qr_image_obj['data'];

        $header = "%PDF-1.4\n";
        $offset1 = strlen($header);
        $offset2 = $offset1 + strlen($obj1);
        $offset3 = $offset2 + strlen($obj2);
        $offset4 = $offset3 + strlen($obj3);
        $offset5 = $offset4 + strlen($obj4);
        $offset6 = $offset5 + strlen($obj5);
        $offset7 = $offset6 + strlen($obj6);
        $offset8 = $offset7 + strlen($obj7);
        $xref_offset = $offset8 + strlen($obj8);

        $xref = sprintf(
            "xref\n0 9\n0000000000 65535 f \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n",
            $offset1,
            $offset2,
            $offset3,
            $offset4,
            $offset5,
            $offset6,
            $offset7,
            $offset8
        );

        $trailer = "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF";

        return $header . $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $obj6 . $obj7 . $obj8 . $xref . $trailer;
    }

    /**
     * Generate thermal voucher PDF (80mm wide) for receipt printers.
     * No background colors — only black text on white, plus QR code.
     *
     * @param array{
     *     title: string,
     *     number: string,
     *     date: string,
     *     customer_name: string,
     *     customer_doc_type: string,
     *     customer_doc_number: string,
     *     customer_address?: ?string,
     *     taxable_amount: float,
     *     igv_amount: float,
     *     total_amount: float,
     *     items: array<int, array{description: string, quantity: int, unit_price: float, subtotal: float}>
     * } $data
     */
    public static function generateThermalVoucherPdf(array $data): string
    {
        $title = $data['title'];
        $number = $data['number'];
        $date = $data['date'];
        $client = $data['customer_name'];
        $doc_type = $data['customer_doc_type'];
        $doc_number = $data['customer_doc_number'];
        $address = $data['customer_address'] ?? null;
        $taxable = $data['taxable_amount'];
        $igv = $data['igv_amount'];
        $total = $data['total_amount'];
        $items = $data['items'];

        // === Generate QR code PNG ===
        $qr_data = self::buildQrPayload($data);
        $qr_png = self::generateQrPng($qr_data);
        $qr_image_obj = self::buildPngImageObject($qr_png);

        // === Page geometry: 80mm thermal = ~226pt wide ===
        $page_width = 226;
        $margin = 8;
        $content_width = $page_width - ($margin * 2);

        // Dynamically calculate height based on content
        $y = 0;
        $y -= 20; // Hotel name
        $y -= 12; // RUC
        $y -= 12; // Address
        $y -= 12; // Phone
        $y -= 16; // Separator
        $y -= 14; // Title
        $y -= 14; // Number
        $y -= 14; // Date
        $y -= 16; // Separator
        $y -= 12; // "CLIENTE" label
        $y -= 12; // Name
        $y -= 12; // Doc
        if ($address !== null && $address !== '') {
            $y -= 12; // Address
        }
        $y -= 16; // Separator
        // Items header
        $y -= 14;
        // Items rows (estimate: 14pt per row, min 1)
        $item_rows = max(1, count($items));
        $y -= $item_rows * 14;
        $y -= 16; // Separator
        // Totals: 3 lines
        $y -= 14;
        $y -= 14;
        $y -= 16;
        $y -= 14;
        $y -= 16; // Separator
        // QR
        $qr_size = 70;
        $y -= $qr_size + 10;
        // Legal text
        $y -= 12;
        $y -= 12;
        $y -= 20; // Footer

        $page_height = abs($y) + 20;

        $stream = new PdfStream();

        $cursor_y = $page_height - 20;

        // -- Hotel header (centered) --
        $stream->setFillColor(0, 0, 0);
        $stream->text($margin, $cursor_y, self::encode('HOTEL CS-HOTEL'), 11, 'F2');
        $cursor_y -= 12;
        $stream->text($margin, $cursor_y, self::encode('RUC: 20608912345'), 7);
        $cursor_y -= 12;
        $stream->text($margin, $cursor_y, self::encode('Av. Principal 123 - Lima, Perú'), 7);
        $cursor_y -= 12;
        $stream->text($margin, $cursor_y, self::encode('Tel: (01) 234-5678'), 7);
        $cursor_y -= 16;

        // -- Separator --
        $stream->line($margin, $cursor_y, $page_width - $margin, $cursor_y, 0.5);
        $cursor_y -= 14;

        // -- Document title --
        $stream->text($margin, $cursor_y, self::encode($title), 9, 'F2');
        $cursor_y -= 12;
        $stream->text($margin, $cursor_y, self::encode("N°: $number"), 8);
        $cursor_y -= 12;
        $stream->text($margin, $cursor_y, self::encode("Fecha: $date"), 7);
        $cursor_y -= 16;

        // -- Separator --
        $stream->line($margin, $cursor_y, $page_width - $margin, $cursor_y, 0.5);
        $cursor_y -= 12;

        // -- Customer info --
        $stream->text($margin, $cursor_y, self::encode('CLIENTE'), 7, 'F2');
        $cursor_y -= 12;
        $stream->text($margin, $cursor_y, self::encode(self::escapePdfString(self::encode($client))), 8);
        $cursor_y -= 12;
        $stream->text($margin, $cursor_y, self::encode("$doc_type: $doc_number"), 7);
        if ($address !== null && $address !== '') {
            $cursor_y -= 12;
            $addr_short = strlen($address) > 30 ? substr($address, 0, 30) . '...' : $address;
            $stream->text($margin, $cursor_y, self::encode('Dir: ' . self::escapePdfString(self::encode($addr_short))), 7);
        }
        $cursor_y -= 16;

        // -- Separator --
        $stream->line($margin, $cursor_y, $page_width - $margin, $cursor_y, 0.5);
        $cursor_y -= 14;

        // -- Items header --
        $stream->text($margin, $cursor_y, self::encode('Cant'), 7, 'F2');
        $stream->text($margin + 25, $cursor_y, self::encode('Descripción'), 7, 'F2');
        $stream->text($page_width - $margin - 50, $cursor_y, self::encode('Total'), 7, 'F2');
        $cursor_y -= 14;

        // -- Items rows --
        foreach ($items as $item) {
            $qty = (string) $item['quantity'];
            $desc = self::encode($item['description']);
            $sub = number_format($item['subtotal'], 2);

            // Truncate description to fit in ~130pt (leaving room for qty and subtotal)
            $max_desc_len = 22;
            if (strlen($desc) > $max_desc_len) {
                $desc = substr($desc, 0, $max_desc_len - 3) . '...';
            }

            $stream->text($margin, $cursor_y, $qty, 7);
            $stream->text($margin + 25, $cursor_y, self::escapePdfString($desc), 7);
            $stream->text($page_width - $margin - 35, $cursor_y, $sub, 7);
            $cursor_y -= 14;
        }
        $cursor_y -= 4;

        // -- Separator --
        $stream->line($margin, $cursor_y, $page_width - $margin, $cursor_y, 0.5);
        $cursor_y -= 14;

        // -- Totals --
        $stream->text($margin, $cursor_y, self::encode('Op. Gravada:'), 7);
        $stream->text($page_width - $margin - 50, $cursor_y, 'S/ ' . number_format($taxable, 2), 7);
        $cursor_y -= 14;
        $stream->text($margin, $cursor_y, self::encode('I.G.V. (18%):'), 7);
        $stream->text($page_width - $margin - 50, $cursor_y, 'S/ ' . number_format($igv, 2), 7);
        $cursor_y -= 16;
        $stream->line($margin + 40, $cursor_y, $page_width - $margin - 10, $cursor_y, 0.5);
        $cursor_y -= 14;
        $stream->text($margin, $cursor_y, self::encode('TOTAL:'), 9, 'F2');
        $stream->text($page_width - $margin - 50, $cursor_y, 'S/ ' . number_format($total, 2), 9, 'F2');
        $cursor_y -= 16;

        // -- Separator --
        $stream->line($margin, $cursor_y, $page_width - $margin, $cursor_y, 0.5);
        $cursor_y -= 10;

        // -- QR code (centered) --
        $qr_x = ($page_width - $qr_size) / 2;
        $stream->drawImage('QR1', $qr_x, $cursor_y - $qr_size, $qr_size, $qr_size);
        $cursor_y -= $qr_size + 10;

        // -- Legal text --
        $stream->text($margin, $cursor_y, self::encode('Representación impresa del'), 6);
        $cursor_y -= 10;
        $stream->text($margin, $cursor_y, self::encode('comprobante electrónico'), 6);
        $cursor_y -= 20;

        // -- Footer --
        $stream->text($margin, $cursor_y, self::encode('¡Gracias por su preferencia!'), 7, 'F2');

        $content_stream = $stream->getContent();
        $stream_len = strlen($content_stream);

        // === Build PDF objects ===
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$page_width} {$page_height}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> /XObject << /QR1 7 0 R >> >> >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Length {$stream_len} >>\nstream\n{$content_stream}\nendstream\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";
        $obj7 = $qr_image_obj['header'];
        $obj8 = $qr_image_obj['data'];

        $header = "%PDF-1.4\n";
        $offset1 = strlen($header);
        $offset2 = $offset1 + strlen($obj1);
        $offset3 = $offset2 + strlen($obj2);
        $offset4 = $offset3 + strlen($obj3);
        $offset5 = $offset4 + strlen($obj4);
        $offset6 = $offset5 + strlen($obj5);
        $offset7 = $offset6 + strlen($obj6);
        $offset8 = $offset7 + strlen($obj7);
        $xref_offset = $offset8 + strlen($obj8);

        $xref = sprintf(
            "xref\n0 9\n0000000000 65535 f \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n",
            $offset1,
            $offset2,
            $offset3,
            $offset4,
            $offset5,
            $offset6,
            $offset7,
            $offset8
        );

        $trailer = "trailer\n<< /Size 9 /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF";

        return $header . $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $obj6 . $obj7 . $obj8 . $xref . $trailer;
    }

    /**
     * Build SUNAT-compliant QR payload
     *
     * @param array{
     *     title: string,
     *     number: string,
     *     date: string,
     *     customer_name: string,
     *     customer_doc_type: string,
     *     customer_doc_number: string,
     *     customer_address?: ?string,
     *     taxable_amount: float,
     *     igv_amount: float,
     *     total_amount: float,
     *     items: array<int, array{description: string, quantity: int, unit_price: float, subtotal: float}>
     * } $data
     */
    private static function buildQrPayload(array $data): string
    {
        $ruc = '20608912345';
        $doc_type = $data['title'];
        $number = $data['number'];
        $total = number_format($data['total_amount'], 2, '.', '');
        $client_doc = $data['customer_doc_number'];
        $date = $data['date'];

        return implode('|', [
            $ruc,
            $doc_type,
            $number,
            $total,
            $client_doc,
            $date,
        ]);
    }

    private static function generateQrPng(string $data): string
    {
        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [PngWriter::WRITER_OPTION_NUMBER_OF_COLORS => null],
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 0,
        );

        $result = $builder->build();
        $png = $result->getString();

        return $png === '' ? ' ' : $png;
    }

    /**
     * Build a PDF image XObject from a PNG string.
     *
     * @return array{header: string, data: string}
     */
    private static function buildPngImageObject(string $png_bytes): array
    {
        $img = imagecreatefromstring($png_bytes);
        if ($img === false) {
            throw new \RuntimeException('Failed to read QR PNG image data.');
        }

        if (! imageistruecolor($img)) {
            imagepalettetotruecolor($img);
        }

        $width = imagesx($img);
        $height = imagesy($img);

        // Extract raw RGB pixel data
        $pixels = '';
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($img, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $pixels .= chr($r) . chr($g) . chr($b);
            }
        }

        // FlateCompress the pixel data
        $compressed = gzcompress($pixels);
        if ($compressed === false) {
            throw new \RuntimeException('Failed to compress QR image data.');
        }
        $data_len = strlen($compressed);

        $header = "7 0 obj\n<< /Type /XObject /Subtype /Image /Width {$width} /Height {$height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /FlateDecode /Length {$data_len} >>\nstream\n";
        $data = $compressed . "\nendstream\nendobj\n";

        return ['header' => $header, 'data' => $data];
    }

    /**
     * Convert UTF-8 string to ISO-8859-1 (Latin-1) for WinAnsiEncoding.
     * This ensures accented characters (á, é, í, ó, ú, ñ, Ñ, ¿, ¡) render correctly.
     */
    private static function encode(string $text): string
    {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Escape special PDF string characters (parentheses, backslash).
     */
    private static function escapePdfString(string $str): string
    {
        return strtr($str, [
            '\\' => '\\\\',
            '(' => '\(',
            ')' => '\)',
            "\n" => ' ',
            "\r" => '',
        ]);
    }

    /**
     * Generate formal A4 Executive Report PDF
     *
     * @param array{
     *     period_label: string,
     *     generated_at: string,
     *     occupancy_rate: float,
     *     occupied_rooms: int,
     *     total_rooms: int,
     *     adr: float,
     *     revpar: float,
     *     accommodation_income: float,
     *     store_income: float,
     *     other_income: float,
     *     total_income: float,
     *     total_expenses: float,
     *     net_profit: float
     * } $data
     */
    /**
     * Generate formal A4 Executive Report PDF with corporate branding & visual KPI cards.
     *
     * @param array{
     *     period_label: string,
     *     generated_at: string,
     *     occupancy_rate: float,
     *     occupied_rooms: int,
     *     total_rooms: int,
     *     adr: float,
     *     revpar: float,
     *     accommodation_income: float,
     *     store_income: float,
     *     other_income: float,
     *     total_income: float,
     *     total_expenses: float,
     *     net_profit: float
     * } $data
     */
    public static function generateExecutiveReportPdf(array $data): string
    {
        $period = $data['period_label'];
        $date = $data['generated_at'];
        $occ = number_format($data['occupancy_rate'], 1);
        $rooms = "{$data['occupied_rooms']} de {$data['total_rooms']} habs";
        $adr = number_format($data['adr'], 2);
        $revpar = number_format($data['revpar'], 2);

        $acc = number_format($data['accommodation_income'], 2);
        $store = number_format($data['store_income'], 2);
        $other = number_format($data['other_income'], 2);
        $totIncome = number_format($data['total_income'], 2);
        $totExp = number_format($data['total_expenses'], 2);
        $profit = number_format($data['net_profit'], 2);

        $margin = $data['total_income'] > 0 ? number_format(($data['net_profit'] / $data['total_income']) * 100, 1) : '0.0';

        $page_width = 595;
        $page_height = 842;

        $stream = new PdfStream();

        // 1. Header banner (Dark Corporate Blue)
        $stream->rect(0, $page_height - 90, $page_width, 90, fill: '0.11 0.20 0.38');
        $stream->setFillColor(1, 1, 1);
        $stream->text(40, $page_height - 35, self::encode('HOTEL CS-HOTEL'), 16, 'F2');
        $stream->text(40, $page_height - 52, self::encode('INFORME EJECUTIVO DE GESTIÓN Y AUDITORÍA'), 10, 'F2');
        $stream->text(40, $page_height - 68, self::encode('RUC: 20608912345 | Av. Principal 123 - Lima | Tel: (01) 234-5678'), 8);

        // Period Box (Right Header)
        $stream->rect($page_width - 220, $page_height - 75, 180, 58, fill: '1 1 1', stroke: '0.11 0.20 0.38');
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text($page_width - 212, $page_height - 32, self::encode('PERIODO EVALUADO:'), 8, 'F2');
        $stream->text($page_width - 212, $page_height - 46, self::encode(self::escapePdfString($period)), 9);
        $stream->setFillColor(0.4, 0.4, 0.4);
        $stream->text($page_width - 212, $page_height - 62, self::encode("Emitido: $date"), 7);

        $y = $page_height - 125;

        // 2. Section 1: KPI Cards Grid
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text(40, $y, self::encode('1. INDICADORES CLAVE DE RENDIMIENTO HOTELERO (KPIs)'), 11, 'F2');
        $stream->line(40, $y - 4, $page_width - 40, $y - 4, 0.8);
        $y -= 16;

        $card_width = 120;
        $card_height = 55;
        $card_gap = 11;
        $start_x = 40;

        // KPI 1: Ocupación
        $stream->rect($start_x, $y - $card_height, $card_width, $card_height, fill: '0.94 0.96 0.99', stroke: '0.75 0.82 0.93');
        $stream->setFillColor(0.25, 0.35, 0.55);
        $stream->text($start_x + 8, $y - 14, self::encode('TASA OCUPACIÓN'), 7, 'F2');
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text($start_x + 8, $y - 32, "{$occ} %", 14, 'F2');
        $stream->setFillColor(0.45, 0.45, 0.45);
        $stream->text($start_x + 8, $y - 46, self::encode($rooms), 7);

        // KPI 2: ADR
        $x2 = $start_x + $card_width + $card_gap;
        $stream->rect($x2, $y - $card_height, $card_width, $card_height, fill: '0.94 0.96 0.99', stroke: '0.75 0.82 0.93');
        $stream->setFillColor(0.25, 0.35, 0.55);
        $stream->text($x2 + 8, $y - 14, self::encode('ADR (TARIFA PROM.)'), 7, 'F2');
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text($x2 + 8, $y - 32, "S/ {$adr}", 13, 'F2');
        $stream->setFillColor(0.45, 0.45, 0.45);
        $stream->text($x2 + 8, $y - 46, self::encode('Por hab. ocupada/noche'), 7);

        // KPI 3: RevPAR
        $x3 = $x2 + $card_width + $card_gap;
        $stream->rect($x3, $y - $card_height, $card_width, $card_height, fill: '0.94 0.96 0.99', stroke: '0.75 0.82 0.93');
        $stream->setFillColor(0.25, 0.35, 0.55);
        $stream->text($x3 + 8, $y - 14, self::encode('RevPAR'), 7, 'F2');
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text($x3 + 8, $y - 32, "S/ {$revpar}", 13, 'F2');
        $stream->setFillColor(0.45, 0.45, 0.45);
        $stream->text($x3 + 8, $y - 46, self::encode('Rendimiento total habs'), 7);

        // KPI 4: Margen Neto
        $x4 = $x3 + $card_width + $card_gap;
        $stream->rect($x4, $y - $card_height, $card_width, $card_height, fill: '0.92 0.98 0.94', stroke: '0.65 0.85 0.70');
        $stream->setFillColor(0.12, 0.50, 0.25);
        $stream->text($x4 + 8, $y - 14, self::encode('MARGEN OPERATIVO'), 7, 'F2');
        $stream->setFillColor(0.08, 0.45, 0.20);
        $stream->text($x4 + 8, $y - 32, "{$margin} %", 14, 'F2');
        $stream->setFillColor(0.45, 0.45, 0.45);
        $stream->text($x4 + 8, $y - 46, self::encode('Rentabilidad neta'), 7);

        $y -= ($card_height + 30);

        // 3. Section 2: Financial Statement Table
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text(40, $y, self::encode('2. CONSOLIDADO FINANCIERO Y RESULTADOS OPERATIVOS'), 11, 'F2');
        $stream->line(40, $y - 4, $page_width - 40, $y - 4, 0.8);
        $y -= 20;

        // Table Header
        $table_w = $page_width - 80;
        $stream->rect(40, $y - 18, $table_w, 18, fill: '0.11 0.20 0.38');
        $stream->setFillColor(1, 1, 1);
        $stream->text(50, $y - 13, self::encode('CENTRO DE INGRESO / CONCEPTO FINANCIERO'), 8, 'F2');
        $stream->text(360, $y - 13, self::encode('TIPO'), 8, 'F2');
        $stream->text(470, $y - 13, self::encode('MONTO (S/)'), 8, 'F2');
        $y -= 18;

        $rows = [
            ['desc' => '(+) Ingresos por Hospedaje / Alojamiento', 'type' => 'Ingreso Operativo', 'amount' => "S/ $acc", 'bg' => false],
            ['desc' => '(+) Ingresos por Ventas Tienda POS / Minibar', 'type' => 'Ingreso Operativo', 'amount' => "S/ $store", 'bg' => true],
            ['desc' => '(+) Otros Ingresos (Cocheras, Lavandería, etc)', 'type' => 'Ingreso No Operativo', 'amount' => "S/ $other", 'bg' => false],
        ];

        foreach ($rows as $r) {
            if ($r['bg']) {
                $stream->rect(40, $y - 18, $table_w, 18, fill: '0.96 0.97 0.98');
            }
            $stream->setFillColor(0.15, 0.15, 0.15);
            $stream->text(50, $y - 12, self::encode($r['desc']), 8);
            $stream->setFillColor(0.4, 0.4, 0.4);
            $stream->text(360, $y - 12, self::encode($r['type']), 8);
            $stream->setFillColor(0.1, 0.1, 0.1);
            $stream->text(470, $y - 12, $r['amount'], 8, 'F2');

            $stream->line(40, $y - 18, $page_width - 40, $y - 18, 0.3);
            $y -= 18;
        }

        // Subtotal Ingresos Brutos
        $stream->rect(40, $y - 20, $table_w, 20, fill: '0.92 0.95 0.99');
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text(50, $y - 14, self::encode('(=) TOTAL INGRESOS OPERATIVOS BRUTOS'), 9, 'F2');
        $stream->text(465, $y - 14, "S/ $totIncome", 10, 'F2');
        $y -= 20;

        // Egresos
        $stream->setFillColor(0.15, 0.15, 0.15);
        $stream->text(50, $y - 14, self::encode('(-) Egresos y Gastos Operativos de Caja Chica'), 8);
        $stream->setFillColor(0.4, 0.4, 0.4);
        $stream->text(360, $y - 14, self::encode('Egreso / Gasto'), 8);
        $stream->setFillColor(0.75, 0.15, 0.15);
        $stream->text(465, $y - 14, "- S/ $totExp", 9, 'F2');
        $stream->line(40, $y - 20, $page_width - 40, $y - 20, 0.5);
        $y -= 20;

        $y -= 15;

        // Total Net Profit Box (Highlighted)
        $stream->rect(280, $y - 50, $page_width - 320, 50, fill: '0.94 0.98 0.95', stroke: '0.15 0.55 0.25');
        $stream->setFillColor(0.15, 0.55, 0.25);
        $stream->text(295, $y - 18, self::encode('(==) UTILIDAD OPERATIVA NETA:'), 10, 'F2');
        $stream->text(295, $y - 38, "S/ {$profit}", 15, 'F2');
        $stream->setFillColor(0.4, 0.4, 0.4);
        $stream->text($page_width - 150, $y - 38, self::encode("Margen: {$margin} %"), 9);

        // 4. Institutional Footer
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->rect(0, 0, $page_width, 28, fill: '0.11 0.20 0.38');
        $stream->setFillColor(1, 1, 1);
        $stream->text(40, 10, self::encode('HOTEL CS-HOTEL | Sistema Integral de Gestión Hotelera & Auditoría'), 8);
        $stream->text($page_width - 160, 10, self::encode('Documento Confidencial'), 8, 'F2');

        $content_stream = $stream->getContent();
        $stream_len = strlen($content_stream);

        // === Build PDF objects ===
        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$page_width} {$page_height}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Length {$stream_len} >>\nstream\n{$content_stream}\nendstream\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $offset1 = strlen($header);
        $offset2 = $offset1 + strlen($obj1);
        $offset3 = $offset2 + strlen($obj2);
        $offset4 = $offset3 + strlen($obj3);
        $offset5 = $offset4 + strlen($obj4);
        $offset6 = $offset5 + strlen($obj5);
        $xref_offset = $offset6 + strlen($obj6);

        $xref = sprintf(
            "xref\n0 7\n0000000000 65535 f \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n",
            $offset1,
            $offset2,
            $offset3,
            $offset4,
            $offset5,
            $offset6
        );

        $trailer = "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF";

        return $header . $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $obj6 . $xref . $trailer;
    }

    /**
     * Generate a corporate A4 PDF with the cashbox shift history.
     *
     * @param array{
     *     period_label: string,
     *     generated_at: string,
     *     shifts: array<int, CashboxShift>
     * } $data
     */
    public static function generateCashboxHistoryPdf(array $data): string
    {
        $period = $data['period_label'];
        $date = $data['generated_at'];
        /** @var array<int, CashboxShift> $shifts */
        $shifts = $data['shifts'];

        $page_width = 595;
        $page_height = 842;

        $stream = new PdfStream();

        // Header banner (Dark Corporate Blue)
        $stream->rect(0, $page_height - 90, $page_width, 90, fill: '0.11 0.20 0.38');
        $stream->setFillColor(1, 1, 1);
        $stream->text(40, $page_height - 35, self::encode('HOTEL CS-HOTEL'), 16, 'F2');
        $stream->text(40, $page_height - 52, self::encode('Historial de Turnos de Caja'), 10, 'F2');
        $stream->text(40, $page_height - 68, self::encode('RUC: 20608912345 | Av. Principal 123 - Lima | Tel: (01) 234-5678'), 8);

        // Period Box (Right Header)
        $stream->rect($page_width - 220, $page_height - 75, 180, 58, fill: '1 1 1', stroke: '0.11 0.20 0.38');
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->text($page_width - 212, $page_height - 32, self::encode('PERIODO:'), 8, 'F2');
        $stream->text($page_width - 212, $page_height - 46, self::encode(self::escapePdfString($period)), 9);
        $stream->setFillColor(0.4, 0.4, 0.4);
        $stream->text($page_width - 212, $page_height - 62, self::encode("Emitido: $date"), 7);

        $y = $page_height - 125;

        // Empty state
        if ($shifts === []) {
            $stream->setFillColor(0.11, 0.20, 0.38);
            $stream->text(40, $y, self::encode('No hay turnos para el período/filtros seleccionados.'), 11, 'F2');
        } else {
            // Section title
            $stream->setFillColor(0.11, 0.20, 0.38);
            $stream->text(40, $y, self::encode('TURNO'), 11, 'F2');
            $stream->line(40, $y - 4, $page_width - 40, $y - 4, 0.8);
            $y -= 20;

            // Table Header
            $table_w = $page_width - 80;
            $stream->rect(40, $y - 16, $table_w, 16, fill: '0.11 0.20 0.38');
            $stream->setFillColor(1, 1, 1);
            $stream->text(46, $y - 11, self::encode('ID'), 7, 'F2');
            $stream->text(68, $y - 11, self::encode('RESP.'), 7, 'F2');
            $stream->text(100, $y - 11, self::encode('APERTURA'), 7, 'F2');
            $stream->text(175, $y - 11, self::encode('CIERRE'), 7, 'F2');
            $stream->text(250, $y - 11, self::encode('FONDO'), 7, 'F2');
            $stream->text(295, $y - 11, self::encode('ESPERADO'), 7, 'F2');
            $stream->text(345, $y - 11, self::encode('DECLARADO'), 7, 'F2');
            $stream->text(400, $y - 11, self::encode('DIFERENCIA'), 7, 'F2');
            $stream->text(465, $y - 11, self::encode('ESTADO'), 7, 'F2');
            $stream->text(520, $y - 11, self::encode('ARQUEO'), 7, 'F2');
            $y -= 16;

            $row_h = 16;
            foreach ($shifts as $i => $s) {
                if ($y < 60) {
                    break;
                }

                $bg_alt = $i % 2 === 1;
                if ($bg_alt) {
                    $stream->rect(40, $y - $row_h, $table_w, $row_h, fill: '0.96 0.97 0.98');
                }

                $stream->setFillColor(0.15, 0.15, 0.15);
                $stream->text(46, $y - 11, (string) $s->id, 7);
                $stream->text(68, $y - 11, (string) $s->user_id, 7);
                $stream->text(100, $y - 11, self::encode(substr($s->opened_at, 5, 11)), 7);
                $stream->text(175, $y - 11, self::encode($s->closed_at !== null ? substr($s->closed_at, 5, 11) : '---'), 7);
                $stream->text(250, $y - 11, number_format($s->initial_cash, 2), 7);
                $stream->text(295, $y - 11, number_format($s->expected_cash, 2), 7);
                $stream->text(345, $y - 11, $s->real_cash_declared !== null ? number_format($s->real_cash_declared, 2) : '---', 7);

                // Difference with traffic-light color
                $diff = $s->difference ?? 0.0;
                $diff_text = $s->difference !== null ? number_format($s->difference, 2) : '---';
                if ($s->balance_status === 'shortage') {
                    $stream->setFillColor(0.75, 0.15, 0.15);
                } elseif ($s->balance_status === 'surplus') {
                    $stream->setFillColor(0.80, 0.55, 0.10);
                } else {
                    $stream->setFillColor(0.12, 0.50, 0.25);
                }
                $stream->text(400, $y - 11, $diff_text, 7, 'F2');

                $stream->setFillColor(0.15, 0.15, 0.15);
                $status_label = $s->status === 'open' ? 'ABIERTO' : 'CERRADO';
                $stream->text(465, $y - 11, self::encode($status_label), 7);

                $balance_label = match ($s->balance_status) {
                    'balanced' => 'CUADRADO',
                    'shortage' => 'FALTANTE',
                    'surplus' => 'SOBRANTE',
                    default => 'PEND.',
                };
                $stream->text(520, $y - 11, self::encode($balance_label), 7);

                $stream->line(40, $y - $row_h, $page_width - 40, $y - $row_h, 0.3);
                $y -= $row_h;
            }

            // Summary footer
            $y -= 15;
            $total_shifts = count($shifts);
            $balanced = 0;
            $shortage = 0;
            $surplus = 0;
            $total_diff = 0.0;

            foreach ($shifts as $s) {
                if ($s->balance_status === 'balanced') {
                    $balanced++;
                } elseif ($s->balance_status === 'shortage') {
                    $shortage++;
                } elseif ($s->balance_status === 'surplus') {
                    $surplus++;
                }
                if ($s->difference !== null) {
                    $total_diff += $s->difference;
                }
            }

            $stream->rect(40, $y - 55, $table_w, 55, fill: '0.94 0.96 0.99', stroke: '0.75 0.82 0.93');
            $stream->setFillColor(0.11, 0.20, 0.38);
            $stream->text(50, $y - 14, self::encode('RESUMEN DE ARQUEO'), 9, 'F2');
            $stream->setFillColor(0.15, 0.15, 0.15);
            $stream->text(50, $y - 30, self::encode("Total de turnos: {$total_shifts}"), 8);
            $stream->text(50, $y - 42, self::encode("Cuadrados: {$balanced}  |  Faltantes: {$shortage}  |  Sobrantes: {$surplus}"), 8);

            $diff_color = $total_diff < -0.01 ? '0.75 0.15 0.15' : ($total_diff > 0.01 ? '0.80 0.55 0.10' : '0.12 0.50 0.25');
            $stream->setFillColor((float) explode(' ', $diff_color)[0], (float) explode(' ', $diff_color)[1], (float) explode(' ', $diff_color)[2]);
            $stream->text(400, $y - 36, self::encode('Diferencia acumulada:'), 8);
            $stream->text(400, $y - 48, 'S/ ' . number_format($total_diff, 2), 10, 'F2');
        }

        // Institutional Footer
        $stream->setFillColor(0.11, 0.20, 0.38);
        $stream->rect(0, 0, $page_width, 28, fill: '0.11 0.20 0.38');
        $stream->setFillColor(1, 1, 1);
        $stream->text(40, 10, self::encode('HOTEL CS-HOTEL | Sistema Integral de Gestión Hotelera & Auditoría'), 8);
        $stream->text($page_width - 160, 10, self::encode('Documento Confidencial'), 8, 'F2');

        $content_stream = $stream->getContent();
        $stream_len = strlen($content_stream);

        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$page_width} {$page_height}] /Contents 4 0 R /Resources << /Font << /F1 5 0 R /F2 6 0 R >> >> >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Length {$stream_len} >>\nstream\n{$content_stream}\nendstream\nendobj\n";
        $obj5 = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>\nendobj\n";
        $obj6 = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";

        $header = "%PDF-1.4\n";
        $offset1 = strlen($header);
        $offset2 = $offset1 + strlen($obj1);
        $offset3 = $offset2 + strlen($obj2);
        $offset4 = $offset3 + strlen($obj3);
        $offset5 = $offset4 + strlen($obj4);
        $offset6 = $offset5 + strlen($obj5);
        $xref_offset = $offset6 + strlen($obj6);

        $xref = sprintf(
            "xref\n0 7\n0000000000 65535 f \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n%010d 00000 n \n",
            $offset1,
            $offset2,
            $offset3,
            $offset4,
            $offset5,
            $offset6
        );

        $trailer = "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n{$xref_offset}\n%%EOF";

        return $header . $obj1 . $obj2 . $obj3 . $obj4 . $obj5 . $obj6 . $xref . $trailer;
    }
}
