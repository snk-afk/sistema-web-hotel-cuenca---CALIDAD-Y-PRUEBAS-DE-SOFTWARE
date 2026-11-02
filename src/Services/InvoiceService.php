<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\Invoice;
use App\Repositories\FolioRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\InvoiceSeriesRepository;
use App\Repositories\InvoiceTransmissionRepository;
use App\Support\PdfGenerator;
use App\Support\StorageProvider;
use App\Support\SunatProvider;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class InvoiceService
{
    private PDO $db;
    private InvoiceRepository $invoice_repository;
    private InvoiceSeriesRepository $series_repository;
    private FolioRepository $folio_repository;
    private SunatProvider $sunat_provider;
    private StorageProvider $storage_provider;
    private InvoiceTransmissionRepository $transmission_repository;

    public function __construct(
        ?PDO $db = null,
        ?InvoiceRepository $invoice_repository = null,
        ?InvoiceSeriesRepository $series_repository = null,
        ?FolioRepository $folio_repository = null,
        ?SunatProvider $sunat_provider = null,
        ?StorageProvider $storage_provider = null,
        ?InvoiceTransmissionRepository $transmission_repository = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->invoice_repository = $invoice_repository ?? new InvoiceRepository($this->db);
        $this->series_repository = $series_repository ?? new InvoiceSeriesRepository($this->db);
        $this->folio_repository = $folio_repository ?? new FolioRepository($this->db);
        $this->sunat_provider = $sunat_provider ?? new SunatProvider();
        $this->storage_provider = $storage_provider ?? new StorageProvider();
        $this->transmission_repository = $transmission_repository ?? new InvoiceTransmissionRepository($this->db);
    }

    /**
     * @return array{taxable_amount: float, igv_amount: float, total_amount: float}
     */
    public function calculateTaxBreakdown(float $total_amount): array
    {
        if (!is_finite($total_amount) || $total_amount <= 0
            || abs($total_amount - round($total_amount, 2)) > 0.000001) {
            throw new InvalidArgumentException('El monto total del comprobante debe tener como máximo dos decimales.');
        }

        $taxable = round($total_amount / 1.18, 2);
        $igv = round($total_amount - $taxable, 2);

        return [
            'taxable_amount' => $taxable,
            'igv_amount' => $igv,
            'total_amount' => round($total_amount, 2),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function issueInvoice(
        string $document_type,
        string $customer_document_type,
        string $customer_document_number,
        string $customer_name,
        float $total_amount,
        array $items,
        int $created_by_user_id,
        ?int $stay_id = null,
        ?int $store_sale_id = null,
        ?string $customer_address = null,
        ?string $idempotency_key = null
    ): Invoice {
        $items = $this->validateIssuance(
            $document_type,
            $customer_document_type,
            $customer_document_number,
            $total_amount,
            $items,
        );
        $tax_breakdown = $this->calculateTaxBreakdown($total_amount);
        $idempotency_key = trim((string) $idempotency_key);
        if ($idempotency_key === '') {
            $idempotency_key = hash('sha256', random_bytes(32));
        } else {
            $idempotency_key = hash('sha256', $idempotency_key);
        }

        $existing_transmission = $this->transmission_repository->findByIdempotencyKey($idempotency_key);
        $invoice = null;
        if ($existing_transmission !== null) {
            $invoice = $this->invoice_repository->findById($existing_transmission['invoice_id']);
            if ($invoice === null) {
                throw new RuntimeException('La transmisión del comprobante no tiene factura asociada.');
            }

            if (in_array($existing_transmission['status'], ['accepted', 'rejected'], true)) {
                return $invoice;
            }
        }

        if ($invoice === null) {
            $series_data = $this->reserveNumber($document_type);
            $owns_transaction = !$this->db->inTransaction();
            if ($owns_transaction) {
                $this->db->beginTransaction();
            }

            try {
                $invoice = $this->invoice_repository->create(
                    stay_id: $stay_id,
                    store_sale_id: $store_sale_id,
                    document_type: $document_type,
                    series: $series_data['series'],
                    number: $series_data['number'],
                    full_number: $series_data['full_number'],
                    customer_document_type: $customer_document_type,
                    customer_document_number: $customer_document_number,
                    customer_name: $customer_name,
                    customer_address: $customer_address,
                    taxable_amount: $tax_breakdown['taxable_amount'],
                    igv_amount: $tax_breakdown['igv_amount'],
                    total_amount: $tax_breakdown['total_amount'],
                    sunat_status: 'pending',
                    sunat_response_code: null,
                    pdf_url: null,
                    xml_url: null,
                    voucher_url: null,
                    created_by_user_id: $created_by_user_id,
                    items: $items,
                );
                $this->transmission_repository->createPending(
                    invoice_id: $invoice->id,
                    provider: 'sunat',
                    idempotency_key: $idempotency_key,
                );

                if ($owns_transaction) {
                    $this->db->commit();
                }
            } catch (\Throwable $e) {
                if ($owns_transaction && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                throw $e;
            }
        }

        $full_number = $invoice->full_number;
        $date = date('Y-m-d H:i:s');
        $title = $document_type === 'FACTURA'
            ? 'FACTURA ELECTRONICA'
            : ($document_type === 'BOLETA' ? 'BOLETA DE VENTA ELECTRONICA' : 'NOTA DE VENTA');
        $document_data = [
            'title' => $title,
            'number' => $full_number,
            'date' => $date,
            'customer_name' => $customer_name,
            'customer_doc_type' => $customer_document_type,
            'customer_doc_number' => $customer_document_number,
            'customer_address' => $customer_address,
            'taxable_amount' => $tax_breakdown['taxable_amount'],
            'igv_amount' => $tax_breakdown['igv_amount'],
            'total_amount' => $tax_breakdown['total_amount'],
            'items' => $items,
        ];
        $pdf_content = PdfGenerator::generateInvoicePdf($document_data);
        $voucher_content = PdfGenerator::generateThermalVoucherPdf($document_data);
        $xml_content = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<Invoice xmlns=\"urn:oasis:names:specification:ubl:schema:xsd:Invoice-2\">\n"
            . "  <ID>{$full_number}</ID>\n"
            . '  <IssueDate>' . date('Y-m-d') . "</IssueDate>\n"
            . "  <Customer>{$customer_name}</Customer>\n"
            . "  <TotalAmount>{$total_amount}</TotalAmount>\n"
            . "</Invoice>";

        try {
            $storage_files = $this->storage_provider->storeInvoiceFiles(
                full_number: $full_number,
                pdf_content: $pdf_content,
                xml_content: $xml_content,
                voucher_content: $voucher_content,
            );
        } catch (\Throwable $e) {
            $this->persistFailure($invoice->id, 'STORAGE');
            throw $e;
        }

        try {
            $sunat_response = $this->sunat_provider->sendDocument([
                'document_type' => $document_type,
                'full_number' => $full_number,
                'customer_doc' => $customer_document_number,
                'total' => $total_amount,
                'idempotency_key' => $idempotency_key,
            ]);
        } catch (\Throwable) {
            $sunat_response = [
                'status' => 'failed',
                'code' => 'EXTERNAL_ERROR',
                'message' => 'No se pudo procesar el comprobante en SUNAT.',
            ];
        }

        $accepted = in_array($sunat_response['status'], ['accepted', 'simulated'], true);
        $transmission_status = $sunat_response['status'] === 'simulated'
            ? 'accepted'
            : $sunat_response['status'];
        $result_urls = $accepted
            ? $storage_files
            : ['pdf_url' => null, 'xml_url' => null, 'voucher_url' => null];

        $owns_result_transaction = !$this->db->inTransaction();
        if ($owns_result_transaction) {
            $this->db->beginTransaction();
        }
        try {
            $invoice = $this->invoice_repository->updateIssuanceResult(
                invoice_id: $invoice->id,
                sunat_status: $sunat_response['status'],
                sunat_response_code: $sunat_response['code'],
                pdf_url: $result_urls['pdf_url'],
                xml_url: $result_urls['xml_url'],
                voucher_url: $result_urls['voucher_url'],
            );
            $this->transmission_repository->recordAttempt(
                invoice_id: $invoice->id,
                status: $transmission_status,
                provider_code: $sunat_response['code'],
                provider_message: $sunat_response['message'],
                attempted_at: new DateTimeImmutable(),
            );

            if ($accepted && $stay_id !== null) {
                $folio = $this->folio_repository->findByStayId($stay_id);
                if ($folio !== null) {
                    $this->folio_repository->closeFolio($folio->id);
                }
            }

            if ($owns_result_transaction) {
                $this->db->commit();
            }

            return $invoice;
        } catch (\Throwable $e) {
            if ($owns_result_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array{description: string, quantity: int, unit_price: float, subtotal: float}>
     */
    private function validateIssuance(
        string $document_type,
        string $customer_document_type,
        string $customer_document_number,
        float $total_amount,
        array $items
    ): array {
        if (!in_array($document_type, ['BOLETA', 'FACTURA', 'NOTA_VENTA'], true)) {
            throw new InvalidArgumentException("Tipo de comprobante inválido: {$document_type}");
        }
        if ($document_type === 'FACTURA'
            && ($customer_document_type !== 'RUC' || strlen(trim($customer_document_number)) !== 11)) {
            throw new InvalidArgumentException('Para emitir Factura se requiere un RUC válido de 11 dígitos.');
        }
        if (!is_finite($total_amount) || $total_amount <= 0
            || abs($total_amount - round($total_amount, 2)) > 0.000001) {
            throw new InvalidArgumentException('El monto total del comprobante debe ser mayor a cero y tener como máximo dos decimales.');
        }
        if ($items === []) {
            throw new InvalidArgumentException('El comprobante debe incluir al menos un ítem en el detalle.');
        }

        $normalized_items = [];
        $items_total = 0.0;
        foreach ($items as $item) {
            if (!is_string($item['description'] ?? null)
                || trim($item['description']) === ''
                || !is_int($item['quantity'])
                || $item['quantity'] <= 0
                || !is_numeric($item['unit_price'] ?? null)
                || !is_numeric($item['subtotal'] ?? null)) {
                throw new InvalidArgumentException('Cada ítem del comprobante debe tener descripción, cantidad y monto válidos.');
            }

            $unit_price = (float) $item['unit_price'];
            $subtotal = (float) $item['subtotal'];
            $expected_subtotal = round($unit_price * $item['quantity'], 2);
            if (!is_finite($unit_price) || $unit_price < 0
                || abs($unit_price - round($unit_price, 2)) > 0.000001
                || !is_finite($subtotal) || $subtotal < 0
                || abs($subtotal - round($subtotal, 2)) > 0.000001
                || abs($subtotal - $expected_subtotal) > 0.000001) {
                throw new InvalidArgumentException('Los montos del detalle del comprobante no cuadran.');
            }

            $normalized_items[] = [
                'description' => trim($item['description']),
                'quantity' => $item['quantity'],
                'unit_price' => $unit_price,
                'subtotal' => $subtotal,
            ];
            $items_total += $subtotal;
        }

        if (abs(round($items_total, 2) - $total_amount) > 0.000001) {
            throw new InvalidArgumentException('El total del comprobante no coincide con sus ítems.');
        }

        return $normalized_items;
    }

    /**
     * @return array{series: string, number: int, full_number: string}
     */
    private function reserveNumber(string $document_type): array
    {
        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }
        try {
            $series_data = $this->series_repository->getNextNumberAndIncrement($document_type);
            if ($owns_transaction) {
                $this->db->commit();
            }
            return $series_data;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function persistFailure(int $invoice_id, string $code): void
    {
        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }
        try {
            $this->invoice_repository->updateIssuanceResult(
                invoice_id: $invoice_id,
                sunat_status: 'failed',
                sunat_response_code: $code,
                pdf_url: null,
                xml_url: null,
                voucher_url: null,
            );
            $this->transmission_repository->recordAttempt(
                invoice_id: $invoice_id,
                status: 'failed',
                provider_code: $code,
                provider_message: 'La operación no pudo completarse.',
                attempted_at: new DateTimeImmutable(),
            );
            if ($owns_transaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, Invoice>
     */
    public function listInvoices(): array
    {
        return $this->invoice_repository->findAll();
    }

    public function getInvoiceById(int $id): ?Invoice
    {
        return $this->invoice_repository->findById($id);
    }
}
