<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\CashboxShift;
use App\Services\CashboxService;
use App\Support\CsvExporter;
use App\Support\PdfGenerator;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class CashboxController
{
    private CashboxService $cashbox_service;

    public function __construct(?CashboxService $cashbox_service = null)
    {
        $this->cashbox_service = $cashbox_service ?? new CashboxService();
    }

    public function current(): void
    {
        $user_id = Session::requireAuth();
        $shift = $this->cashbox_service->getCurrentShift($user_id);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $shift,
        ]);
    }

    public function open(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $user_id = Session::requireAuth();
        $initial_cash = is_numeric($body['initial_cash'] ?? null) ? (float) $body['initial_cash'] : 0.0;
        $notes = is_string($body['notes'] ?? null) ? $body['notes'] : null;

        try {
            $shift = $this->cashbox_service->openShift($user_id, $initial_cash, $notes);

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Turno de caja abierto exitosamente',
                'data' => $shift,
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

    public function close(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $user_id = Session::requireAuth();
        $real_cash = is_numeric($body['real_cash_declared'] ?? null) ? (float) $body['real_cash_declared'] : 0.0;
        $notes = is_string($body['notes'] ?? null) ? $body['notes'] : null;

        try {
            $real_yape = $this->parseNullableAmount($body, 'real_yape_declared');
            $real_plin = $this->parseNullableAmount($body, 'real_plin_declared');
            $real_card = $this->parseNullableAmount($body, 'real_card_declared');
            $real_transfer = $this->parseNullableAmount($body, 'real_transfer_declared');
            $shift = $this->cashbox_service->closeShift(
                user_id: $user_id,
                real_cash_declared: $real_cash,
                notes: $notes,
                real_yape_declared: $real_yape,
                real_plin_declared: $real_plin,
                real_card_declared: $real_card,
                real_transfer_declared: $real_transfer
            );

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Turno de caja cerrado exitosamente',
                'data' => $shift,
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
    private function parseNullableAmount(array $body, string $key): ?float
    {
        if (!array_key_exists($key, $body) || $body[$key] === null || $body[$key] === '') {
            return null;
        }

        if (!is_numeric($body[$key])) {
            throw new InvalidArgumentException("El monto declarado de {$key} debe ser numérico.");
        }

        $amount = (float) $body[$key];
        if ($amount < 0 || abs($amount - round($amount, 2)) > 0.000001) {
            throw new InvalidArgumentException("El monto declarado de {$key} es inválido.");
        }

        return $amount;
    }

    public function movement(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $user_id = Session::requireAuth();
        $type = is_string($body['type'] ?? null) ? $body['type'] : 'expense';
        $category = is_string($body['category'] ?? null) ? $body['category'] : 'petty_expense';
        $concept = is_string($body['concept'] ?? null) ? $body['concept'] : '';
        $amount = is_numeric($body['amount'] ?? null) ? (float) $body['amount'] : 0.0;
        $payment_method = is_string($body['payment_method'] ?? null) ? $body['payment_method'] : 'cash';

        try {
            $movement = $this->cashbox_service->recordMovement(
                user_id: $user_id,
                type: $type,
                category: $category,
                concept: $concept,
                amount: $amount,
                payment_method: $payment_method
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Movimiento de caja registrado exitosamente',
                'data' => $movement,
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

    public function history(): void
    {
        Session::requireAuth();

        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : null;
        $user_id = is_numeric($_GET['user_id'] ?? null) ? (int) $_GET['user_id'] : null;
        $from = is_string($_GET['from'] ?? null) ? $_GET['from'] : null;
        $to = is_string($_GET['to'] ?? null) ? $_GET['to'] : null;

        $shifts = $this->cashbox_service->listShifts($status, $user_id, $from, $to);

        // Lightweight list: exclude the movements array from each shift
        // to keep the response small (movements are fetched via the detail endpoint).
        $data = array_map(static fn(CashboxShift $s): array => [
            'id' => $s->id,
            'user_id' => $s->user_id,
            'opened_at' => $s->opened_at,
            'closed_at' => $s->closed_at,
            'initial_cash' => $s->initial_cash,
            'expected_cash' => $s->expected_cash,
            'real_cash_declared' => $s->real_cash_declared,
            'difference' => $s->difference,
            'status' => $s->status,
            'balance_status' => $s->balance_status,
            'notes' => $s->notes,
        ], $shifts);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function show(): void
    {
        Session::requireAuth();

        $raw_id = is_numeric($_GET['id'] ?? null) ? (int) $_GET['id'] : null;

        if ($raw_id === null || $raw_id <= 0) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'El parámetro id es obligatorio.']);
            return;
        }

        $shift = $this->cashbox_service->getShiftById($raw_id);

        if ($shift === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Turno de caja no encontrado.']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $shift,
        ]);
    }

    public function exportCsv(): void
    {
        Session::requireAuth();

        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : null;
        $user_id = is_numeric($_GET['user_id'] ?? null) ? (int) $_GET['user_id'] : null;
        $from = is_string($_GET['from'] ?? null) ? $_GET['from'] : null;
        $to = is_string($_GET['to'] ?? null) ? $_GET['to'] : null;

        $shifts = $this->cashbox_service->listShifts($status, $user_id, $from, $to);

        $headers = ['ID', 'Responsable (user_id)', 'Apertura', 'Cierre', 'Fondo Inicial', 'Efectivo Esperado', 'Efectivo Declarado', 'Diferencia', 'Estado', 'Arqueo', 'Notas'];
        $rows = array_map(static fn(CashboxShift $s): array => [
            $s->id,
            $s->user_id,
            $s->opened_at,
            $s->closed_at ?? '',
            $s->initial_cash,
            $s->expected_cash,
            $s->real_cash_declared ?? '',
            $s->difference ?? '',
            $s->status,
            $s->balance_status,
            $s->notes ?? '',
        ], $shifts);

        $csv = CsvExporter::generateCsv($headers, $rows);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="historial_caja.csv"');
        echo $csv;
    }

    public function exportPdf(): void
    {
        Session::requireAuth();

        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : null;
        $user_id = is_numeric($_GET['user_id'] ?? null) ? (int) $_GET['user_id'] : null;
        $from = is_string($_GET['from'] ?? null) ? $_GET['from'] : null;
        $to = is_string($_GET['to'] ?? null) ? $_GET['to'] : null;

        $shifts = $this->cashbox_service->listShifts($status, $user_id, $from, $to);

        $period = '';
        if ($from !== null && $to !== null) {
            $period = "Del {$from} al {$to}";
        } elseif ($from !== null) {
            $period = "Desde {$from}";
        } elseif ($to !== null) {
            $period = "Hasta {$to}";
        } else {
            $period = 'Histórico Completo';
        }

        $pdf_content = PdfGenerator::generateCashboxHistoryPdf([
            'period_label' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'shifts' => $shifts,
        ]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="historial_caja.pdf"');
        echo $pdf_content;
    }
}
