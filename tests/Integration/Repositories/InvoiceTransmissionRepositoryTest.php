<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\InvoiceTransmissionRepository;
use Tests\Support\MySqlTestCase;

final class InvoiceTransmissionRepositoryTest extends MySqlTestCase
{
    public function testPersistsAndReadsTransmissionAgainstMysqlSchema(): void
    {
        $this->createUserFixture(1, 'transmission');
        $statement = $this->pdo()->prepare(
            'INSERT INTO invoices '
            . '(document_type, series, number, full_number, customer_document_type, customer_document_number, '
            . 'customer_name, taxable_amount, igv_amount, total_amount, sunat_status, created_by_user_id) '
            . 'VALUES (:document_type, :series, :number, :full_number, :customer_document_type, '
            . ':customer_document_number, :customer_name, :taxable_amount, :igv_amount, :total_amount, '
            . ':sunat_status, :created_by_user_id)'
        );
        $statement->execute([
            'document_type' => 'BOLETA',
            'series' => 'B001',
            'number' => 1,
            'full_number' => 'B001-00000001',
            'customer_document_type' => 'DNI',
            'customer_document_number' => '72819283',
            'customer_name' => 'Cliente de integración',
            'taxable_amount' => 100.00,
            'igv_amount' => 18.00,
            'total_amount' => 118.00,
            'sunat_status' => 'pending',
            'created_by_user_id' => 1,
        ]);
        $invoice_id = (int) $this->pdo()->lastInsertId();

        $repository = new InvoiceTransmissionRepository($this->pdo());
        $repository->createPending($invoice_id, 'sunat', 'integration-idempotency-key');
        $stored = $repository->findByIdempotencyKey('integration-idempotency-key');

        self::assertNotNull($stored);
        self::assertSame($invoice_id, $stored['invoice_id']);
        self::assertSame('pending', $stored['status']);
    }
}
