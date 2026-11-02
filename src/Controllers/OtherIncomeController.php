<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ExpenseService;
use App\Support\Session;
use DomainException;
use InvalidArgumentException;

final class OtherIncomeController
{
    private ExpenseService $expense_service;

    public function __construct(?ExpenseService $expense_service = null)
    {
        $this->expense_service = $expense_service ?? new ExpenseService();
    }

    public function index(): void
    {
        $incomes = $this->expense_service->listOtherIncomes();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $incomes,
        ]);
    }

    public function create(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $user_id = Session::requireAuth();
        $category = is_string($body['category'] ?? null) ? $body['category'] : 'other_income';
        $concept = is_string($body['concept'] ?? null) ? $body['concept'] : '';
        $amount = is_numeric($body['amount'] ?? null) ? (float) $body['amount'] : 0.0;
        $payment_method = is_string($body['payment_method'] ?? null) ? $body['payment_method'] : 'cash';
        $notes = is_string($body['notes'] ?? null) ? $body['notes'] : null;

        try {
            $income = $this->expense_service->recordOtherIncome(
                user_id: $user_id,
                category: $category,
                concept: $concept,
                amount: $amount,
                payment_method: $payment_method,
                notes: $notes
            );

            http_response_code(201);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => 'Otro ingreso registrado exitosamente',
                'data' => $income,
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
