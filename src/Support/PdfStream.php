<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Builder for PDF content streams with helper methods for text, rectangles, lines and images.
 * @internal
 */
final class PdfStream
{
    /** @var list<string> */
    private array $lines = [];

    /**
     * @param float $r Red 0-1
     * @param float $g Green 0-1
     * @param float $b Blue 0-1
     */
    public function setFillColor(float $r, float $g, float $b): void
    {
        $this->lines[] = sprintf('%.2f %.2f %.2f rg', $r, $g, $b);
    }

    /**
     * @param float $r Red 0-1
     * @param float $g Green 0-1
     * @param float $b Blue 0-1
     */
    public function setStrokeColor(float $r, float $g, float $b): void
    {
        $this->lines[] = sprintf('%.2f %.2f %.2f RG', $r, $g, $b);
    }

    /**
     * Draw a filled and/or stroked rectangle.
     *
     * @param ?string $fill Fill color string like "0.11 0.20 0.38" (rgb 0-1, space-separated), null for no fill
     * @param ?string $stroke Stroke color string, null for no stroke
     */
    public function rect(float $x, float $y, float $w, float $h, ?string $fill = null, ?string $stroke = null): void
    {
        $this->lines[] = sprintf('%.2f %.2f %.2f %.2f re', $x, $y, $w, $h);

        if ($fill !== null && $stroke !== null) {
            $this->lines[] = $fill . ' rg';
            $this->lines[] = $stroke . ' RG';
            $this->lines[] = 'B';
        } elseif ($fill !== null) {
            $this->lines[] = $fill . ' rg';
            $this->lines[] = 'f';
        } elseif ($stroke !== null) {
            $this->lines[] = $stroke . ' RG';
            $this->lines[] = 'S';
        }
    }

    /**
     * Draw a line.
     *
     * @param float $lw Line width
     */
    public function line(float $x1, float $y1, float $x2, float $y2, float $lw = 0.5): void
    {
        $this->lines[] = sprintf('%.2f w', $lw);
        $this->lines[] = sprintf('%.2f %.2f m', $x1, $y1);
        $this->lines[] = sprintf('%.2f %.2f l', $x2, $y2);
        $this->lines[] = 'S';
    }

    /**
     * Draw text at (x, y).
     *
     * @param float $size Font size
     * @param string $font Font name: 'F1' for Helvetica, 'F2' for Helvetica-Bold
     */
    public function text(float $x, float $y, string $text, float $size = 10, string $font = 'F1'): void
    {
        $this->lines[] = 'BT';
        $this->lines[] = "/{$font} {$size} Tf";
        $this->lines[] = sprintf('%.2f %.2f Td', $x, $y);
        $this->lines[] = '(' . $text . ') Tj';
        $this->lines[] = 'ET';
    }

    /**
     * Draw wrapped text starting at (x, y), breaking into lines of approx $max_width pt.
     * Each subsequent line is placed $line_height pt below the previous.
     *
     * @param float $size Font size
     * @param string $font Font name: 'F1' for Helvetica, 'F2' for Helvetica-Bold
     * @return float The Y position after the last line (for continued drawing)
     */
    public function textWrapped(float $x, float $y, string $text, float $max_width, float $size = 9, float $line_height = 11, string $font = 'F1'): float
    {
        $words = explode(' ', $text);
        $lines = [];
        $current_line = '';

        foreach ($words as $word) {
            $candidate = $current_line === '' ? $word : $current_line . ' ' . $word;
            $estimated_width = self::estimateTextWidth($candidate, $size, $font);

            if ($estimated_width > $max_width && $current_line !== '') {
                $lines[] = $current_line;
                $current_line = $word;
            } else {
                $current_line = $candidate;
            }
        }

        if ($current_line !== '') {
            $lines[] = $current_line;
        }

        $current_y = $y;
        foreach ($lines as $line) {
            $this->text($x, $current_y, $line, $size, $font);
            $current_y -= $line_height;
        }

        return $current_y;
    }

    /**
     * Estimate text width in points using approximate character widths.
     * Helvetica average char width ≈ 0.5 * font_size; Helvetica-Bold ≈ 0.52 * font_size.
     */
    public static function estimateTextWidth(string $text, float $size, string $font = 'F1'): float
    {
        $char_factor = $font === 'F2' ? 0.52 : 0.50;
        return strlen($text) * $char_factor * $size;
    }

    /**
     * Draw an image XObject at (x, y) with given width and height.
     */
    public function drawImage(string $name, float $x, float $y, float $w, float $h): void
    {
        $this->lines[] = sprintf('q %.2f 0 0 %.2f %.2f %.2f cm /%s Do Q', $w, $h, $x, $y, $name);
    }

    /**
     * Get the complete content stream string.
     */
    public function getContent(): string
    {
        return implode("\n", $this->lines);
    }
}
