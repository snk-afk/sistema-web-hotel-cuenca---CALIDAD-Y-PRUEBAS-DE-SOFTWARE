<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\CashMovement;
use App\Models\ReservationCancellation;
use App\Models\ReservationRefund;
use App\Repositories\ReservationCancellationRepository;
use App\Repositories\ReservationPaymentRepository;
use App\Repositories\ReservationRefundRepository;
use App\Repositories\ReservationRepository;
use App\Support\Config;
use DateTimeImmutable;
use DateTimeZone;
use DomainException;
use InvalidArgumentException;
use PDO;

final class ReservationSettlementService
{
    private PDO $db;
    private ReservationRepository $reservation_repository;
    private ReservationPaymentRepository $reservation_payment_repository;
    private ReservationCancellationRepository $cancellation_repository;
    private ReservationRefundRepository $refund_repository;
    private CashboxService $cashbox_service;
    private AuditLogService $audit_log_service;
    private DateTimeImmutable $clock;

    public function __construct(
        ?PDO $db = null,
        ?ReservationRepository $reservation_repository = null,
        ?ReservationPaymentRepository $reservation_payment_repository = null,
        ?ReservationCancellationRepository $cancellation_repository = null,
        ?ReservationRefundRepository $refund_repository = null,
        ?CashboxService $cashbox_service = null,
        ?AuditLogService $audit_log_service = null,
        ?DateTimeImmutable $clock = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->reservation_repository = $reservation_repository ?? new ReservationRepository($this->db);
        $this->reservation_payment_repository = $reservation_payment_repository ?? new ReservationPaymentRepository($this->db);
        $this->cancellation_repository = $cancellation_repository ?? new ReservationCancellationRepository($this->db);
        $this->refund_repository = $refund_repository ?? new ReservationRefundRepository($this->db);
        $this->cashbox_service = $cashbox_service ?? new CashboxService($this->db);
        $this->audit_log_service = $audit_log_service ?? new AuditLogService($this->db);
        $this->clock = $clock ?? new DateTimeImmutable(
            'now',
            new DateTimeZone(Config::string('app.timezone', 'America/Lima'))
        );
    }

    public function requestCancellation(
        int $reservation_id,
        int $user_id,
        string $reason
    ): ReservationCancellation {
        return $this->settleReservation(
            reservation_id: $reservation_id,
            user_id: $user_id,
            reason: $reason,
            event_type: 'cancelled',
        );
    }

    public function declareNoShow(
        int $reservation_id,
        int $user_id,
        string $reason
    ): ReservationCancellation {
        return $this->settleReservation(
            reservation_id: $reservation_id,
            user_id: $user_id,
            reason: $reason,
            event_type: 'no_show',
        );
    }

