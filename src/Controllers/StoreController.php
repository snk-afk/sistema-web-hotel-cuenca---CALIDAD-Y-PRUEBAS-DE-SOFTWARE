<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\InvoiceService;
use App\Services\StoreService;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class StoreController
{
    private StoreService $store_service;
    private InvoiceService $invoice_service;

    public function __construct(
        ?StoreService $store_service = null,
        ?InvoiceService $invoice_service = null
    ) {
        $this->store_service = $store_service ?? new StoreService();
        $this->invoice_service = $invoice_service ?? new InvoiceService();
    }

    public function sellDirect(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $user_id = Session::requireAuth();
        $payment_method = is_string($body['payment_method'] ?? null) ? $body['payment_method'] : 'cash';
        $doc_type = is_string($body['doc_type'] ?? null) ? strtoupper($body['doc_type']) : 'NOTA_VENTA';
        $customer_doc = is_string($body['customer_doc'] ?? null) ? trim($body['customer_doc']) : '00000000';
        $customer_name = is_string($body['customer_name'] ?? null) && trim($body['customer_name']) !== '' ? trim($body['customer_name']) : 'CLIENTE GENERAL';
        $customer_doc_type = $doc_type === 'FACTURA' ? 'RUC' : 'DNI';

        /** @var array<int, array{product_id: int, quantity: int}> $items */
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        $idempotency_key = is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null)
            ? trim($_SERVER['HTTP_IDEMPOTENCY_KEY'])
            : '';

        if ($idempotency_key === '') {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'El encabezado Idempotency-Key es obligatorio para una venta.',
            ]);
            return;
        }

        try {
            $sale = $this->store_service->sellDirectPOS(
                user_id: $user_id,
                items_payload: $items,
                payment_method: $payment_method,
                idempotency_key: $idempotency_key,
            );

            // Emitir comprobante oficial automáticamente (Boleta, Factura o Nota de Venta)
            $invoice_items = [];
            foreach ($sale->items as $item) {
                $product = $this->store_service->getProductRepository()->findById($item->product_id);
                $p_name = $product !== null ? $product->name : "Item #{$item->product_id}";
                $invoice_items[] = [
                    'description' => "Item Tienda: {$p_name}",
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'subtotal' => $item->subtotal,
                ];
            }

            $invoice = $this->invoice_service->issueInvoice(
                document_type: in_array($doc_type, ['BOLETA', 'FACTURA', 'NOTA_VENTA'], true) ? $doc_type : 'NOTA_VENTA',
                customer_document_type: $customer_doc_type,
                customer_document_number: $customer_doc !== '' ? $customer_doc : '00000000',
                customer_name: $customer_name,
                total_amount: $sale->total_amount,
                items: $invoice_items,
                created_by_user_id: $user_id,
                store_sale_id: $sale->id,
                idempotency_key: $idempotency_key,
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Venta en tienda realizada exitosamente',
                'data' => [
                    'sale' => $sale,
                    'invoice' => $invoice,
                    'voucher_url' => $invoice->voucher_url,
                    'pdf_url' => $invoice->pdf_url,
                ],
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

    public function chargeToRoom(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $room_id = is_numeric($body['room_id'] ?? null) ? (int) $body['room_id'] : 0;
        $user_id = Session::requireAuth();
        /** @var array<int, array{product_id: int, quantity: int}> $items */
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];

        try {
            $this->store_service->chargeToRoom($room_id, $items, $user_id);

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Consumo cargado a la habitación exitosamente',
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
}
