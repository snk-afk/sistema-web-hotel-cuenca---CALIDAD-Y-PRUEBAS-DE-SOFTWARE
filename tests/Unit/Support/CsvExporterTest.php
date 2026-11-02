<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CsvExporter;
use PHPUnit\Framework\TestCase;

final class CsvExporterTest extends TestCase
{
    public function testGenerateCsvIncludesUtf8BomAndFormatedRows(): void
    {
        $headers = ['CONCEPTO', 'MONTO'];
        $rows = [
            ['Habitación 101', '100.00'],
            ['Gasto Lejía', '15.00'],
        ];

        $csv = CsvExporter::generateCsv($headers, $rows);

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('CONCEPTO,MONTO', $csv);
        $this->assertStringContainsString('"Habitación 101",100.00', $csv);
    }
}