    /**
     * @return array{cancellation: ReservationCancellation, refunds: list<ReservationRefund>, movements: list<CashMovement>}
     */
    public function executeReservationRefund(
        int $cancellation_id,
        int $user_id,
        string $reason,
        ?string $reference_number = null
    ): array {
        $normalized_reason = trim($reason);
        if ($normalized_reason === '' || mb_strlen($normalized_reason) > 255) {
            throw new InvalidArgumentException('El motivo de aprobación es obligatorio y debe tener como máximo 255 caracteres.');
        }

        if ($reference_number !== null) {
            $reference_number = trim($reference_number);
            if ($reference_number === '') {
                $reference_number = null;
            } elseif (mb_strlen($reference_number) > 100) {
                throw new InvalidArgumentException('La referencia de devolución debe tener como máximo 100 caracteres.');
            }
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            $cancellation = $this->cancellation_repository->lockById($cancellation_id);
            if ($cancellation === null) {
                throw new InvalidArgumentException('Liquidación de reserva no encontrada.');
            }

            if ($cancellation->status !== 'pending_refund') {
                throw new DomainException('La liquidación no tiene una devolución pendiente.');
            }

            $payments = $this->reservation_payment_repository->findByReservationIdForUpdate(
                $cancellation->reservation_id
            );
            $shift = $this->cashbox_service->getCurrentShiftForUpdate($user_id);
            if ($shift === null) {
                throw new DomainException('Debe abrir un turno de caja antes de ejecutar la devolución.');
            }

            /** @var array<int, float> $payment_refund_amounts */
            $payment_refund_amounts = [];
            $calculated_refund_amount = 0.0;
            foreach ($payments as $payment) {
                if ($this->reservation_payment_repository->hasAppliedFolioPayment($payment->id)) {
                    throw new DomainException('El adelanto ya fue aplicado a un folio y no puede devolverse.');
                }

                if ($this->refund_repository->findByReservationPaymentId($payment->id) !== null) {
                    throw new DomainException('El adelanto ya tiene una devolución registrada.');
                }

                $amount = round(
                    $payment->amount * ($cancellation->refund_percentage / 100),
                    2,
                    PHP_ROUND_HALF_UP
                );
                if ($amount <= 0) {
                    continue;
                }

                $payment_refund_amounts[$payment->id] = $amount;
                $calculated_refund_amount = round($calculated_refund_amount + $amount, 2);
            }

            if (abs($calculated_refund_amount - $cancellation->refund_amount) > 0.001) {
                throw new DomainException('El monto de los adelantos cambió después de crear la liquidación.');
            }

            /** @var list<ReservationRefund> $refunds */
            $refunds = [];
            /** @var list<CashMovement> $movements */
            $movements = [];
            foreach ($payments as $payment) {
                $amount = $payment_refund_amounts[$payment->id] ?? 0.0;
                if ($amount <= 0) {
                    continue;
                }

                $refund = $this->refund_repository->create(
                    cancellation_id: $cancellation->id,
                    reservation_payment_id: $payment->id,
                    cashbox_shift_id: $shift->id,
                    amount: $amount,
                    payment_method: $payment->payment_method,
                    reference_number: $reference_number,
                    executed_by_user_id: $user_id,
                    reason: $normalized_reason,
                );
                $refunds[] = $refund;

                $movements[] = $this->cashbox_service->recordMovement(
                    user_id: $user_id,
                    type: 'expense',
                    category: 'reservation_refund',
                    concept: "Devolución de reserva #{$cancellation->reservation_id}",
                    amount: $amount,
                    payment_method: $payment->payment_method,
                    reference_id: $refund->id,
                    reference_type: 'reservation_refund',
                );
            }

            if ($refunds === []) {
                throw new DomainException('La liquidación no tiene adelantos devolvibles.');
            }

            if ($this->cancellation_repository->markCompleted(
                id: $cancellation->id,
                approved_by_user_id: $user_id,
                approved_at: $this->clock->format('Y-m-d H:i:s'),
            ) !== 1) {
                throw new DomainException('La liquidación cambió mientras se ejecutaba la devolución.');
            }

            $completed = $this->cancellation_repository->findById($cancellation->id);
            if ($completed === null) {
                throw new DomainException('No se pudo recuperar la liquidación completada.');
            }

            $this->audit_log_service->logAction(
                user_id: $user_id,
                action: 'EXECUTE_RESERVATION_REFUND',
                entity_name: 'reservation_cancellations',
                entity_id: $cancellation->id,
                old_values: ['status' => $cancellation->status],
                new_values: [
                    'status' => $completed->status,
                    'refund_ids' => array_map(
                        static fn(ReservationRefund $refund): int => $refund->id,
                        $refunds
                    ),
                    'amount' => $completed->refund_amount,
                ],
            );

            if ($owns_transaction) {
                $this->db->commit();
            }

            return [
                'cancellation' => $completed,
                'refunds' => $refunds,
                'movements' => $movements,
            ];
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    private function settleReservation(
        int $reservation_id,
        int $user_id,
        string $reason,
        string $event_type
    ): ReservationCancellation {
        $normalized_reason = trim($reason);
        if ($normalized_reason === '' || mb_strlen($normalized_reason) > 255) {
            throw new InvalidArgumentException('El motivo es obligatorio y debe tener como máximo 255 caracteres.');
        }

        if (!in_array($event_type, ['cancelled', 'no_show'], true)) {
            throw new InvalidArgumentException('Tipo de liquidación inválido.');
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            if (!$this->reservation_repository->lockById($reservation_id)) {
                throw new InvalidArgumentException('Reserva no encontrada.');
            }

            $reservation = $this->reservation_repository->findById($reservation_id);
            if ($reservation === null) {
                throw new InvalidArgumentException('Reserva no encontrada.');
            }

            if (!in_array($reservation->status, ['pending', 'confirmed'], true)) {
                throw new DomainException('La reserva no puede ser liquidada en su estado actual.');
            }

            if ($this->cancellation_repository->lockByReservationId($reservation_id) !== null) {
                throw new DomainException('La reserva ya tiene una liquidación registrada.');
            }

            $payments = $this->reservation_payment_repository->findByReservationIdForUpdate($reservation_id);
            foreach ($payments as $payment) {
                if ($this->reservation_payment_repository->hasAppliedFolioPayment($payment->id)) {
                    throw new DomainException('El adelanto ya fue aplicado a un folio y no puede devolverse desde reservas.');
                }
            }

            $policy = $this->resolvePolicy($reservation->reserved_check_in_at, $event_type);
            $gross_paid_amount = 0.0;
            $refund_amount = 0.0;
            $penalty_amount = 0.0;

            foreach ($payments as $payment) {
                $payment_refund = round(
                    $payment->amount * ($policy['refund_percentage'] / 100),
                    2,
                    PHP_ROUND_HALF_UP
                );
                $payment_penalty = round($payment->amount - $payment_refund, 2, PHP_ROUND_HALF_UP);
                $gross_paid_amount = round($gross_paid_amount + $payment->amount, 2);
                $refund_amount = round($refund_amount + $payment_refund, 2);
                $penalty_amount = round($penalty_amount + $payment_penalty, 2);
            }

            $status = $refund_amount > 0 ? 'pending_refund' : 'no_refund';
            $cancellation = $this->cancellation_repository->create(
                reservation_id: $reservation_id,
                event_type: $event_type,
                policy_code: $policy['policy_code'],
                refund_percentage: $policy['refund_percentage'],
                gross_paid_amount: $gross_paid_amount,
                refund_amount: $refund_amount,
                penalty_amount: $penalty_amount,
                status: $status,
                reason: $normalized_reason,
                requested_by_user_id: $user_id,
            );

            $this->reservation_repository->updateStatus($reservation_id, $event_type);
            $this->audit_log_service->logAction(
                user_id: $user_id,
                action: $event_type === 'no_show'
                    ? 'DECLARE_RESERVATION_NO_SHOW'
                    : 'REQUEST_RESERVATION_CANCELLATION',
                entity_name: 'reservation_cancellations',
                entity_id: $cancellation->id,
                old_values: ['reservation_status' => $reservation->status],
                new_values: [
                    'reservation_status' => $event_type,
                    'status' => $status,
                    'refund_amount' => $refund_amount,
                    'penalty_amount' => $penalty_amount,
                ],
            );

            if ($owns_transaction) {
                $this->db->commit();
            }

            return $cancellation;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{policy_code: string, refund_percentage: float}
     */
    private function resolvePolicy(string $reserved_check_in_at, string $event_type): array
    {
        $check_in = new DateTimeImmutable($reserved_check_in_at, $this->clock->getTimezone());

        if ($event_type === 'no_show') {
            if ($this->clock < $check_in->modify('+2 hours')) {
                throw new DomainException('El no-show solo puede declararse después de dos horas del check-in.');
            }

            return [
                'policy_code' => 'no_refund',
                'refund_percentage' => 0.0,
            ];
        }

        if ($this->clock >= $check_in) {
            throw new DomainException('La cancelación normal no está permitida después del inicio del check-in.');
        }

        $hours_until_check_in = ($check_in->getTimestamp() - $this->clock->getTimestamp()) / 3600;
        if ($hours_until_check_in >= 24) {
            return [
                'policy_code' => 'full_refund',
                'refund_percentage' => 100.0,
            ];
        }

        return [
            'policy_code' => 'partial_refund',
            'refund_percentage' => 50.0,
        ];
    }
}
