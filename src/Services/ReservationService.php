<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\Reservation;
use App\Models\Stay;
use App\Repositories\GuestRepository;
use App\Repositories\ReservationPaymentRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\StayGuestRepository;
use App\Repositories\StayRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class ReservationService
{
    private PDO $db;
    private ReservationRepository $reservation_repository;
    private RoomRepository $room_repository;
    private GuestRepository $guest_repository;
    private RoomTariffRepository $tariff_repository;
    private StayRepository $stay_repository;
    private StayGuestRepository $stay_guest_repository;
    private ReservationPaymentRepository $reservation_payment_repository;
    private CashboxService $cashbox_service;
    private FolioService $folio_service;
    private AuditLogService $audit_log_service;

    public function __construct(
        ?PDO $db = null,
        ?ReservationRepository $reservation_repository = null,
        ?RoomRepository $room_repository = null,
        ?GuestRepository $guest_repository = null,
        ?RoomTariffRepository $tariff_repository = null,
        ?StayRepository $stay_repository = null,
        ?StayGuestRepository $stay_guest_repository = null,
        ?ReservationPaymentRepository $reservation_payment_repository = null,
        ?CashboxService $cashbox_service = null,
        ?FolioService $folio_service = null,
        ?AuditLogService $audit_log_service = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->reservation_repository = $reservation_repository ?? new ReservationRepository($this->db);
        $this->room_repository = $room_repository ?? new RoomRepository($this->db);
        $this->guest_repository = $guest_repository ?? new GuestRepository($this->db);
        $this->tariff_repository = $tariff_repository ?? new RoomTariffRepository($this->db);
        $this->stay_repository = $stay_repository ?? new StayRepository($this->db);
        $this->stay_guest_repository = $stay_guest_repository ?? new StayGuestRepository($this->db);
        $this->reservation_payment_repository = $reservation_payment_repository ?? new ReservationPaymentRepository($this->db);
        $this->cashbox_service = $cashbox_service ?? new CashboxService($this->db);
        $this->folio_service = $folio_service ?? new FolioService($this->db);
        $this->audit_log_service = $audit_log_service ?? new AuditLogService($this->db);
    }

    /**
     * @param list<mixed>|null $payment_parts
     */
    public function createReservation(
        int $room_id,
        int $guest_id,
        int $room_tariff_id,
        string $reserved_check_in_at,
        float $advance_payment,
        int $created_by_user_id,
        ?string $notes = null,
        ?array $payment_parts = null
    ): Reservation {
        if (!is_finite($advance_payment) || $advance_payment < 0 || abs($advance_payment - round($advance_payment, 2)) > 0.000001) {
            throw new InvalidArgumentException('El adelanto debe ser válido y tener como máximo dos decimales.');
        }

        $normalized_payment_parts = $this->normalizePaymentParts($advance_payment, $payment_parts);
        $resolved_advance_payment = array_reduce(
            $normalized_payment_parts,
            static fn(float $total, array $payment_part): float => $total + $payment_part['amount'],
            0.0
        );

        $room = $this->room_repository->findById($room_id);
        if ($room === null) {
            throw new InvalidArgumentException("Habitación no encontrada.");
        }

        $guest = $this->guest_repository->findById($guest_id);
        if ($guest === null) {
            throw new InvalidArgumentException("Huésped no encontrado.");
        }

        $tariff = $this->tariff_repository->findById($room_tariff_id);
        if ($tariff === null) {
            throw new InvalidArgumentException("Tarifa no encontrada.");
        }

        if ($resolved_advance_payment > round($tariff->price, 2)) {
            throw new InvalidArgumentException('El adelanto no puede superar el precio de la tarifa.');
        }

        $check_in_ts = strtotime($reserved_check_in_at);
        if ($check_in_ts === false) {
            throw new InvalidArgumentException("Fecha de check-in inválida.");
        }

        $reserved_check_out_at = date('Y-m-d H:i:s', $check_in_ts + ($tariff->duration_hours * 3600));

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            if (!$this->room_repository->lockById($room_id)) {
                throw new InvalidArgumentException('Habitación no encontrada.');
            }

            if ($this->reservation_repository->hasOverlappingReservation($room_id, $reserved_check_in_at, $reserved_check_out_at)) {
                throw new DomainException("La habitación ya tiene una reserva activa en ese rango de fechas.");
            }

            $reservation = $this->reservation_repository->create(
                room_id: $room_id,
                guest_id: $guest_id,
                room_tariff_id: $room_tariff_id,
                reserved_check_in_at: $reserved_check_in_at,
                reserved_check_out_at: $reserved_check_out_at,
                advance_payment: $resolved_advance_payment,
                created_by_user_id: $created_by_user_id,
                notes: $notes
            );

            $payment_ids = [];
            foreach ($normalized_payment_parts as $payment_part) {
                $shift = $this->cashbox_service->getCurrentShift($created_by_user_id);
                if ($shift === null) {
                    throw new DomainException('Debe abrir un turno de caja antes de registrar un adelanto.');
                }

                $payment = $this->reservation_payment_repository->create(
                    reservation_id: $reservation->id,
                    cashbox_shift_id: $shift->id,
                    amount: $payment_part['amount'],
                    payment_method: $payment_part['payment_method'],
                    created_by_user_id: $created_by_user_id,
                    reference_number: $payment_part['reference_number'],
                    notes: $payment_part['notes']
                );

                $this->cashbox_service->recordMovement(
                    user_id: $created_by_user_id,
                    type: 'income',
                    category: 'accommodation',
                    concept: "Adelanto de reserva #{$reservation->id}",
                    amount: $payment->amount,
                    payment_method: $payment->payment_method,
                    reference_id: $payment->id,
                    reference_type: 'reservation_payment'
                );
                $payment_ids[] = $payment->id;
            }

            $this->audit_log_service->logAction(
                user_id: $created_by_user_id,
                action: 'CREATE_RESERVATION',
                entity_name: 'reservations',
                entity_id: $reservation->id,
                new_values: [
                    'advance_payment' => $resolved_advance_payment,
                    'payment_ids' => $payment_ids,
                ]
            );

            if ($owns_transaction) {
                $this->db->commit();
            }

            return $reservation;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param list<mixed>|null $payment_parts
     * @return list<array{amount: float, payment_method: string, reference_number: ?string, notes: ?string}>
     */
    private function normalizePaymentParts(float $advance_payment, ?array $payment_parts): array
    {
        if ($payment_parts === null || $payment_parts === []) {
            if ($advance_payment > 0) {
                throw new InvalidArgumentException('Todo adelanto positivo debe indicar sus partes de pago.');
            }

            return [];
        }

        $normalized = [];
        $total = 0.0;
        foreach ($payment_parts as $payment_part) {
            if (!is_array($payment_part)) {
                throw new InvalidArgumentException('Cada parte de pago debe ser un objeto válido.');
            }

            $amount = $payment_part['amount'] ?? null;
            if ((!is_int($amount) && !is_float($amount) && !is_string($amount)) || !is_numeric($amount)) {
                throw new InvalidArgumentException('El monto de cada parte de pago debe ser numérico.');
            }

            $resolved_amount = (float) $amount;
            if (!is_finite($resolved_amount) || $resolved_amount <= 0 || abs($resolved_amount - round($resolved_amount, 2)) > 0.000001) {
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

            $normalized[] = [
                'amount' => round($resolved_amount, 2),
                'payment_method' => $payment_method,
                'reference_number' => $reference_number,
                'notes' => $part_notes,
            ];
            $total += $resolved_amount;
        }

        $total = round($total, 2);
        if ($total <= 0) {
            throw new InvalidArgumentException('La suma de las partes de pago debe ser mayor a cero.');
        }

        if ($advance_payment > 0 && abs($total - round($advance_payment, 2)) > 0.000001) {
            throw new InvalidArgumentException('La suma de las partes de pago debe coincidir con el adelanto.');
        }

        return $normalized;
    }

    public function convertReservationToStay(int $reservation_id, int $user_id): Stay
    {
        $reservation = $this->reservation_repository->findById($reservation_id);
        if ($reservation === null) {
            throw new InvalidArgumentException("Reserva no encontrada.");
        }

        if (!in_array($reservation->status, ['pending', 'confirmed'], true)) {
            throw new DomainException("La reserva no se encuentra en estado pendiente o confirmada.");
        }

        $room = $this->room_repository->findById($reservation->room_id);
        if ($room === null) {
            throw new InvalidArgumentException("Habitación asociada no encontrada.");
        }

        if ($room->status !== 'free') {
            throw new DomainException("La habitación {$room->number} no está libre para realizar el check-in.");
        }

        $tariff = $reservation->tariff;
        if ($tariff === null) {
            throw new InvalidArgumentException("Tarifa asociada no encontrada.");
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            if (!$this->reservation_repository->lockById($reservation_id)) {
                throw new InvalidArgumentException("Reserva no encontrada.");
            }

            $reservation = $this->reservation_repository->findById($reservation_id);
            if ($reservation === null) {
                throw new InvalidArgumentException("Reserva no encontrada.");
            }

            if (!in_array($reservation->status, ['pending', 'confirmed'], true)) {
                throw new DomainException("La reserva no se encuentra en estado pendiente o confirmada.");
            }

            if (!$this->room_repository->lockById($reservation->room_id)) {
                throw new InvalidArgumentException("Habitación asociada no encontrada.");
            }

            $room = $this->room_repository->findById($reservation->room_id);
            if ($room === null || $room->status !== 'free') {
                throw new DomainException("La habitación no está libre para realizar el check-in.");
            }

            $tariff = $reservation->tariff;
            if ($tariff === null) {
                throw new InvalidArgumentException("Tarifa asociada no encontrada.");
            }

            $this->reservation_repository->updateStatus($reservation_id, 'checked_in');
            $this->room_repository->updateStatus($reservation->room_id, 'occupied');

            $check_in_at = date('Y-m-d H:i:s');
            $expected_check_out_at = date('Y-m-d H:i:s', time() + ($tariff->duration_hours * 3600));

            $stay = $this->stay_repository->create(
                room_id: $reservation->room_id,
                room_tariff_id: $reservation->room_tariff_id,
                check_in_at: $check_in_at,
                expected_check_out_at: $expected_check_out_at,
                base_price: $tariff->price,
                applied_price: $tariff->price,
                discount_reason: null,
                created_by_user_id: $user_id,
                guest_id: $reservation->guest_id
            );

            $this->stay_guest_repository->attachGuestToStay(
                stay_id: $stay->id,
                guest_id: $reservation->guest_id,
                is_primary: true
            );

            $this->folio_service->addCharge(
                stay_id: $stay->id,
                charge_type: 'accommodation',
                description: "Alojamiento Inicial - Tarifa {$tariff->name}",
                quantity: 1,
                unit_price: $tariff->price,
                created_by_user_id: $user_id
            );

            $reservation_payments = $this->reservation_payment_repository->findByReservationId($reservation_id);
            foreach ($reservation_payments as $reservation_payment) {
                $this->folio_service->applyReservationPayment(
                    stay_id: $stay->id,
                    reservation_payment: $reservation_payment,
                    applied_by_user_id: $user_id
                );
            }

            $folio = $this->folio_service->getOrCreateFolio($stay->id);
            $this->audit_log_service->logAction(
                user_id: $user_id,
                action: 'CHECK_IN_RESERVATION',
                entity_name: 'stays',
                entity_id: $stay->id,
                new_values: [
                    'reservation_id' => $reservation_id,
                    'folio_id' => $folio->id,
                    'reservation_payment_count' => count($reservation_payments),
                ]
            );

            if ($owns_transaction) {
                $this->db->commit();
            }
            return $stay;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<int, Reservation>
     */
    public function getActiveReservations(): array
    {
        return $this->reservation_repository->findAllActive();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     room_id: int,
     *     guest_id: int,
     *     room_tariff_id: int,
     *     reserved_check_in_at: string,
     *     reserved_check_out_at: string,
     *     advance_payment: float,
     *     status: string,
     *     notes: ?string,
     *     created_by_user_id: int,
     *     created_at: string,
     *     updated_at: string,
     *     room_number: string,
     *     room_floor: string,
     *     category_name: string,
     *     guest_name: string,
     *     guest_document_type: string,
     *     guest_document_number: string,
     *     guest_phone: ?string,
     *     guest_email: ?string,
     *     tariff_name: string,
     *     tariff_duration_hours: int,
     *     tariff_price: float
     * }>
     */
    public function getReservationsCatalog(?string $search = null, ?string $status = null): array
    {
        return $this->reservation_repository->getReservationsCatalog($search, $status);
    }

    /**
     * @return array{
     *     total_reservations: int,
     *     active_reservations: int,
     *     pending_count: int,
     *     confirmed_count: int,
     *     checked_in_count: int,
     *     cancelled_count: int,
     *     total_advance_amount: float,
     *     today_checkins_count: int
     * }
     */
    public function getReservationMetrics(): array
    {
        return $this->reservation_repository->getMetrics();
    }

    /**
     * @return array{
     *     id: int,
     *     room_id: int,
     *     guest_id: int,
     *     room_tariff_id: int,
     *     reserved_check_in_at: string,
     *     reserved_check_out_at: string,
     *     advance_payment: float,
     *     status: string,
     *     notes: ?string,
     *     created_by_user_id: int,
     *     created_at: string,
     *     updated_at: string,
     *     room_number: string,
     *     room_floor: string,
     *     category_name: string,
     *     guest_name: string,
     *     guest_document_type: string,
     *     guest_document_number: string,
     *     guest_phone: ?string,
     *     guest_email: ?string,
     *     tariff_name: string,
     *     tariff_duration_hours: int,
     *     tariff_price: float
     * }|null
     */
    public function getReservationById(int $id): ?array
    {
        $list = $this->reservation_repository->getReservationsCatalog();
        foreach ($list as $res) {
            if ($res['id'] === $id) {
                return $res;
            }
        }
        return null;
    }
}
