<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Invoice;

/**
 * Pure PHP 8.4 ESC/POS Binary Command Formatter for 80mm & 58mm Thermal Printers.
 * Standard ESC/POS commands compliant with Epson, Star, Xprinter, Bixolon, etc.
 */
final class EscPosFormatter
{
    private const string ESC = "\x1B";
    private const string GS = "\x1D";

    /**
     * Format an Invoice entity into an ESC/POS binary string.
     */
    public static function formatInvoice(Invoice $invoice, bool $open_drawer = false, bool $cut_paper = true): string
    {
        $b = '';

        // 1. Initialize printer
        $b .= self::ESC . '@';

        // 2. Pulse cash drawer if requested (Pin 2, 50ms on, 500ms off)
        if ($open_drawer) {
            $b .= self::ESC . "p\x00\x19\xFA";
        }

        // 3. Header (Centered, bold)
        $b .= self::setAlign('center');
        $b .= self::setBold(true);
        $b .= self::setTextSize(2, 2);
        $b .= "HOTEL CS-HOTEL\n";
        $b .= self::setTextSize(1, 1);
        $b .= self::setBold(false);
        $b .= "RUC: 20608912345\n";
        $b .= "Av. Principal 123 - Lima, Peru\n";
        $b .= "Tel: (01) 234-5678\n";
        $b .= str_repeat('-', 42) . "\n";

        // 4. Document Type & Number
        $b .= self::setBold(true);
        $b .= "{$invoice->document_type} ELECTRONICA\n";
        $b .= "Nro: {$invoice->full_number}\n";
        $b .= self::setBold(false);
        $b .= "Fecha: {$invoice->created_at}\n";
        $b .= str_repeat('-', 42) . "\n";

        // 5. Customer Info (Left aligned)
        $b .= self::setAlign('left');
        $b .= "CLIENTE: " . self::cleanString($invoice->customer_name) . "\n";
        $b .= "{$invoice->customer_document_type}: {$invoice->customer_document_number}\n";
        if ($invoice->customer_address !== null && $invoice->customer_address !== '') {
            $b .= "Dir: " . self::cleanString($invoice->customer_address) . "\n";
        }
        $b .= str_repeat('-', 42) . "\n";

        // 6. Items Table Header
        $b .= sprintf("%-4s %-26s %9s\n", "CANT", "DESCRIPCION", "TOTAL");
        $b .= str_repeat('-', 42) . "\n";

        // 7. Items Rows
        foreach ($invoice->items as $item) {
            $desc = self::cleanString($item->description);
            if (strlen($desc) > 26) {
                $desc = substr($desc, 0, 23) . '...';
            }
            $sub = number_format($item->subtotal, 2);
            $b .= sprintf("%-4d %-26s %9s\n", $item->quantity, $desc, $sub);
        }
        $b .= str_repeat('-', 42) . "\n";

        // 8. Totals
        $b .= sprintf("%-30s %11s\n", "Op. Gravada:", "S/ " . number_format($invoice->taxable_amount, 2));
        $b .= sprintf("%-30s %11s\n", "I.G.V. (18%):", "S/ " . number_format($invoice->igv_amount, 2));
        $b .= self::setBold(true);
        $b .= sprintf("%-28s %13s\n", "TOTAL:", "S/ " . number_format($invoice->total_amount, 2));
        $b .= self::setBold(false);
        $b .= str_repeat('=', 42) . "\n";

        // 9. Legal footer & SUNAT disclaimer (Centered)
        $b .= self::setAlign('center');
        $b .= "Representacion impresa del comprobante electronico\n";
        $b .= "Autorizado mediante SUNAT\n";
        $b .= "Consulte su comprobante en www.sunat.gob.pe\n\n";
        $b .= "¡Gracias por su preferencia!\n\n\n";

        // 10. Paper feed & partial/full cut
        if ($cut_paper) {
            // Feed 3 lines and cut (GS V 66 0)
            $b .= self::GS . "V\x42\x00";
        }

        return $b;
    }

    private static function setAlign(string $align): string
    {
        $code = match ($align) {
            'center' => "\x01",
            'right' => "\x02",
            default => "\x00",
        };
        return self::ESC . "a{$code}";
    }

    private static function setBold(bool $enable): string
    {
        return self::ESC . 'E' . ($enable ? "\x01" : "\x00");
    }

    private static function setTextSize(int $width_multiplier, int $height_multiplier): string
    {
        $w = max(1, min(8, $width_multiplier)) - 1;
        $h = max(1, min(8, $height_multiplier)) - 1;
        $n = chr(($w << 4) | $h);
        return self::GS . "!{$n}";
    }

    private static function cleanString(string $text): string
    {
        $unaccented = strtr($text, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N',
        ]);
        return preg_replace('/[^\x20-\x7E]/', '', $unaccented) ?? $unaccented;
    }
}
