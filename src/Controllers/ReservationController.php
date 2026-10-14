<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\GuestService;
use App\Services\ReservationService;
use App\Services\ReservationSettlementService;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class ReservationController
{
    private ReservationService $reservation_service;
    private ReservationSettlementService $settlement_service;
    private ?GuestService $guest_service;

    public function __construct(
        ?ReservationService $reservation_service = null,
        ?GuestService $guest_service = null,
        ?ReservationSettlementService $settlement_service = null
    ) {
        $this->reservation_service = $reservation_service ?? new ReservationService();
        $this->settlement_service = $settlement_service ?? new ReservationSettlementService();
        $this->guest_service = $guest_service;
    }

    private function getGuestService(): GuestService
    {
        return $this->guest_service ??= new GuestService();
    }

    public function index(): void
    {
        $search = is_string($_GET['search'] ?? null) ? $_GET['search'] : null;
        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : null;

        $reservations = $this->reservation_service->getReservationsCatalog($search, $status);
        $metrics = $this->reservation_service->getReservationMetrics();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $reservations,
            'metrics' => $metrics,
        ]);
    }

    public function show(int $id): void
    {
        $reservation = $this->reservation_service->getReservationById($id);

        if ($reservation === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Reserva no encontrada.']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $reservation,
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
        $reservation_id = is_numeric($body['reservation_id'] ?? null) ? (int) $body['reservation_id'] : (is_numeric($body['id'] ?? null) ? (int) $body['id'] : 0);

        match ($action) {
            'checkin' => $this->checkin($reservation_id, $body),
            'cancel' => $this->cancel(
                $reservation_id,
                is_string($body['reason'] ?? null) ? $body['reason'] : ''
            ),
            'no_show' => $this->noShow(
                $reservation_id,
                is_string($body['reason'] ?? null) ? $body['reason'] : ''
            ),
            'create', '' => $this->create($body),
            default => (function (): void {
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Acción no válida o no especificada.']);
            })(),
        };
    }

    /**
     * @param array<string, mixed>|null $input_body
     */
    public function create(?array $input_body = null): void
    {
        if ($input_body === null) {
            /** @var string|false $raw_input */
            $raw_input = file_get_contents('php://input');
            /** @var array<string, mixed>|null $body */
            $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;
            $input_body = is_array($body) ? $body : [];
        }

        $room_id = is_numeric($input_body['room_id'] ?? null) ? (int) $input_body['room_id'] : 0;
        $guest_id = is_numeric($input_body['guest_id'] ?? null) ? (int) $input_body['guest_id'] : 0;
        $tariff_id = is_numeric($input_body['tariff_id'] ?? null) ? (int) $input_body['tariff_id'] : (is_numeric($input_body['room_tariff_id'] ?? null) ? (int) $input_body['room_tariff_id'] : 0);
        $reserved_check_in_at = is_string($input_body['check_in_at'] ?? null) ? $input_body['check_in_at'] : (is_string($input_body['reserved_check_in_at'] ?? null) ? $input_body['reserved_check_in_at'] : '');
        $advance_payment = is_numeric($input_body['advance_payment'] ?? null) ? (float) $input_body['advance_payment'] : 0.0;
        $user_id = Session::requireAuth();
        $notes = is_string($input_body['notes'] ?? null) ? $input_body['notes'] : null;

        $guest_name = is_string($input_body['guest_name'] ?? null) ? trim($input_body['guest_name']) : null;
        $guest_doc = is_string($input_body['guest_doc'] ?? null) ? trim($input_body['guest_doc']) : (is_string($input_body['document_number'] ?? null) ? trim($input_body['document_number']) : null);
        $guest_doc_type = is_string($input_body['guest_doc_type'] ?? null) ? trim($input_body['guest_doc_type']) : (is_string($input_body['document_type'] ?? null) ? trim($input_body['document_type']) : 'DNI');

        if ($guest_id <= 0 && (($guest_name !== null && $guest_name !== '') || ($guest_doc !== null && $guest_doc !== ''))) {
            $doc_number = $guest_doc !== null && $guest_doc !== '' ? $guest_doc : 'DNI-' . time();
            $name_val = $guest_name !== null && $guest_name !== '' ? $guest_name : 'Huésped Registrado';
            $doc_type_val = in_array(strtoupper($guest_doc_type), ['DNI', 'RUC', 'CE', 'PASAPORTE'], true) ? strtoupper($guest_doc_type) : 'DNI';

            $existing_lookup = $this->getGuestService()->lookup($doc_type_val, $doc_number);
            if ($existing_lookup['guest'] !== null) {
                $guest_id = $existing_lookup['guest']->id;
            } else {
                try {
                    $new_guest = $this->getGuestService()->createGuest(
                        document_type: $doc_type_val,
                        document_number: $doc_number,
                        first_name: $name_val
                    );
                    $guest_id = $new_guest->id;
                } catch (\Throwable) {
                    // In case of duplicate or constraint, fallback
                }
            }
        }

        try {
            $payment_parts = $this->parsePaymentParts($input_body['payment_parts'] ?? null);
            $reservation = $this->reservation_service->createReservation(
                room_id: $room_id,
                guest_id: $guest_id,
                room_tariff_id: $tariff_id,
                reserved_check_in_at: $reserved_check_in_at,
                advance_payment: $advance_payment,
                created_by_user_id: $user_id,
                notes: $notes,
                payment_parts: $payment_parts
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Reserva creada exitosamente',
                'data' => [
                    'id' => $reservation->id,
                    'room_id' => $reservation->room_id,
                    'guest_id' => $reservation->guest_id,
                    'room_tariff_id' => $reservation->room_tariff_id,
                    'reserved_check_in_at' => $reservation->reserved_check_in_at,
                    'reserved_check_out_at' => $reservation->reserved_check_out_at,
                    'advance_payment' => $reservation->advance_payment,
                    'status' => $reservation->status,
                    'notes' => $reservation->notes,
                    'room_number' => $reservation->room?->number ?? '',
                    'guest_name' => $reservation->guest?->name ?? '',
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

    /**
     * @param array<string, mixed>|null $input_body
     */
    public function checkin(int $reservation_id, ?array $input_body = null): void
    {
        if ($input_body === null) {
            /** @var string|false $raw_input */
            $raw_input = file_get_contents('php://input');
            /** @var array<string, mixed>|null $body */
            $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;
            $input_body = is_array($body) ? $body : [];
        }

        $user_id = Session::requireAuth();

        try {
            $stay = $this->reservation_service->convertReservationToStay($reservation_id, $user_id);

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Check-in de reserva completado exitosamente',
                'data' => [
                    'stay' => [
                        'id' => $stay->id,
                        'room_id' => $stay->room_id,
                        'room_tariff_id' => $stay->room_tariff_id,
                        'check_in_at' => $stay->check_in_at,
                        'expected_check_out_at' => $stay->expected_check_out_at,
                        'applied_price' => $stay->applied_price,
                        'status' => $stay->status,
                    ],
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

    public function cancel(int $reservation_id, string $reason): void
    {
        try {
            $user_id = Session::requireAuth();
            $settlement = $this->settlement_service->requestCancellation(
                reservation_id: $reservation_id,
                user_id: $user_id,
                reason: $reason,
            );

            http_response_code($settlement->status === 'pending_refund' ? 202 : 200);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => $settlement->status === 'pending_refund'
                    ? 'Reserva cancelada; devolución pendiente de autorización.'
                    : 'Reserva cancelada sin devolución pendiente.',
                'data' => $this->serializeCancellation($settlement),
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

    public function noShow(int $reservation_id, string $reason): void
    {
        try {
            $settlement = $this->settlement_service->declareNoShow(
                reservation_id: $reservation_id,
                user_id: Session::requireAuth(),
                reason: $reason,
            );

            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'No-show registrado sin devolución.',
                'data' => $this->serializeCancellation($settlement),
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

    public function approveRefund(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;
        $body = is_array($body) ? $body : [];

        $cancellation_id = is_numeric($body['cancellation_id'] ?? null)
            ? (int) $body['cancellation_id']
            : 0;
        $reason = is_string($body['reason'] ?? null) ? $body['reason'] : '';
        $reference_number = is_string($body['reference_number'] ?? null)
            ? $body['reference_number']
            : null;

        try {
            $result = $this->settlement_service->executeReservationRefund(
                cancellation_id: $cancellation_id,
                user_id: Session::requireAuth(),
                reason: $reason,
                reference_number: $reference_number,
            );

            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Devolución ejecutada correctamente.',
                'data' => [
                    'cancellation' => $this->serializeCancellation($result['cancellation']),
                    'refunds' => array_map(
                        static fn($refund): array => [
                            'id' => $refund->id,
                            'cancellation_id' => $refund->cancellation_id,
                            'reservation_payment_id' => $refund->reservation_payment_id,
                            'cashbox_shift_id' => $refund->cashbox_shift_id,
                            'amount' => $refund->amount,
                            'payment_method' => $refund->payment_method,
                            'reference_number' => $refund->reference_number,
                            'executed_by_user_id' => $refund->executed_by_user_id,
                            'reason' => $refund->reason,
                            'created_at' => $refund->created_at,
                        ],
                        $result['refunds']
                    ),
                    'movements' => array_map(
                        static fn($movement): array => [
                            'id' => $movement->id,
                            'cashbox_shift_id' => $movement->cashbox_shift_id,
                            'type' => $movement->type,
                            'category' => $movement->category,
                            'concept' => $movement->concept,
                            'amount' => $movement->amount,
                            'payment_method' => $movement->payment_method,
                            'reference_id' => $movement->reference_id,
                            'reference_type' => $movement->reference_type,
                        ],
                        $result['movements']
                    ),
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
     * @return array<string, int|float|string|null>
     */
    private function serializeCancellation(\App\Models\ReservationCancellation $settlement): array
    {
        return [
            'id' => $settlement->id,
            'reservation_id' => $settlement->reservation_id,
            'event_type' => $settlement->event_type,
            'policy_code' => $settlement->policy_code,
            'refund_percentage' => $settlement->refund_percentage,
            'gross_paid_amount' => $settlement->gross_paid_amount,
            'refund_amount' => $settlement->refund_amount,
            'penalty_amount' => $settlement->penalty_amount,
            'status' => $settlement->status,
            'reason' => $settlement->reason,
            'requested_by_user_id' => $settlement->requested_by_user_id,
            'approved_by_user_id' => $settlement->approved_by_user_id,
            'approved_at' => $settlement->approved_at,
            'created_at' => $settlement->created_at,
            'updated_at' => $settlement->updated_at,
        ];
    }
}
