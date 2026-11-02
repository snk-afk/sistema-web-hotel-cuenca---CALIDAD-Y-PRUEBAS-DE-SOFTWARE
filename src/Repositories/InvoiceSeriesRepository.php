<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\InvoiceSeries;
use PDO;
use RuntimeException;

final class InvoiceSeriesRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findByDocumentType(string $document_type): ?InvoiceSeries
    {
        $stmt = $this->db->prepare(
            'SELECT id, document_type, series, current_number, is_active, created_at
             FROM invoice_series
             WHERE document_type = :document_type AND is_active = 1
             LIMIT 1'
        );

        $stmt->execute(['document_type' => $document_type]);
        /** @var array{id: int|string, document_type: string, series: string, current_number: int|string, is_active: int|string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new InvoiceSeries(
            id: (int) $row['id'],
            document_type: $row['document_type'],
            series: $row['series'],
            current_number: (int) $row['current_number'],
            is_active: (bool) $row['is_active'],
            created_at: $row['created_at'],
        );
    }

    public function createSeries(string $document_type, string $series, int $initial_number = 0): InvoiceSeries
    {
        $stmt = $this->db->prepare(
            'INSERT INTO invoice_series (document_type, series, current_number, is_active)
             VALUES (:document_type, :series, :initial_number, 1)'
        );

        $stmt->execute([
            'document_type' => $document_type,
            'series' => $series,
            'initial_number' => $initial_number,
        ]);

        /** @var InvoiceSeries */
        return $this->findByDocumentType($document_type);
    }

    /**
     * @return array{series: string, number: int, full_number: string}
     */
    public function getNextNumberAndIncrement(string $document_type): array
    {
        $series_obj = $this->findByDocumentType($document_type);

        if ($series_obj === null) {
            $default_series = match ($document_type) {
                'BOLETA' => 'B001',
                'FACTURA' => 'F001',
                default => 'NV01',
            };
            $series_obj = $this->createSeries($document_type, $default_series, 0);
        }

        // Safety check: ensure current_number is not behind the actual max number
        // in the invoices table (can happen if data was seeded/migrated manually).
        $max_stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(number), 0) FROM invoices WHERE document_type = :dt AND series = :series'
        );
        $max_stmt->execute(['dt' => $document_type, 'series' => $series_obj->series]);
        $max_used = (int) $max_stmt->fetchColumn();

        // Use a local variable for the effective current number to avoid mutating
        // the readonly model. If the DB is behind the invoices table, sync forward.
        $effective_current_number = max($series_obj->current_number, $max_used);

        $next_number = $effective_current_number + 1;

        // Atomic increment with conditional WHERE to prevent duplicate numbering
        $stmt = $this->db->prepare(
            'UPDATE invoice_series
             SET current_number = :next_number
             WHERE id = :id AND current_number = :current_number'
        );

        $stmt->execute([
            'next_number' => $next_number,
            'id' => $series_obj->id,
            'current_number' => $effective_current_number,
        ]);

        // Verify the row was actually updated (optimistic lock check)
        if ($stmt->rowCount() === 0) {
            throw new RuntimeException(
                "No se pudo reservar el número de comprobante: otro proceso lo tomó. Reintente."
            );
        }

        return [
            'series' => $series_obj->series,
            'number' => $next_number,
            'full_number' => sprintf('%s-%08d', $series_obj->series, $next_number),
        ];
    }
}
