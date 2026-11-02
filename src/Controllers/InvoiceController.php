<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\InvoiceService;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class InvoiceController
{
    private InvoiceService $invoice_service;

    public function __construct(?InvoiceService $invoice_service = null)
    {
        $this->invoice_service = $invoice_service ?? new InvoiceService();
    }

    public function index(): void
    {
        $invoices = $this->invoice_service->listInvoices();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $invoices,
        ]);
    }

    public function issue(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $document_type = is_string($body['document_type'] ?? null) ? $body['document_type'] : 'BOLETA';
        $customer_document_type = is_string($body['customer_document_type'] ?? null) ? $body['customer_document_type'] : 'DNI';
        $customer_document_number = is_string($body['customer_document_number'] ?? null) ? $body['customer_document_number'] : '';
        $customer_name = is_string($body['customer_name'] ?? null) ? $body['customer_name'] : '';
        $customer_address = is_string($body['customer_address'] ?? null) ? $body['customer_address'] : null;
        $total_amount = is_numeric($body['total_amount'] ?? null) ? (float) $body['total_amount'] : 0.0;
        $user_id = Session::requireAuth();
        $stay_id = is_numeric($body['stay_id'] ?? null) ? (int) $body['stay_id'] : null;
        $store_sale_id = is_numeric($body['store_sale_id'] ?? null) ? (int) $body['store_sale_id'] : null;

        /** @var array<int, array{description: string, quantity: int, unit_price: float, subtotal: float}> $items */
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        $idempotency_key = is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null)
            ? trim($_SERVER['HTTP_IDEMPOTENCY_KEY'])
            : '';

        if ($idempotency_key === '') {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'El encabezado Idempotency-Key es obligatorio para emitir comprobantes.',
            ]);
            return;
        }

        try {
            $invoice = $this->invoice_service->issueInvoice(
                document_type: $document_type,
                customer_document_type: $customer_document_type,
                customer_document_number: $customer_document_number,
                customer_name: $customer_name,
                total_amount: $total_amount,
                items: $items,
                created_by_user_id: $user_id,
                stay_id: $stay_id,
                store_sale_id: $store_sale_id,
                customer_address: $customer_address,
                idempotency_key: $idempotency_key,
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Comprobante emitido exitosamente',
                'data' => $invoice,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (DomainException $e) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function show(int $id): void
    {
        $invoice = $this->invoice_service->getInvoiceById($id);

        if ($invoice === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Comprobante no encontrado']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $invoice,
        ]);
    }

    public function download(): void
    {
        $type = is_string($_GET['type'] ?? null) ? $_GET['type'] : 'pdf';
        $number = is_string($_GET['number'] ?? null) ? $_GET['number'] : '';

        // Sanitize number to prevent directory traversal
        $number = preg_replace('/[^A-Za-z0-9_\-]/', '', $number) ?? '';
        if ($number === '') {
            http_response_code(400);
            echo 'Número de comprobante inválido.';
            return;
        }

        if ($type === 'xml') {
            $suffix = '';
            $ext = 'xml';
        } elseif ($type === 'voucher') {
            $suffix = '_voucher';
            $ext = 'pdf';
        } else {
            $suffix = '';
            $ext = 'pdf';
        }

        (new StorageController())->download("{$number}{$suffix}.{$ext}");
    }

    public function escpos(int $id): void
    {
        $invoice = $this->invoice_service->getInvoiceById($id);

        if ($invoice === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Comprobante no encontrado']);
            return;
        }

        $open_drawer = isset($_GET['open_drawer']) && $_GET['open_drawer'] === '1';
        $raw_binary = \App\Support\EscPosFormatter::formatInvoice($invoice, open_drawer: $open_drawer, cut_paper: true);

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: inline; filename="' . $invoice->full_number . '.bin"');
        echo $raw_binary;
    }
}
