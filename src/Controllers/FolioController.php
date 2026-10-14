<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\FolioService;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class FolioController
{
    private FolioService $folio_service;

    public function __construct(?FolioService $folio_service = null)
    {
        $this->folio_service = $folio_service ?? new FolioService();
    }

    public function show(int $stay_id): void
    {
        try {
            $folio = $this->folio_service->getOrCreateFolio($stay_id);

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'data' => $folio,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function addCharge(int $stay_id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $charge_type = is_string($body['charge_type'] ?? null) ? $body['charge_type'] : 'service';
        $description = is_string($body['description'] ?? null) ? $body['description'] : '';
        $quantity = is_numeric($body['quantity'] ?? null) ? (int) $body['quantity'] : 1;
        $unit_price = is_numeric($body['unit_price'] ?? null) ? (float) $body['unit_price'] : 0.0;
        $user_id = Session::requireAuth();

        try {
            $charge = $this->folio_service->addCharge(
                stay_id: $stay_id,
                charge_type: $charge_type,
                description: $description,
                quantity: $quantity,
                unit_price: $unit_price,
                created_by_user_id: $user_id
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Cargo registrado exitosamente',
                'data' => $charge,
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

    public function addPayment(int $stay_id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $payment_type = is_string($body['payment_type'] ?? null) ? $body['payment_type'] : 'partial';
        $amount = is_numeric($body['amount'] ?? null) ? (float) $body['amount'] : 0.0;
        $payment_method = is_string($body['payment_method'] ?? null) ? $body['payment_method'] : 'cash';
        $reference_number = is_string($body['reference_number'] ?? null) ? $body['reference_number'] : null;
        $notes = is_string($body['notes'] ?? null) ? $body['notes'] : null;
        $user_id = Session::requireAuth();

        try {
            $payment = $this->folio_service->addPayment(
                stay_id: $stay_id,
                payment_type: $payment_type,
                amount: $amount,
                payment_method: $payment_method,
                created_by_user_id: $user_id,
                reference_number: $reference_number,
                notes: $notes
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Abono registrado exitosamente',
                'data' => $payment,
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

    public function reverseCharge(int $stay_id, int $charge_id): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $reason = is_string($body['reversal_reason'] ?? null) ? $body['reversal_reason'] : '';
        $user_id = Session::requireAuth();

        try {
            $reversal = $this->folio_service->reverseCharge(
                stay_id: $stay_id,
                charge_id: $charge_id,
                reversal_reason: $reason,
                created_by_user_id: $user_id
            );

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Cargo revertido exitosamente',
                'data' => $reversal,
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
