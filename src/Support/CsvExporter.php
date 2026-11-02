<?php

declare(strict_types=1);

namespace App\Support;

final class CsvExporter
{
    /**
     * @param array<int, string> $headers
     * @param array<int, array<int, scalar|null>> $rows
     */
    public static function generateCsv(array $headers, array $rows): string
    {
        $output = fopen('php://temp', 'r+');
        if ($output === false) {
            return '';
        }

        // UTF-8 BOM for Microsoft Excel compatibility
        fwrite($output, "\xEF\xBB\xBF");

        fputcsv($output, $headers, ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($output, $row, ',', '"', '\\');
        }

        rewind($output);
        $csv_content = stream_get_contents($output);
        fclose($output);

        return is_string($csv_content) ? $csv_content : '';
    }
}
