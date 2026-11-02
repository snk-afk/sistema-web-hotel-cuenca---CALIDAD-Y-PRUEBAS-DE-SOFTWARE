<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\GuestService;
use App\Services\InvoiceService;
use App\Services\RoomService;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class RoomController
{
    private RoomService $room_service;
    private ?GuestService $guest_service;
    private ?InvoiceService $invoice_service;

    public function __construct(
        ?RoomService $room_service = null,
        ?GuestService $guest_service = null,
        ?InvoiceService $invoice_service = null
    ) {
        $this->room_service = $room_service ?? new RoomService();
        $this->guest_service = $guest_service;
        $this->invoice_service = $invoice_service;
    }

    private function getGuestService(): GuestService
    {
        return $this->guest_service ??= new GuestService();
    }

    private function getInvoiceService(): InvoiceService
    {
        return $this->invoice_service ??= new InvoiceService();
    }

    /**
     * @return list<array{amount: float|string, payment_method: string, reference_number: ?string, notes: ?string}>|null
     */
    private function parsePaymentParts(mixed $raw_payment_parts): ?array
    {
        if ($raw_payment_parts === null) {
            return null;
        }

        if (!is_array($raw_payment_parts)) {
            throw new InvalidArgumentException('Las partes de pago deben ser una lista.');
        }

        $payment_parts = [];
        foreach ($raw_payment_parts as $raw_payment_part) {
            if (!is_array($raw_payment_part)) {
                throw new InvalidArgumentException('Cada parte de pago debe ser un objeto.');
            }

            $raw_amount = $raw_payment_part['amount'] ?? null;
            $amount = is_numeric($raw_amount) ? (float) $raw_amount : '';
            $payment_method = is_string($raw_payment_part['payment_method'] ?? null)
                ? trim($raw_payment_part['payment_method'])
                : '';
            $reference_number = is_string($raw_payment_part['reference_number'] ?? null)
                ? trim($raw_payment_part['reference_number'])
                : null;
            $notes = is_string($raw_payment_part['notes'] ?? null)
                ? trim($raw_payment_part['notes'])
                : null;

            $payment_parts[] = [
                'amount' => $amount,
                'payment_method' => $payment_method,
                'reference_number' => $reference_number,
                'notes' => $notes,
            ];
        }

        return $payment_parts;
    }

    public function index(): void
    {
        $map = $this->room_service->getDashboardMap();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $map,
        ]);
    }

    public function categories(): void
    {
        $categories = $this->room_service->getCategoriesCatalog();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    public function tariffs(): void
    {
        $catalog = $this->room_service->getTariffsCatalog();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $catalog,
        ]);
    }

    public function handlePost(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $action = is_string($body['action'] ?? null) ? $body['action'] : '';
        $room_id = is_numeric($body['room_id'] ?? null) ? (int) $body['room_id'] : (is_numeric($body['from_room_id'] ?? null) ? (int) $body['from_room_id'] : 0);

        match ($action) {
            'create' => $this->createRoom($body),
            'update' => $this->updateRoom($room_id, $body),
            'occupy' => $this->occupy($room_id),
            'checkout' => $this->checkout($room_id),
            'extend' => $this->extend($room_id),
            'transfer' => $this->transfer($room_id),
            'update_status' => $this->updateStatus($room_id),
            default => (function (): void {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Acción no válida o no especificada.']);
            })(),
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    public function createRoom(array $body): void
    {
        $category_id = is_numeric($body['room_category_id'] ?? null) ? (int) $body['room_category_id'] : 0;
        $number = is_string($body['number'] ?? null) ? $body['number'] : '';
        $floor = is_string($body['floor'] ?? null) ? $body['floor'] : '1';
        $description = is_string($body['description'] ?? null) ? $body['description'] : null;

        try {
            $room = $this->room_service->createRoom(
                category_id: $category_id,
                number: $number,
                floor: $floor,
                description: $description
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => "Habitación {$room->number} registrada exitosamente",
                'data' => [
                    'id' => $room->id,
                    'number' => $room->number,
                    'floor' => $room->floor,
                    'category_id' => $room->room_category_id,
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

    /**
     * @param array<string, mixed> $body
     */
    public function updateRoom(int $room_id, array $body): void
    {
        $category_id = is_numeric($body['room_category_id'] ?? null) ? (int) $body['room_category_id'] : 0;
        $number = is_string($body['number'] ?? null) ? $body['number'] : '';
        $floor = is_string($body['floor'] ?? null) ? $body['floor'] : '1';
        $description = is_string($body['description'] ?? null) ? $body['description'] : null;

        try {
            $room = $this->room_service->updateRoom(
                id: $room_id,
                category_id: $category_id,
                number: $number,
                floor: $floor,
                description: $description
            );

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => "Habitación {$room->number} actualizada exitosamente",
                'data' => [
                    'id' => $room->id,
                    'number' => $room->number,
                    'floor' => $room->floor,
                    'category_id' => $room->room_category_id,
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

    public function handleCategoryPost(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $id = is_numeric($body['id'] ?? null) ? (int) $body['id'] : null;
        $name = is_string($body['name'] ?? null) ? $body['name'] : '';
        $description = is_string($body['description'] ?? null) ? $body['description'] : null;
        $is_active = is_numeric($body['is_active'] ?? null) ? (int) $body['is_active'] : 1;

        try {
            if ($id !== null && $id > 0) {
                $cat = $this->room_service->updateCategory($id, $name, $description, $is_active);
                $msg = "Categoría {$cat->name} actualizada exitosamente";
            } else {
                $cat = $this->room_service->createCategory($name, $description);
                http_response_code(201);
                $msg = "Categoría {$cat->name} creada exitosamente";
            }

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => $msg,
                'data' => [
                    'id' => $cat->id,
                    'name' => $cat->name,
                    'description' => $cat->description,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function handleTariffPost(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $id = is_numeric($body['id'] ?? null) ? (int) $body['id'] : null;
        $category_id = is_numeric($body['room_category_id'] ?? null) ? (int) $body['room_category_id'] : 0;
        $name = is_string($body['name'] ?? null) ? $body['name'] : '';
        $duration_hours = is_numeric($body['duration_hours'] ?? null) ? (int) $body['duration_hours'] : 12;
        $price = is_numeric($body['price'] ?? null) ? (float) $body['price'] : 0.0;
        $is_active = is_numeric($body['is_active'] ?? null) ? (int) $body['is_active'] : 1;

        try {
            if ($id !== null && $id > 0) {
                $tariff = $this->room_service->updateTariff($id, $category_id, $name, $duration_hours, $price, $is_active);
                $msg = "Tarifa {$tariff->name} actualizada exitosamente";
            } else {
                $tariff = $this->room_service->createTariff($category_id, $name, $duration_hours, $price);
                http_response_code(201);
                $msg = "Tarifa {$tariff->name} registrada exitosamente";
            }

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => $msg,
                'data' => [
                    'id' => $tariff->id,
                    'name' => $tariff->name,
                    'price' => $tariff->price,
                    'duration_hours' => $tariff->duration_hours,
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function occupy(int $room_id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $tariff_id = is_numeric($body['tariff_id'] ?? null) ? (int) $body['tariff_id'] : 0;
        $applied_price = is_numeric($body['applied_price'] ?? null) ? (float) $body['applied_price'] : 0.0;
        $discount_reason = is_string($body['discount_reason'] ?? null) ? $body['discount_reason'] : null;
        $created_by_user_id = Session::requireAuth();
        $guest_id = is_numeric($body['guest_id'] ?? null) ? (int) $body['guest_id'] : null;
        $custom_check_in_time = is_string($body['custom_check_in_time'] ?? null) && trim($body['custom_check_in_time']) !== '' ? trim($body['custom_check_in_time']) : null;
        $custom_check_out_time = is_string($body['custom_check_out_time'] ?? null) && trim($body['custom_check_out_time']) !== '' ? trim($body['custom_check_out_time']) : null;
        $initial_payment_amount = is_numeric($body['initial_payment_amount'] ?? null) ? (float) $body['initial_payment_amount'] : 0.0;
        $payment_method = is_string($body['payment_method'] ?? null) && in_array($body['payment_method'], ['cash', 'yape', 'plin', 'card', 'transfer'], true) ? $body['payment_method'] : 'cash';
        $payment_reference = is_string($body['payment_reference'] ?? null) ? $body['payment_reference'] : null;

        $guest_name = is_string($body['guest_name'] ?? null) ? trim($body['guest_name']) : null;
        $guest_doc = is_string($body['guest_doc'] ?? null) ? trim($body['guest_doc']) : null;
        $guest_doc_type = is_string($body['guest_doc_type'] ?? null) ? trim($body['guest_doc_type']) : 'DNI';

        if ($guest_id === null && (($guest_name !== null && $guest_name !== '') || ($guest_doc !== null && $guest_doc !== ''))) {
            $doc_number = $guest_doc !== null && $guest_doc !== '' ? $guest_doc : 'DNI-' . time();
            $name_val = $guest_name !== null && $guest_name !== '' ? $guest_name : 'Huésped Registrado';
            try {
                $guest = $this->getGuestService()->createGuest(
                    document_type: in_array(strtoupper($guest_doc_type), ['DNI', 'RUC', 'CE', 'PASAPORTE'], true) ? strtoupper($guest_doc_type) : 'DNI',
                    document_number: $doc_number,
                    first_name: $name_val
                );
                $guest_id = $guest->id;
            } catch (\Throwable) {
                // If auto-create encounters any constraint, proceed with guest_id null
            }
        }

        try {
            $payment_parts = $this->parsePaymentParts($body['payment_parts'] ?? null);
            $stay = $this->room_service->occupyRoom(
                room_id: $room_id,
                tariff_id: $tariff_id,
                applied_price: $applied_price,
                discount_reason: $discount_reason,
                created_by_user_id: $created_by_user_id,
                guest_id: $guest_id,
                custom_check_in_time: $custom_check_in_time,
                custom_check_out_time: $custom_check_out_time,
                initial_payment_amount: $initial_payment_amount,
                payment_method: $payment_method,
                payment_reference: $payment_reference,
                payment_parts: $payment_parts
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Habitación hospedada exitosamente',
                'data' => [
                    'stay_id' => $stay->id,
                    'room_id' => $stay->room_id,
                    'check_in_at' => $stay->check_in_at,
                    'expected_check_out_at' => $stay->expected_check_out_at,
                    'applied_price' => $stay->applied_price,
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

    public function extend(int $room_id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $added_hours = is_numeric($body['added_hours'] ?? null) ? (int) $body['added_hours'] : 0;
        $added_price = is_numeric($body['added_price'] ?? null) ? (float) $body['added_price'] : 0.0;
        $created_by_user_id = Session::requireAuth();

        try {
            $extension = $this->room_service->extendStay(
                room_id: $room_id,
                added_hours: $added_hours,
                added_price: $added_price,
                created_by_user_id: $created_by_user_id
            );

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Estancia extendida exitosamente',
                'data' => [
                    'extension_id' => $extension->id,
                    'stay_id' => $extension->stay_id,
                    'added_hours' => $extension->added_hours,
                    'added_price' => $extension->added_price,
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

    public function transfer(int $from_room_id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $to_room_id = is_numeric($body['to_room_id'] ?? null) ? (int) $body['to_room_id'] : 0;
        $reason = is_string($body['reason'] ?? null) ? $body['reason'] : '';
        $created_by_user_id = Session::requireAuth();

        try {
            $this->room_service->transferRoom(
                from_room_id: $from_room_id,
                to_room_id: $to_room_id,
                reason: $reason,
                created_by_user_id: $created_by_user_id
            );

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Transferencia de habitación realizada exitosamente',
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

    public function checkout(int $room_id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $payment_method = is_string($body['payment_method'] ?? null) && in_array($body['payment_method'], ['cash', 'yape', 'plin', 'card', 'transfer'], true) ? $body['payment_method'] : 'cash';
        $payment_reference = is_string($body['payment_reference'] ?? null) ? $body['payment_reference'] : null;
        $doc_type = is_string($body['document_type'] ?? null) ? strtoupper($body['document_type']) : 'NOTA_VENTA';
        $user_id = Session::requireAuth();

        try {
            $payment_parts = $this->parsePaymentParts($body['payment_parts'] ?? null);
            $room = $this->room_service->getRoomRepository()->findById($room_id);
            $room_num = $room !== null ? $room->number : '';
            $guest_name = 'HUÉSPED HABITACIÓN ' . $room_num;
            $guest_doc = '00000000';
            $guest_doc_type = 'DNI';

            $guest_id = ($room !== null && $room->current_stay !== null) ? $room->current_stay->guest_id : null;
            if ($guest_id !== null) {
                $g = $this->getGuestService()->getGuestById($guest_id);
                if ($g !== null) {
                    $fullname = trim($g->first_name . ' ' . $g->last_name);
                    $guest_name = $fullname !== '' ? $fullname : ($g->company_name ?? $guest_name);
                    $guest_doc = $g->document_number;
                    $guest_doc_type = $g->document_type;
                }
            }

            if ($doc_type === 'FACTURA') {
                $guest_doc_type = 'RUC';
                if (isset($body['customer_ruc']) && is_string($body['customer_ruc']) && trim($body['customer_ruc']) !== '') {
                    $guest_doc = trim($body['customer_ruc']);
                }
                if (isset($body['customer_company']) && is_string($body['customer_company']) && trim($body['customer_company']) !== '') {
                    $guest_name = trim($body['customer_company']);
                }
            }

            $stay_id = ($room !== null && $room->current_stay !== null) ? $room->current_stay->id : null;
            $items = [];
            $total_amount = 0.0;

            if ($stay_id !== null) {
                $folio = $this->room_service->getFolioService()->getOrCreateFolio($stay_id);
                foreach ($folio->charges as $ch) {
                    $items[] = [
                        'description' => $ch->description,
                        'quantity' => $ch->quantity,
                        'unit_price' => $ch->unit_price,
                        'subtotal' => $ch->total_amount,
                    ];
                    $total_amount += $ch->total_amount;
                }
            }

            $this->room_service->checkoutRoom(
                room_id: $room_id,
                payment_method: $payment_method,
                payment_reference: $payment_reference,
                user_id: $user_id,
                payment_parts: $payment_parts
            );

            $invoice = null;
            if ($stay_id !== null && $total_amount > 0 && $items !== []) {
                $idempotency_key = is_string($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? null)
                    ? trim($_SERVER['HTTP_IDEMPOTENCY_KEY'])
                    : 'checkout-stay-' . $stay_id;
                $invoice = $this->getInvoiceService()->issueInvoice(
                    document_type: in_array($doc_type, ['BOLETA', 'FACTURA', 'NOTA_VENTA'], true) ? $doc_type : 'NOTA_VENTA',
                    customer_document_type: $guest_doc_type,
                    customer_document_number: $guest_doc,
                    customer_name: $guest_name,
                    total_amount: $total_amount,
                    items: $items,
                    created_by_user_id: $user_id,
                    stay_id: $stay_id,
                    idempotency_key: $idempotency_key,
                );
            }

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Checkout completado y saldo liquidado. Habitación enviada a limpieza.',
                'data' => [
                    'invoice' => $invoice,
                    'voucher_url' => $invoice?->voucher_url,
                    'pdf_url' => $invoice?->pdf_url,
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

    public function updateStatus(int $room_id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $new_status = is_string($body['status'] ?? null) ? $body['status'] : '';

        try {
            $this->room_service->updateRoomStatus($room_id, $new_status);

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => "Estado de habitación actualizado a {$new_status}",
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
