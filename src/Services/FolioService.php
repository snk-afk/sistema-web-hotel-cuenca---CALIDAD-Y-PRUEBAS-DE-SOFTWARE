<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\Folio;
use App\Models\FolioCharge;
use App\Models\FolioPayment;
use App\Models\ReservationPayment;
use App\Repositories\FolioChargeRepository;
use App\Repositories\FolioPaymentRepository;
use App\Repositories\FolioRepository;
use App\Repositories\StayRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class FolioService
{
    private PDO $db;
    private FolioRepository $folio_repository;
    private FolioChargeRepository $charge_repository;
    private FolioPaymentRepository $payment_repository;
    private StayRepository $stay_repository;
    private CashboxService $cashbox_service;
    private AuditLogService $audit_log_service;

    public function __construct(
        ?PDO $db = null,
        ?FolioRepository $folio_repository = null,
        ?FolioChargeRepository $charge_repository = null,
        ?FolioPaymentRepository $payment_repository = null,
        ?StayRepository $stay_repository = null,
        ?CashboxService $cashbox_service = null,
        ?AuditLogService $audit_log_service = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->folio_repository = $folio_repository ?? new FolioRepository($this->db);
        $this->charge_repository = $charge_repository ?? new FolioChargeRepository($this->db);
        $this->payment_repository = $payment_repository ?? new FolioPaymentRepository($this->db);
        $this->stay_repository = $stay_repository ?? new StayRepository($this->db);
        $this->cashbox_service = $cashbox_service ?? new CashboxService($this->db);
        $this->audit_log_service = $audit_log_service ?? new AuditLogService($this->db);
    }

    public function getOrCreateFolio(int $stay_id): Folio
    {
        $stay = $this->stay_repository->findById($stay_id);
        if ($stay === null) {
            throw new InvalidArgumentException("Estancia con ID {$stay_id} no encontrada.");
        }

        $folio = $this->folio_repository->findByStayId($stay_id);
        if ($folio !== null) {
            return $folio;
        }

        return $this->folio_repository->create($stay_id);
    }

    public function addCharge(
        int $stay_id,
        string $charge_type,
        string $description,
        int $quantity,
        float $unit_price,
        int $created_by_user_id
    ): FolioCharge {
        $allowed_types = ['accommodation', 'extension', 'store_item', 'service', 'penalty', 'adjustment'];
        if (!in_array($charge_type, $allowed_types, true)) {
            throw new InvalidArgumentException("Tipo de cargo inválido: {$charge_type}");
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException("La cantidad debe ser mayor a cero.");
        }

        if (!is_finite($unit_price) || $unit_price < 0 || abs($unit_price - round($unit_price, 2)) > 0.000001) {
            throw new InvalidArgumentException("El precio unitario no puede ser negativo y debe tener como máximo dos decimales.");
        }

        $folio = $this->getOrCreateFolio($stay_id);
        if ($folio->status === 'closed') {
            throw new DomainException("No se pueden agregar cargos a un Folio cerrado.");
        }

        $total_amount = round($quantity * $unit_price, 2);

        return $this->charge_repository->create(
            folio_id: $folio->id,
            charge_type: $charge_type,
            description: $description,
            quantity: $quantity,
            unit_price: $unit_price,
            total_amount: $total_amount,
            created_by_user_id: $created_by_user_id
        );
    }

    public function applyReservationPayment(
        int $stay_id,
        ReservationPayment $reservation_payment,
        int $applied_by_user_id
    ): FolioPayment {
        $folio = $this->getOrCreateFolio($stay_id);
        if ($folio->status === 'closed') {
            throw new DomainException('No se pueden aplicar adelantos a un Folio cerrado.');
        }

        $existing_payment = $this->payment_repository->findByReservationPaymentId($reservation_payment->id);
        if ($existing_payment !== null) {
            throw new DomainException('El adelanto ya fue aplicado a un Folio.');
        }

        if (round($reservation_payment->amount, 2) > round($folio->pending_balance, 2)) {
            throw new DomainException('El adelanto supera el saldo pendiente del Folio.');
        }

        $payment = $this->payment_repository->create(
            folio_id: $folio->id,
            payment_type: 'advance',
            amount: $reservation_payment->amount,
            payment_method: $reservation_payment->payment_method,
            created_by_user_id: $applied_by_user_id,
            reference_number: $reservation_payment->reference_number,
            notes: $reservation_payment->notes ?? 'Adelanto de reserva aplicado al check-in',
            cashbox_shift_id: $reservation_payment->cashbox_shift_id,
            reservation_payment_id: $reservation_payment->id
        );

        $this->audit_log_service->logAction(
            user_id: $applied_by_user_id,
            action: 'APPLY_RESERVATION_PAYMENT',
            entity_name: 'folio_payments',
            entity_id: $payment->id,
            new_values: [
                'folio_id' => $folio->id,
                'reservation_payment_id' => $reservation_payment->id,
                'cashbox_shift_id' => $reservation_payment->cashbox_shift_id,
                'amount' => $payment->amount,
                'payment_method' => $payment->payment_method,
            ]
        );

        return $payment;
    }

    public function closeFolio(int $stay_id): void
    {
        $folio = $this->getOrCreateFolio($stay_id);
        if ($folio->status === 'closed') {
            throw new DomainException('El Folio ya está cerrado.');
        }

        if (round($folio->pending_balance, 2) !== 0.0) {
            throw new DomainException('No se puede cerrar un Folio con saldo pendiente.');
        }

        $this->folio_repository->closeFolio($folio->id);
    }

    public function addPayment(
        int $stay_id,
        string $payment_type,
        float $amount,
        string $payment_method,
        int $created_by_user_id,
        ?string $reference_number = null,
        ?string $notes = null
    ): FolioPayment {
        $payments = $this->addPaymentParts(
            stay_id: $stay_id,
            payment_type: $payment_type,
            payment_parts: [[
                'amount' => $amount,
                'payment_method' => $payment_method,
                'reference_number' => $reference_number,
            ]],
            created_by_user_id: $created_by_user_id,
            notes: $notes
        );

        return $payments[0];
    }

    /**
     * @param list<mixed> $payment_parts
     * @return list<FolioPayment>
     */
    public function addPaymentParts(
        int $stay_id,
        string $payment_type,
        array $payment_parts,
        int $created_by_user_id,
        ?string $notes = null,
        bool $require_exact_balance = false
    ): array {
        $allowed_types = ['advance', 'partial', 'final', 'settlement'];
        if (!in_array($payment_type, $allowed_types, true)) {
            throw new InvalidArgumentException("Tipo de pago inválido: {$payment_type}");
        }

        if ($payment_parts === []) {
            throw new InvalidArgumentException('Debe indicar al menos una parte de pago.');
        }

        $normalized_parts = [];
        $total_amount = 0.0;
        foreach ($payment_parts as $payment_part) {
            if (!is_array($payment_part)) {
                throw new InvalidArgumentException('Cada parte de pago debe ser un objeto válido.');
            }

            $amount = $payment_part['amount'] ?? null;
            if ((!is_int($amount) && !is_float($amount) && !is_string($amount)) || !is_numeric($amount)) {
                throw new InvalidArgumentException('El monto de cada parte de pago debe ser numérico.');
            }

            $resolved_amount = (float) $amount;
            if ($resolved_amount <= 0 || abs($resolved_amount - round($resolved_amount, 2)) > 0.000001) {
                throw new InvalidArgumentException('Cada parte de pago debe ser mayor a cero y tener como máximo dos decimales.');
            }

            $payment_method = $payment_part['payment_method'] ?? null;
            if (!is_string($payment_method) || !in_array($payment_method, ['cash', 'yape', 'plin', 'card', 'transfer'], true)) {
                throw new InvalidArgumentException('Método de pago inválido.');
            }

            $reference_number = $payment_part['reference_number'] ?? null;
            if ($reference_number !== null && !is_string($reference_number)) {
                throw new InvalidArgumentException('La referencia de pago debe ser texto.');
            }

            $part_notes = $payment_part['notes'] ?? null;
            if ($part_notes !== null && !is_string($part_notes)) {
                throw new InvalidArgumentException('La nota de pago debe ser texto.');
            }

            $normalized_parts[] = [
                'amount' => round($resolved_amount, 2),
                'payment_method' => $payment_method,
                'reference_number' => $reference_number,
                'notes' => $part_notes,
            ];
            $total_amount += $resolved_amount;
        }

        $total_amount = round($total_amount, 2);
        if ($total_amount <= 0) {
            throw new InvalidArgumentException('La suma de las partes de pago debe ser mayor a cero.');
        }

        $started_transaction = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $started_transaction = true;
        }

        try {
            if (!$this->stay_repository->lockById($stay_id)) {
                throw new InvalidArgumentException("Estancia con ID {$stay_id} no encontrada.");
            }

            $folio = $this->getOrCreateFolio($stay_id);
            if ($folio->status === 'closed') {
                throw new DomainException("No se pueden registrar abonos en un Folio cerrado.");
            }

            $pending_balance = round($folio->pending_balance, 2);
            if ($total_amount > $pending_balance) {
                throw new DomainException("El pago supera el saldo pendiente del Folio.");
            }

            if ($require_exact_balance && abs($total_amount - $pending_balance) > 0.000001) {
                throw new DomainException("El pago debe coincidir exactamente con el saldo pendiente del Folio.");
            }

            $shift = $this->cashbox_service->getCurrentShift($created_by_user_id);
            if ($shift === null) {
                throw new DomainException('Debe abrir un turno de caja antes de registrar un pago.');
            }

            $payments = [];
            foreach ($normalized_parts as $payment_part) {
                $payment = $this->payment_repository->create(
                    folio_id: $folio->id,
                    payment_type: $payment_type,
                    amount: $payment_part['amount'],
                    payment_method: $payment_part['payment_method'],
                    created_by_user_id: $created_by_user_id,
                    reference_number: $payment_part['reference_number'],
                    notes: $payment_part['notes'] ?? $notes,
                    cashbox_shift_id: $shift->id
                );

                $this->cashbox_service->recordMovement(
                    user_id: $created_by_user_id,
                    type: 'income',
                    category: 'accommodation',
                    concept: "Pago a Folio #{$folio->id} (Estancia #{$stay_id})",
                    amount: $payment->amount,
                    payment_method: $payment->payment_method,
                    reference_id: $payment->id,
                    reference_type: 'folio_payment'
                );
                $this->audit_log_service->logAction(
                    user_id: $created_by_user_id,
                    action: 'CREATE_FOLIO_PAYMENT',
                    entity_name: 'folio_payments',
                    entity_id: $payment->id,
                    new_values: [
                        'folio_id' => $folio->id,
                        'cashbox_shift_id' => $shift->id,
                        'amount' => $payment->amount,
                        'payment_method' => $payment->payment_method,
                        'reference_type' => 'folio_payment',
                    ]
                );
                $payments[] = $payment;
            }

            if ($started_transaction) {
                $this->db->commit();
            }
            return $payments;
        } catch (\Throwable $e) {
            if ($started_transaction) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function reverseCharge(
        int $stay_id,
        int $charge_id,
        string $reversal_reason,
        int $created_by_user_id
    ): FolioCharge {
        if (trim($reversal_reason) === '') {
            throw new InvalidArgumentException("Debe ingresar el motivo del reverso o anulación.");
        }

        $folio = $this->getOrCreateFolio($stay_id);
        if ($folio->status === 'closed') {
            throw new DomainException("No se pueden revertir cargos en un Folio cerrado.");
        }

        $charge = $this->charge_repository->findById($charge_id);
        if ($charge === null || $charge->folio_id !== $folio->id) {
            throw new InvalidArgumentException("Cargo con ID {$charge_id} no encontrado en este Folio.");
        }

        if ($charge->is_reversal) {
            throw new DomainException("Este cargo ya es un reverso.");
        }

        $reversal_description = "REVERSO: " . $charge->description;
        $reversal_total = -1.0 * abs($charge->total_amount);

        return $this->charge_repository->create(
            folio_id: $folio->id,
            charge_type: 'adjustment',
            description: $reversal_description,
            quantity: $charge->quantity,
            unit_price: -1.0 * abs($charge->unit_price),
            total_amount: $reversal_total,
            created_by_user_id: $created_by_user_id,
            is_reversal: true,
            reversal_reason: $reversal_reason
        );
    }
}
