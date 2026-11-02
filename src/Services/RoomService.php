<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\Room;
use App\Models\RoomTariff;
use App\Models\Stay;
use App\Models\StayExtension;
use App\Repositories\GuestRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use App\Repositories\RoomTransferRepository;
use App\Repositories\StayExtensionRepository;
use App\Repositories\StayRepository;
use DomainException;
use InvalidArgumentException;
use PDO;

final class RoomService
{
    private PDO $db;
    private RoomRepository $room_repository;
    private RoomTariffRepository $tariff_repository;
    private StayRepository $stay_repository;
    private StayExtensionRepository $extension_repository;
    private RoomTransferRepository $transfer_repository;
    private FolioService $folio_service;
    private HousekeepingService $housekeeping_service;
    private RoomCategoryRepository $category_repository;
    private GuestRepository $guest_repository;
    private ReservationRepository $reservation_repository;
    private AuditLogService $audit_log_service;

    public function __construct(
        ?PDO $db = null,
        ?RoomRepository $room_repository = null,
        ?RoomTariffRepository $tariff_repository = null,
        ?StayRepository $stay_repository = null,
        ?StayExtensionRepository $extension_repository = null,
        ?RoomTransferRepository $transfer_repository = null,
        ?FolioService $folio_service = null,
        ?HousekeepingService $housekeeping_service = null,
        ?RoomCategoryRepository $category_repository = null,
        ?GuestRepository $guest_repository = null,
        ?ReservationRepository $reservation_repository = null,
        ?AuditLogService $audit_log_service = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->room_repository = $room_repository ?? new RoomRepository($this->db);
        $this->tariff_repository = $tariff_repository ?? new RoomTariffRepository($this->db);
        $this->stay_repository = $stay_repository ?? new StayRepository($this->db);
        $this->extension_repository = $extension_repository ?? new StayExtensionRepository($this->db);
        $this->transfer_repository = $transfer_repository ?? new RoomTransferRepository($this->db);
        $this->folio_service = $folio_service ?? new FolioService($this->db);
        $this->housekeeping_service = $housekeeping_service ?? new HousekeepingService($this->db);
        $this->category_repository = $category_repository ?? new RoomCategoryRepository($this->db);
        $this->guest_repository = $guest_repository ?? new GuestRepository($this->db);
        $this->reservation_repository = $reservation_repository ?? new ReservationRepository($this->db);
        $this->audit_log_service = $audit_log_service ?? new AuditLogService($this->db);
    }

    public function getRoomRepository(): RoomRepository
    {
        return $this->room_repository;
    }

