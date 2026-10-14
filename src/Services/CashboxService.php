<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\CashMovement;
use App\Models\CashboxShift;
use App\Repositories\CashMovementRepository;
use App\Repositories\CashboxShiftRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class CashboxService
{
    /** @var list<string> */
    private const PAYMENT_METHODS = ['cash', 'yape', 'plin', 'card', 'transfer'];
    /** @var list<string> */
    private const REFERENCE_TYPES = ['reservation_payment', 'reservation_refund', 'folio_payment', 'store_sale', 'expense', 'other_income'];

    private PDO $db;
    private CashboxShiftRepository $shift_repository;
    private CashMovementRepository $movement_repository;

    public function __construct(
        ?PDO $db = null,
        ?CashboxShiftRepository $shift_repository = null,
        ?CashMovementRepository $movement_repository = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->shift_repository = $shift_repository ?? new CashboxShiftRepository($this->db);
        $this->movement_repository = $movement_repository ?? new CashMovementRepository($this->db);
    }

    public function getCurrentShift(int $user_id): ?CashboxShift
    {
        return $this->shift_repository->findActiveByUserId($user_id);
    }

    public function getCurrentShiftForUpdate(int $user_id): ?CashboxShift
    {
        return $this->shift_repository->findActiveByUserIdForUpdate($user_id);
    }

    /**
     * @return array<int, CashboxShift>
     */
    public function listShifts(
        ?string $status = null,
        ?int $user_id = null,
        ?string $from = null,
        ?string $to = null,
        int $limit = 100
    ): array {
        return $this->shift_repository->findAll($status, $user_id, $from, $to, $limit);
    }

    public function getShiftById(int $id): ?CashboxShift
    {
        return $this->shift_repository->findById($id);
    }

    public function openShift(int $user_id, float $initial_cash, ?string $notes = null): CashboxShift
    {
        if ($initial_cash < 0 || !$this->hasAtMostTwoDecimals($initial_cash)) {
            throw new InvalidArgumentException('El monto inicial de caja no puede ser negativo y debe tener como máximo dos decimales.');
        }

        $existing = $this->shift_repository->findActiveByUserId($user_id);
        if ($existing !== null) {
            throw new DomainException("El usuario ya tiene un turno de caja abierto (ID {$existing->id}).");
        }

        return $this->shift_repository->create($user_id, $initial_cash, $notes);
    }

    public function recordMovement(
        int $user_id,
        string $type,
        string $category,
        string $concept,
        float $amount,
        string $payment_method = 'cash',
        ?int $reference_id = null,
        ?string $reference_type = null
    ): CashMovement {
        if (!in_array($type, ['income', 'expense'], true)) {
            throw new InvalidArgumentException("Tipo de movimiento inválido: {$type}");
        }

        if (!in_array($payment_method, self::PAYMENT_METHODS, true)) {
            throw new InvalidArgumentException("Método de pago inválido: {$payment_method}");
        }

        if ($reference_type !== null && !in_array($reference_type, self::REFERENCE_TYPES, true)) {
            throw new InvalidArgumentException("Tipo de referencia inválido: {$reference_type}");
        }

        if ($amount <= 0 || !$this->hasAtMostTwoDecimals($amount)) {
            throw new InvalidArgumentException('El monto del movimiento debe ser mayor a cero y tener como máximo dos decimales.');
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            $shift = $this->shift_repository->findActiveByUserIdForUpdate($user_id);
            if ($shift === null) {
                throw new DomainException('Debe abrir un turno de caja antes de realizar operaciones de efectivo.');
            }

            $movement = $this->movement_repository->create(
                cashbox_shift_id: $shift->id,
                type: $type,
                category: $category,
                concept: $concept,
                amount: $amount,
                payment_method: $payment_method,
                created_by_user_id: $user_id,
                reference_id: $reference_id,
                reference_type: $reference_type
            );

            $delta = $type === 'income' ? $amount : -$amount;
            $expected_amount = $this->expectedAmountForMethod($shift, $payment_method) + $delta;
            $updated_rows = $this->shift_repository->updateExpectedByPaymentMethod(
                shift_id: $shift->id,
                payment_method: $payment_method,
                expected_amount: round($expected_amount, 2)
            );

            if ($updated_rows !== 1) {
                throw new DomainException('El turno de caja ya no está abierto para registrar el movimiento.');
            }

            if ($owns_transaction) {
                $this->db->commit();
            }

            return $movement;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function closeShift(
        int $user_id,
        float $real_cash_declared,
        ?string $notes = null,
        ?float $real_yape_declared = null,
        ?float $real_plin_declared = null,
        ?float $real_card_declared = null,
        ?float $real_transfer_declared = null
    ): CashboxShift {
        $declared = [
            'cash' => $real_cash_declared,
            'yape' => $real_yape_declared,
            'plin' => $real_plin_declared,
            'card' => $real_card_declared,
            'transfer' => $real_transfer_declared,
        ];

        foreach ($declared as $payment_method => $amount) {
            if ($amount !== null && ($amount < 0 || !$this->hasAtMostTwoDecimals($amount))) {
                throw new InvalidArgumentException("El monto declarado de {$payment_method} no puede ser negativo y debe tener como máximo dos decimales.");
            }
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            $shift = $this->shift_repository->findActiveByUserIdForUpdate($user_id);
            if ($shift === null) {
                throw new DomainException('No hay ningún turno de caja abierto para cerrar.');
            }

            $expected = $this->calculateExpectedAmounts($shift);
            foreach (['yape', 'plin', 'card', 'transfer'] as $payment_method) {
                if ($expected[$payment_method] > 0 && $declared[$payment_method] === null) {
                    throw new InvalidArgumentException(
                        "Debe declarar el monto de {$payment_method} antes de cerrar la caja."
                    );
                }
            }

            $resolved_declared = [];
            $differences = [];
            foreach ($expected as $payment_method => $expected_amount) {
                $resolved_declared[$payment_method] = $declared[$payment_method] ?? 0.0;
                $differences[$payment_method] = round(
                    $resolved_declared[$payment_method] - $expected_amount,
                    2
                );
            }

            $balance_status = $this->resolveBalanceStatus($differences);
            $updated_rows = $this->shift_repository->closeShift(
                shift_id: $shift->id,
                expected_cash: $expected['cash'],
                real_cash_declared: $resolved_declared['cash'],
                difference: $differences['cash'],
                balance_status: $balance_status,
                notes: $notes,
                expected_yape: $expected['yape'],
                expected_plin: $expected['plin'],
                expected_card: $expected['card'],
                expected_transfer: $expected['transfer'],
                real_yape_declared: $declared['yape'],
                real_plin_declared: $declared['plin'],
                real_card_declared: $declared['card'],
                real_transfer_declared: $declared['transfer'],
                difference_yape: $differences['yape'],
                difference_plin: $differences['plin'],
                difference_card: $differences['card'],
                difference_transfer: $differences['transfer'],
            );

            if ($updated_rows !== 1) {
                throw new DomainException('El turno cambió mientras se intentaba cerrar la caja.');
            }

            if ($owns_transaction) {
                $this->db->commit();
            }

            $closed_shift = $this->shift_repository->findById($shift->id);
            if ($closed_shift === null) {
                throw new DomainException('No se pudo recuperar el turno cerrado.');
            }

            return $closed_shift;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{cash: float, yape: float, plin: float, card: float, transfer: float}
     */
    private function calculateExpectedAmounts(CashboxShift $shift): array
    {
        $movements = $this->movement_repository->findByShiftId($shift->id);
        $expected = [
            'cash' => $shift->initial_cash,
            'yape' => 0.0,
            'plin' => 0.0,
            'card' => 0.0,
            'transfer' => 0.0,
        ];

        foreach ($movements as $movement) {
            $delta = $movement->type === 'income' ? $movement->amount : -$movement->amount;
            switch ($movement->payment_method) {
                case 'cash':
                    $expected['cash'] = round($expected['cash'] + $delta, 2);
                    break;
                case 'yape':
                    $expected['yape'] = round($expected['yape'] + $delta, 2);
                    break;
                case 'plin':
                    $expected['plin'] = round($expected['plin'] + $delta, 2);
                    break;
                case 'card':
                    $expected['card'] = round($expected['card'] + $delta, 2);
                    break;
                case 'transfer':
                    $expected['transfer'] = round($expected['transfer'] + $delta, 2);
                    break;
                default:
                    throw new InvalidArgumentException('Método de pago inválido en el movimiento de caja.');
            }
        }

        foreach ($expected as $payment_method => $amount) {
            $expected[$payment_method] = round($amount, 2);
        }

        return $expected;
    }

    private function expectedAmountForMethod(CashboxShift $shift, string $payment_method): float
    {
        return match ($payment_method) {
            'cash' => $shift->expected_cash,
            'yape' => $shift->expected_yape,
            'plin' => $shift->expected_plin,
            'card' => $shift->expected_card,
            'transfer' => $shift->expected_transfer,
            default => throw new InvalidArgumentException('Método de pago inválido.'),
        };
    }

    /**
     * @param array<string, float> $differences
     */
    private function resolveBalanceStatus(array $differences): string
    {
        foreach ($differences as $difference) {
            if ($difference < -0.01) {
                return 'shortage';
            }
        }

        foreach ($differences as $difference) {
            if ($difference > 0.01) {
                return 'surplus';
            }
        }

        return 'balanced';
    }

    private function hasAtMostTwoDecimals(float $amount): bool
    {
        return is_finite($amount) && abs($amount - round($amount, 2)) <= 0.000001;
    }
}
