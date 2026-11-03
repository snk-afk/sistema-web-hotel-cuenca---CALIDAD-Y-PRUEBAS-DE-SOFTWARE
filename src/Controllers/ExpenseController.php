<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ExpenseService;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class ExpenseController
{
    private ExpenseService $expense_service;

    public function __construct(?ExpenseService $expense_service = null)
    {
        $this->expense_service = $expense_service ?? new ExpenseService();
    }

    public function index(): void
    {
        $this->sendJson([
            'status' => 'success',
            'data' => $this->expense_service->listExpenses(),
        ]);
    }

    public function create(): void
    {
        $raw = file_get_contents('php://input');
        $input = ($raw === false || $raw === '') ? $_POST : json_decode($raw, true);
        $body = is_array($input) ? $input : [];

        $user_id = Session::requireAuth();
        $text = array_filter($body, 'is_string');
        $amount = $body['amount'] ?? null;

        try {
            $expense = $this->expense_service->recordExpense(
                user_id: $user_id,
                category: $text['category'] ?? 'petty_cash',
                concept: $text['concept'] ?? '',
                amount: is_numeric($amount) ? (float) $amount : 0.0,
                payment_method: $text['payment_method'] ?? 'cash',
                supplier_name: $text['supplier_name'] ?? null,
                receipt_number: $text['receipt_number'] ?? null,
            );

            $this->sendJson([
                'status' => 'success',
                'message' => 'Egreso registrado exitosamente',
                'data' => $expense,
            ], 201);
        } catch (InvalidArgumentException | DomainException $e) {
            $this->sendJson([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e instanceof InvalidArgumentException ? 422 : 409);
        }
    }

    /** @param array<string, mixed> $payload */
    private function sendJson(array $payload, ?int $statusCode = null): void
    {
        if ($statusCode !== null) {
            http_response_code($statusCode);
        }

        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}