    public function getFolioService(): FolioService
    {
        return $this->folio_service;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getDashboardMap(): array
    {
        $rooms = $this->room_repository->findAll();

        return array_map(function (Room $room): array {
            $stay_data = null;
            $seconds_remaining = null;

            if ($room->status === 'occupied' && $room->current_stay !== null) {
                $seconds_remaining = $this->calculateSecondsRemaining($room->current_stay->expected_check_out_at);
                $folio = $this->folio_service->getOrCreateFolio($room->current_stay->id);

                $guest = $room->current_stay->guest_id !== null ? $this->guest_repository->findById($room->current_stay->guest_id) : null;
                $guest_name = $guest !== null ? $guest->getFullName() : null;
                if ($guest_name === null || $guest_name === '') {
                    $guest_name = 'Huésped Registrado';
                }

                $charges_data = array_map(fn($c) => [
                    'id' => $c->id,
                    'charge_type' => $c->charge_type,
                    'description' => $c->description,
                    'quantity' => $c->quantity,
                    'unit_price' => $c->unit_price,
                    'total_amount' => $c->total_amount,
                ], $folio->charges);

                $payments_data = array_map(fn($p) => [
                    'id' => $p->id,
                    'payment_type' => $p->payment_type,
                    'amount' => $p->amount,
                    'payment_method' => $p->payment_method,
                    'created_at' => $p->created_at,
                ], $folio->payments);

                $stay_data = [
                    'id' => $room->current_stay->id,
                    'guest_id' => $room->current_stay->guest_id,
                    'guest_name' => $guest_name,
                    'guest_doc' => $guest?->document_number ?? '---',
                    'guest_doc_type' => $guest?->document_type ?? 'DNI',
                    'check_in_at' => $room->current_stay->check_in_at,
                    'expected_check_out_at' => $room->current_stay->expected_check_out_at,
                    'base_price' => $room->current_stay->base_price,
                    'applied_price' => $room->current_stay->applied_price,
                    'seconds_remaining' => $seconds_remaining,
                    'is_overdue' => $seconds_remaining < 0,
                    'extensions_count' => count($room->current_stay->extensions),
                    'total_charges' => $folio->total_charges,
                    'total_payments' => $folio->total_payments,
                    'pending_balance' => $folio->pending_balance,
                    'charges' => $charges_data,
                    'payments' => $payments_data,
                ];
            }

            return [
                'id' => $room->id,
                'number' => $room->number,
                'status' => $room->status,
                'floor' => $room->floor,
                'category' => $room->category?->name ?? 'General',
                'category_name' => $room->category?->name ?? 'General',
                'room_category_id' => $room->room_category_id,
                'current_stay' => $stay_data,
            ];
        }, $rooms);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTariffsCatalog(): array
    {
        $tariffs = $this->tariff_repository->findAll();
        $categories = $this->category_repository->findAll();
        $cat_map = [];
        foreach ($categories as $cat) {
            $cat_map[$cat->id] = $cat->name;
        }

        return array_map(function (RoomTariff $t) use ($cat_map): array {
            return [
                'id' => $t->id,
                'room_category_id' => $t->room_category_id,
                'category_name' => $cat_map[$t->room_category_id] ?? 'General',
                'name' => $t->name,
                'duration_hours' => $t->duration_hours,
                'price' => $t->price,
            ];
        }, $tariffs);
    }

    /**
     * @param list<mixed>|null $payment_parts
     */
    public function occupyRoom(
        int $room_id,
        int $tariff_id,
        float $applied_price,
        ?string $discount_reason,
        int $created_by_user_id,
        ?int $guest_id = null,
        ?string $custom_check_in_time = null,
        ?string $custom_check_out_time = null,
        float $initial_payment_amount = 0.0,
        string $payment_method = 'cash',
        ?string $payment_reference = null,
        ?array $payment_parts = null
    ): Stay {
        if (!is_finite($applied_price) || $applied_price < 0 || abs($applied_price - round($applied_price, 2)) > 0.000001) {
            throw new InvalidArgumentException('El precio aplicado debe ser válido y tener como máximo dos decimales.');
        }

        $resolved_payment_parts = $this->resolveInitialPaymentParts(
            initial_payment_amount: $initial_payment_amount,
            payment_method: $payment_method,
            payment_reference: $payment_reference,
            payment_parts: $payment_parts
        );

        $room = $this->room_repository->findById($room_id);
        if ($room === null) {
            throw new InvalidArgumentException("Habitación con ID {$room_id} no encontrada.");
        }

        if ($room->status !== 'free') {
            throw new DomainException("La habitación {$room->number} no está libre (estado actual: {$room->status}).");
        }

        $tariff = $this->tariff_repository->findById($tariff_id);
        if ($tariff === null) {
            throw new InvalidArgumentException("Tarifa con ID {$tariff_id} no encontrada.");
        }

        if ($tariff->room_category_id !== $room->room_category_id) {
            throw new DomainException("La tarifa no corresponde a la categoría de esta habitación.");
        }

        if ($applied_price < $tariff->price && ($discount_reason === null || trim($discount_reason) === '')) {
            throw new InvalidArgumentException("Debe ingresar un motivo para justificar la modificación de precio.");
        }

        $check_in_timestamp = $custom_check_in_time !== null ? strtotime($custom_check_in_time) : time();
        if ($check_in_timestamp === false) {
            $check_in_timestamp = time();
        }

        $check_in_at = date('Y-m-d H:i:s', $check_in_timestamp);

        $custom_checkout_ts = $custom_check_out_time !== null ? strtotime($custom_check_out_time) : false;
        if ($custom_checkout_ts !== false) {
            $expected_check_out_at = date('Y-m-d H:i:s', $custom_checkout_ts);
        } else {
            $expected_check_out_at = date('Y-m-d H:i:s', $check_in_timestamp + ($tariff->duration_hours * 3600));
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            if (!$this->room_repository->lockById($room_id)) {
                throw new InvalidArgumentException("Habitación con ID {$room_id} no encontrada.");
            }

            $room = $this->room_repository->findById($room_id);
            if ($room === null || $room->status !== 'free') {
                throw new DomainException("La habitación no está libre.");
            }

            if ($this->reservation_repository->hasOverlappingReservation($room_id, $check_in_at, $expected_check_out_at)) {
                throw new DomainException("No se puede registrar el hospedaje: el horario de salida previsto ({$expected_check_out_at}) se cruza con una reserva activa o futura de la habitación {$room->number}.");
            }

            $this->room_repository->updateStatus($room_id, 'occupied');
            $stay = $this->stay_repository->create(
                room_id: $room_id,
                room_tariff_id: $tariff_id,
                check_in_at: $check_in_at,
                expected_check_out_at: $expected_check_out_at,
                base_price: $tariff->price,
                applied_price: $applied_price,
                discount_reason: $discount_reason,
                created_by_user_id: $created_by_user_id,
                guest_id: $guest_id
            );

            // Auto-create initial accommodation charge on Folio
            $this->folio_service->addCharge(
                stay_id: $stay->id,
                charge_type: 'accommodation',
                description: "Alojamiento Inicial - Tarifa {$tariff->name}",
                quantity: 1,
                unit_price: $applied_price,
                created_by_user_id: $created_by_user_id
            );

            if ($resolved_payment_parts !== []) {
                $this->folio_service->addPaymentParts(
                    stay_id: $stay->id,
                    payment_type: 'advance',
                    payment_parts: $resolved_payment_parts,
                    created_by_user_id: $created_by_user_id,
                    notes: 'Adelanto / Pago inicial al Check-in'
                );
            }

            $folio = $this->folio_service->getOrCreateFolio($stay->id);
            $this->audit_log_service->logAction(
                user_id: $created_by_user_id,
                action: 'CREATE_DIRECT_CHECK_IN',
                entity_name: 'stays',
                entity_id: $stay->id,
                new_values: [
                    'room_id' => $room_id,
                    'folio_id' => $folio->id,
                    'applied_price' => $applied_price,
                    'payment_part_count' => count($resolved_payment_parts),
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

    public function extendStay(
        int $room_id,
        int $added_hours,
        float $added_price,
        int $created_by_user_id
    ): StayExtension {
        if ($added_hours <= 0) {
            throw new InvalidArgumentException("Las horas adicionadas deben ser mayores a 0.");
        }

        if (!is_finite($added_price) || $added_price < 0 || abs($added_price - round($added_price, 2)) > 0.000001) {
            throw new InvalidArgumentException('El precio de la extensión debe ser válido y tener como máximo dos decimales.');
        }

        $room = $this->room_repository->findById($room_id);
        if ($room === null) {
            throw new InvalidArgumentException("Habitación con ID {$room_id} no encontrada.");
        }

        if ($room->status !== 'occupied' || $room->current_stay === null) {
            throw new DomainException("La habitación no tiene una estancia activa para extender.");
        }

        $stay = $room->current_stay;
        $current_expected_ts = strtotime($stay->expected_check_out_at);
        if ($current_expected_ts === false) {
            $current_expected_ts = time();
        }

        $new_expected_check_out_at = date('Y-m-d H:i:s', $current_expected_ts + ($added_hours * 3600));

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            $this->stay_repository->updateExpectedCheckout($stay->id, $new_expected_check_out_at);
            $extension = $this->extension_repository->create(
                stay_id: $stay->id,
                added_hours: $added_hours,
                added_price: $added_price,
                created_by_user_id: $created_by_user_id
            );

            if ($added_price > 0) {
                $this->folio_service->addCharge(
                    stay_id: $stay->id,
                    charge_type: 'extension',
                    description: "Extensión de estancia +{$added_hours} horas",
                    quantity: 1,
                    unit_price: $added_price,
                    created_by_user_id: $created_by_user_id
                );
            }

            if ($owns_transaction) {
                $this->db->commit();
            }
            return $extension;
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function transferRoom(
        int $from_room_id,
        int $to_room_id,
        string $reason,
        int $created_by_user_id
    ): void {
        if (trim($reason) === '') {
            throw new InvalidArgumentException("Debe ingresar un motivo para la transferencia de habitación.");
        }

        $from_room = $this->room_repository->findById($from_room_id);
        if ($from_room === null || $from_room->status !== 'occupied' || $from_room->current_stay === null) {
            throw new DomainException("La habitación de origen no posee una estancia activa para transferir.");
        }

        $to_room = $this->room_repository->findById($to_room_id);
        if ($to_room === null) {
            throw new InvalidArgumentException("Habitación de destino no encontrada.");
        }

        if ($to_room->status !== 'free') {
            throw new DomainException("La habitación de destino {$to_room->number} no está libre (estado actual: {$to_room->status}).");
        }

        $stay_id = $from_room->current_stay->id;

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            // Update stay room pointer
            $this->stay_repository->updateRoomId($stay_id, $to_room_id);

            // Update rooms statuses
            $this->room_repository->updateStatus($from_room_id, 'cleaning');
            $this->room_repository->updateStatus($to_room_id, 'occupied');

            // Record transfer history
            $this->transfer_repository->create(
                stay_id: $stay_id,
                from_room_id: $from_room_id,
                to_room_id: $to_room_id,
                reason: $reason,
                created_by_user_id: $created_by_user_id
            );

            // Auto-create checkout cleaning task for from_room
            $this->housekeeping_service->createTask(
                room_id: $from_room_id,
                task_type: 'checkout_cleaning',
                priority: 'normal',
                created_by_user_id: $created_by_user_id,
                notes: "Limpieza tras transferencia de habitación {$from_room->number} a {$to_room->number}"
            );

            if ($owns_transaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param list<mixed>|null $payment_parts
     */
    public function checkoutRoom(
        int $room_id,
        ?string $payment_method = 'cash',
        ?string $payment_reference = null,
        int $user_id = 1,
        ?array $payment_parts = null
    ): void {
        $room = $this->room_repository->findById($room_id);
        if ($room === null) {
            throw new InvalidArgumentException("Habitación con ID {$room_id} no encontrada.");
        }

        if ($room->status !== 'occupied' || $room->current_stay === null) {
            throw new DomainException("La habitación no está ocupada.");
        }

        $owns_transaction = !$this->db->inTransaction();
        if ($owns_transaction) {
            $this->db->beginTransaction();
        }

        try {
            if (!$this->room_repository->lockById($room_id)) {
                throw new InvalidArgumentException("Habitación con ID {$room_id} no encontrada.");
            }

            $room = $this->room_repository->findById($room_id);
            if ($room === null || $room->status !== 'occupied' || $room->current_stay === null) {
                throw new DomainException("La habitación no está ocupada.");
            }

            $stay_id = $room->current_stay->id;
            if (!$this->stay_repository->lockById($stay_id)) {
                throw new InvalidArgumentException("Estancia con ID {$stay_id} no encontrada.");
            }

            $folio = $this->folio_service->getOrCreateFolio($stay_id);

            if ($folio->pending_balance > 0.0) {
                $checkout_payment_parts = $payment_parts;
                if ($checkout_payment_parts === null) {
                    $checkout_payment_parts = [[
                        'amount' => $folio->pending_balance,
                        'payment_method' => $payment_method ?? 'cash',
                        'reference_number' => $payment_reference,
                    ]];
                }

                $this->folio_service->addPaymentParts(
                    stay_id: $stay_id,
                    payment_type: 'settlement',
                    payment_parts: $checkout_payment_parts,
                    created_by_user_id: $user_id,
                    notes: "Liquidación final en checkout de Habitación {$room->number}",
                    require_exact_balance: true
                );
            } elseif ($payment_parts !== null && $payment_parts !== []) {
                throw new InvalidArgumentException('El Folio ya está saldado y no admite pagos adicionales.');
            }

            $this->stay_repository->completeStay($stay_id, date('Y-m-d H:i:s'));
            $this->room_repository->updateStatus($room_id, 'cleaning');

            $this->folio_service->closeFolio($stay_id);

            // Auto-create checkout cleaning task
            $this->housekeeping_service->createTask(
                room_id: $room_id,
                task_type: 'checkout_cleaning',
                priority: 'normal',
                created_by_user_id: $user_id,
                notes: "Limpieza automática tras Checkout de Habitación {$room->number}"
            );

            $this->audit_log_service->logAction(
                user_id: $user_id,
                action: 'CHECKOUT_STAY',
                entity_name: 'stays',
                entity_id: $stay_id,
                new_values: [
                    'room_id' => $room_id,
                    'folio_id' => $folio->id,
                    'payment_part_count' => $payment_parts !== null ? count($payment_parts) : ($folio->pending_balance > 0 ? 1 : 0),
                ]
            );

            if ($owns_transaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($owns_transaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param list<mixed>|null $payment_parts
     * @return list<array{amount: int|float|string, payment_method: string, reference_number?: ?string, notes?: ?string}>
     */
    private function resolveInitialPaymentParts(
        float $initial_payment_amount,
        string $payment_method,
        ?string $payment_reference,
        ?array $payment_parts
    ): array {
        if (!is_finite($initial_payment_amount)
            || $initial_payment_amount < 0
            || abs($initial_payment_amount - round($initial_payment_amount, 2)) > 0.000001) {
            throw new InvalidArgumentException('El pago inicial debe ser válido y tener como máximo dos decimales.');
        }

        if ($payment_parts === null) {
            if (!in_array($payment_method, ['cash', 'yape', 'plin', 'card', 'transfer'], true)) {
                throw new InvalidArgumentException('Método de pago inválido.');
            }
            return $initial_payment_amount > 0
                ? [[
                    'amount' => $initial_payment_amount,
                    'payment_method' => $payment_method,
                    'reference_number' => $payment_reference,
                ]]
                : [];
        }

        if ($payment_parts === [] && $initial_payment_amount > 0) {
            throw new InvalidArgumentException('Todo pago inicial positivo debe indicar sus partes de pago.');
        }

        $normalized_parts = [];
        $total_amount = 0.0;
        foreach ($payment_parts as $payment_part) {
            if (!is_array($payment_part)) {
                throw new InvalidArgumentException('Cada parte de pago debe ser un objeto válido.');
            }

            $amount = $payment_part['amount'] ?? null;
            $method = $payment_part['payment_method'] ?? null;
            if ((!is_int($amount) && !is_float($amount) && !is_string($amount))
                || !is_numeric($amount)
                || !is_string($method)
                || !in_array($method, ['cash', 'yape', 'plin', 'card', 'transfer'], true)) {
                throw new InvalidArgumentException('Cada parte de pago debe incluir monto y método válidos.');
            }

            $resolved_amount = (float) $amount;
            if (!is_finite($resolved_amount)
                || $resolved_amount <= 0
                || abs($resolved_amount - round($resolved_amount, 2)) > 0.000001) {
                throw new InvalidArgumentException('Cada pago inicial debe ser mayor a cero y tener como máximo dos decimales.');
            }

            $reference_number = $payment_part['reference_number'] ?? null;
            $part_notes = $payment_part['notes'] ?? null;
            if (($reference_number !== null && !is_string($reference_number)) || ($part_notes !== null && !is_string($part_notes))) {
                throw new InvalidArgumentException('Los datos adicionales de la parte de pago son inválidos.');
            }

            $normalized_parts[] = [
                'amount' => round($resolved_amount, 2),
                'payment_method' => $method,
                'reference_number' => $reference_number,
                'notes' => $part_notes,
            ];
            $total_amount += $resolved_amount;
        }

        if ($initial_payment_amount > 0 && abs(round($total_amount, 2) - round($initial_payment_amount, 2)) > 0.000001) {
            throw new InvalidArgumentException('La suma de las partes debe coincidir con el pago inicial.');
        }

        return $normalized_parts;
    }

    public function updateRoomStatus(int $room_id, string $new_status): void
    {
        $allowed_statuses = ['free', 'occupied', 'cleaning', 'maintenance', 'reserved'];
        if (!in_array($new_status, $allowed_statuses, true)) {
            throw new InvalidArgumentException("Estado de habitación inválido: {$new_status}");
        }

        $room = $this->room_repository->findById($room_id);
        if ($room === null) {
            throw new InvalidArgumentException("Habitación con ID {$room_id} no encontrada.");
        }

        if ($room->status === 'occupied' && $new_status === 'free') {
            throw new DomainException("No se puede pasar directamente de ocupada a libre sin hacer checkout/limpieza.");
        }

        $this->room_repository->updateStatus($room_id, $new_status);
    }

    public function calculateSecondsRemaining(string $expected_check_out_at, ?string $now = null): int
    {
        $target_ts = strtotime($expected_check_out_at);
        $current_ts = $now !== null ? strtotime($now) : time();

        if ($target_ts === false || $current_ts === false) {
            return 0;
        }

        return $target_ts - $current_ts;
    }

    public function createRoom(
        int $category_id,
        string $number,
        ?string $floor = null,
        ?string $description = null
    ): Room {
        $trimmed_number = trim($number);
        if ($trimmed_number === '') {
            throw new InvalidArgumentException("El número de habitación no puede estar vacío.");
        }

        $existing = $this->room_repository->findByNumber($trimmed_number);
        if ($existing !== null) {
            throw new DomainException("Ya existe una habitación registrada con el número {$trimmed_number}.");
        }

        $category = $this->category_repository->findById($category_id);
        if ($category === null) {
            throw new InvalidArgumentException("Categoría de habitación no válida.");
        }

        return $this->room_repository->create(
            room_category_id: $category_id,
            number: $trimmed_number,
            floor: $floor !== null && trim($floor) !== '' ? trim($floor) : '1',
            description: $description
        );
    }

    public function updateRoom(
        int $id,
        int $category_id,
        string $number,
        ?string $floor = null,
        ?string $description = null
    ): Room {
        $trimmed_number = trim($number);
        if ($trimmed_number === '') {
            throw new InvalidArgumentException("El número de habitación no puede estar vacío.");
        }

        $room = $this->room_repository->findById($id);
        if ($room === null) {
            throw new InvalidArgumentException("Habitación no encontrada.");
        }

        $existing = $this->room_repository->findByNumber($trimmed_number);
        if ($existing !== null && $existing->id !== $id) {
            throw new DomainException("El número {$trimmed_number} ya pertenece a otra habitación.");
        }

        $category = $this->category_repository->findById($category_id);
        if ($category === null) {
            throw new InvalidArgumentException("Categoría de habitación no válida.");
        }

        return $this->room_repository->update(
            id: $id,
            room_category_id: $category_id,
            number: $trimmed_number,
            floor: $floor !== null && trim($floor) !== '' ? trim($floor) : '1',
            description: $description
        );
    }

    /**
     * @return array<int, array{id: int, name: string, description: ?string, is_active: bool, rooms_count: int}>
     */
    public function getCategoriesCatalog(): array
    {
        $categories = $this->category_repository->findAll();
        $rooms = $this->room_repository->findAll();

        return array_map(function ($cat) use ($rooms): array {
            $count = count(array_filter($rooms, fn($r) => $r->room_category_id === $cat->id));
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'is_active' => $cat->is_active,
                'rooms_count' => $count,
            ];
        }, $categories);
    }

    public function createCategory(string $name, ?string $description = null): \App\Models\RoomCategory
    {
        $trimmed_name = trim($name);
        if ($trimmed_name === '') {
            throw new InvalidArgumentException("El nombre de la categoría no puede estar vacío.");
        }

        return $this->category_repository->create($trimmed_name, $description);
    }

    public function updateCategory(int $id, string $name, ?string $description = null, int $is_active = 1): \App\Models\RoomCategory
    {
        $trimmed_name = trim($name);
        if ($trimmed_name === '') {
            throw new InvalidArgumentException("El nombre de la categoría no puede estar vacío.");
        }

        return $this->category_repository->update($id, $trimmed_name, $description, $is_active);
    }

    public function createTariff(
        int $room_category_id,
        string $name,
        int $duration_hours,
        float $price
    ): \App\Models\RoomTariff {
        $trimmed_name = trim($name);
        if ($trimmed_name === '') {
            throw new InvalidArgumentException("El nombre de la tarifa no puede estar vacío.");
        }

        if ($duration_hours <= 0) {
            throw new InvalidArgumentException("La duración debe ser mayor a 0 horas.");
        }

        if (!is_finite($price) || $price <= 0 || abs($price - round($price, 2)) > 0.000001) {
            throw new InvalidArgumentException("El precio debe ser mayor a cero.");
        }

        return $this->tariff_repository->create($room_category_id, $trimmed_name, $duration_hours, $price);
    }

    public function updateTariff(
        int $id,
        int $room_category_id,
        string $name,
        int $duration_hours,
        float $price,
        int $is_active = 1
    ): \App\Models\RoomTariff {
        $trimmed_name = trim($name);
        if ($trimmed_name === '') {
            throw new InvalidArgumentException("El nombre de la tarifa no puede estar vacío.");
        }

        if ($duration_hours <= 0) {
            throw new InvalidArgumentException("La duración debe ser mayor a 0 horas.");
        }

        if ($price <= 0) {
            throw new InvalidArgumentException("El precio debe ser mayor a cero.");
        }

        return $this->tariff_repository->update($id, $room_category_id, $trimmed_name, $duration_hours, $price, $is_active);
    }
}
