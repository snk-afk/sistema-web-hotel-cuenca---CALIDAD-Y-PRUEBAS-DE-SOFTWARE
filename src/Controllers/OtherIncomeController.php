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
        $this->respond([
            'status' => 'success',
            'data' => $this->expense_service->listOtherIncomes(),
        ]);
    }

    public function create(): void
    {
        $body = $this->readRequestBody();
        $user_id = Session::requireAuth();

        try {
            $income = $this->expense_service->recordOtherIncome(
                user_id: $user_id,
                category: $this->stringField($body, 'category') ?? 'other_income',
                concept: $this->stringField($body, 'concept') ?? '',
                amount: is_numeric($body['amount'] ?? null) ? (float) $body['amount'] : 0.0,
                payment_method: $this->stringField($body, 'payment_method') ?? 'cash',
                notes: $this->stringField($body, 'notes'),
            );

            $this->respond([
                'status' => 'success',
                'message' => 'Otro ingreso registrado exitosamente',
                'data' => $income,
            ], 201);
        } catch (InvalidArgumentException | DomainException $e) {
            $this->respond([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], $e instanceof InvalidArgumentException ? 422 : 409);
        }
    }

    /** @return array<array-key, mixed> */
    private function readRequestBody(): array
    {
        $raw = file_get_contents('php://input');

        if ($raw === false || $raw === '') {
            return $_POST;
        }

        $body = json_decode($raw, true);

        return is_array($body) ? $body : [];
    }

    /** @param array<array-key, mixed> $body */
    private function stringField(array $body, string $key): ?string
    {
        $value = $body[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param array<string, mixed> $payload */
    private function respond(array $payload, ?int $statusCode = null): void
    {
        if ($statusCode !== null) {
            http_response_code($statusCode);
        }

        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}