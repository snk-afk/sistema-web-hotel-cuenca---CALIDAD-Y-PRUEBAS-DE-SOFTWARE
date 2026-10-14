<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use PDO;

final class InvoiceRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?Invoice
    {
        $stmt = $this->db->prepare(
            'SELECT id, stay_id, store_sale_id, document_type, series, number, full_number, customer_document_type, customer_document_number, customer_name, customer_address, taxable_amount, igv_amount, total_amount, sunat_status, sunat_response_code, pdf_url, xml_url, voucher_url, created_by_user_id, created_at
             FROM invoices
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, stay_id: int|string|null, store_sale_id: int|string|null, document_type: string, series: string, number: int|string, full_number: string, customer_document_type: string, customer_document_number: string, customer_name: string, customer_address: ?string, taxable_amount: float|string, igv_amount: float|string, total_amount: float|string, sunat_status: string, sunat_response_code: ?string, pdf_url: ?string, xml_url: ?string, voucher_url: ?string, created_by_user_id: int|string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $items = $this->findItemsByInvoiceId((int) $row['id']);

        return new Invoice(
            id: (int) $row['id'],
            stay_id: $row['stay_id'] !== null ? (int) $row['stay_id'] : null,
            store_sale_id: $row['store_sale_id'] !== null ? (int) $row['store_sale_id'] : null,
            document_type: $row['document_type'],
            series: $row['series'],
            number: (int) $row['number'],
            full_number: $row['full_number'],
            customer_document_type: $row['customer_document_type'],
            customer_document_number: $row['customer_document_number'],
            customer_name: $row['customer_name'],
            customer_address: $row['customer_address'],
            taxable_amount: (float) $row['taxable_amount'],
            igv_amount: (float) $row['igv_amount'],
            total_amount: (float) $row['total_amount'],
            sunat_status: $row['sunat_status'],
            sunat_response_code: $row['sunat_response_code'],
            pdf_url: $row['pdf_url'],
            xml_url: $row['xml_url'],
            voucher_url: $row['voucher_url'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            items: $items,
        );
    }

    /**
     * @return array<int, InvoiceItem>
     */
    public function findItemsByInvoiceId(int $invoice_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, invoice_id, description, quantity, unit_price, subtotal
             FROM invoice_items
             WHERE invoice_id = :invoice_id
             ORDER BY id ASC'
        );

        $stmt->execute(['invoice_id' => $invoice_id]);
        /** @var array<int, array{id: int|string, invoice_id: int|string, description: string, quantity: int|string, unit_price: float|string, subtotal: float|string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): InvoiceItem => new InvoiceItem(
                id: (int) $row['id'],
                invoice_id: (int) $row['invoice_id'],
                description: $row['description'],
                quantity: (int) $row['quantity'],
                unit_price: (float) $row['unit_price'],
                subtotal: (float) $row['subtotal'],
            ),
            $rows
        );
    }

    /**
     * @return array<int, Invoice>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, stay_id, store_sale_id, document_type, series, number, full_number, customer_document_type, customer_document_number, customer_name, customer_address, taxable_amount, igv_amount, total_amount, sunat_status, sunat_response_code, pdf_url, xml_url, voucher_url, created_by_user_id, created_at
             FROM invoices
             ORDER BY id DESC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, stay_id: int|string|null, store_sale_id: int|string|null, document_type: string, series: string, number: int|string, full_number: string, customer_document_type: string, customer_document_number: string, customer_name: string, customer_address: ?string, taxable_amount: float|string, igv_amount: float|string, total_amount: float|string, sunat_status: string, sunat_response_code: ?string, pdf_url: ?string, xml_url: ?string, voucher_url: ?string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): Invoice {
                $items = $this->findItemsByInvoiceId((int) $row['id']);
                return new Invoice(
                    id: (int) $row['id'],
                    stay_id: $row['stay_id'] !== null ? (int) $row['stay_id'] : null,
                    store_sale_id: $row['store_sale_id'] !== null ? (int) $row['store_sale_id'] : null,
                    document_type: $row['document_type'],
                    series: $row['series'],
                    number: (int) $row['number'],
                    full_number: $row['full_number'],
                    customer_document_type: $row['customer_document_type'],
                    customer_document_number: $row['customer_document_number'],
                    customer_name: $row['customer_name'],
                    customer_address: $row['customer_address'],
                    taxable_amount: (float) $row['taxable_amount'],
                    igv_amount: (float) $row['igv_amount'],
                    total_amount: (float) $row['total_amount'],
                    sunat_status: $row['sunat_status'],
                    sunat_response_code: $row['sunat_response_code'],
                    pdf_url: $row['pdf_url'],
                    xml_url: $row['xml_url'],
                    voucher_url: $row['voucher_url'],
                    created_by_user_id: (int) $row['created_by_user_id'],
                    created_at: $row['created_at'],
                    items: $items,
                );
            },
            $rows
        );
    }

    /**
     * @param array<int, array{description: string, quantity: int, unit_price: float, subtotal: float}> $items
     */
    public function create(
        ?int $stay_id,
        ?int $store_sale_id,
        string $document_type,
        string $series,
        int $number,
        string $full_number,
        string $customer_document_type,
        string $customer_document_number,
        string $customer_name,
        ?string $customer_address,
        float $taxable_amount,
        float $igv_amount,
        float $total_amount,
        string $sunat_status,
        ?string $sunat_response_code,
        ?string $pdf_url,
        ?string $xml_url,
        ?string $voucher_url,
        int $created_by_user_id,
        array $items = []
    ): Invoice {
        $stmt = $this->db->prepare(
            'INSERT INTO invoices (stay_id, store_sale_id, document_type, series, number, full_number, customer_document_type, customer_document_number, customer_name, customer_address, taxable_amount, igv_amount, total_amount, sunat_status, sunat_response_code, pdf_url, xml_url, voucher_url, created_by_user_id)
             VALUES (:stay_id, :store_sale_id, :document_type, :series, :number, :full_number, :customer_document_type, :customer_document_number, :customer_name, :customer_address, :taxable_amount, :igv_amount, :total_amount, :sunat_status, :sunat_response_code, :pdf_url, :xml_url, :voucher_url, :created_by_user_id)'
        );

        $stmt->execute([
            'stay_id' => $stay_id,
            'store_sale_id' => $store_sale_id,
            'document_type' => $document_type,
            'series' => $series,
            'number' => $number,
            'full_number' => $full_number,
            'customer_document_type' => $customer_document_type,
            'customer_document_number' => $customer_document_number,
            'customer_name' => $customer_name,
            'customer_address' => $customer_address,
            'taxable_amount' => $taxable_amount,
            'igv_amount' => $igv_amount,
            'total_amount' => $total_amount,
            'sunat_status' => $sunat_status,
            'sunat_response_code' => $sunat_response_code,
            'pdf_url' => $pdf_url,
            'xml_url' => $xml_url,
            'voucher_url' => $voucher_url,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $invoice_id = (int) $this->db->lastInsertId();

        $item_stmt = $this->db->prepare(
            'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, subtotal)
             VALUES (:invoice_id, :description, :quantity, :unit_price, :subtotal)'
        );

        foreach ($items as $item) {
            $item_stmt->execute([
                'invoice_id' => $invoice_id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'subtotal' => $item['subtotal'],
            ]);
        }

        /** @var Invoice */
        return $this->findById($invoice_id);
    }

    public function updateIssuanceResult(
        int $invoice_id,
        string $sunat_status,
        ?string $sunat_response_code,
        ?string $pdf_url,
        ?string $xml_url,
        ?string $voucher_url
    ): Invoice {
        $statement = $this->db->prepare(
            'UPDATE invoices SET sunat_status = :sunat_status, sunat_response_code = :sunat_response_code, '
            . 'pdf_url = :pdf_url, xml_url = :xml_url, voucher_url = :voucher_url WHERE id = :id'
        );
        $statement->execute([
            'sunat_status' => $sunat_status,
            'sunat_response_code' => $sunat_response_code,
            'pdf_url' => $pdf_url,
            'xml_url' => $xml_url,
            'voucher_url' => $voucher_url,
            'id' => $invoice_id,
        ]);

        $invoice = $this->findById($invoice_id);
        if ($invoice === null) {
            throw new \RuntimeException('No se pudo actualizar el estado del comprobante.');
        }

        return $invoice;
    }
}
