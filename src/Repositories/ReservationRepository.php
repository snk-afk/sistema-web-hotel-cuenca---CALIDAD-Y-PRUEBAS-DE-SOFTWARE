<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Reservation;
use PDO;

final class ReservationRepository
{
    private PDO $db;
    private RoomRepository $room_repository;
    private GuestRepository $guest_repository;
    private RoomTariffRepository $tariff_repository;

    public function __construct(
        ?PDO $db = null,
        ?RoomRepository $room_repository = null,
        ?GuestRepository $guest_repository = null,
        ?RoomTariffRepository $tariff_repository = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->room_repository = $room_repository ?? new RoomRepository($this->db);
        $this->guest_repository = $guest_repository ?? new GuestRepository($this->db);
        $this->tariff_repository = $tariff_repository ?? new RoomTariffRepository($this->db);
    }

    public function findById(int $id): ?Reservation
    {
        $stmt = $this->db->prepare(
            'SELECT id, room_id, guest_id, room_tariff_id, start_time, end_time,
                    deposit_amount, status, notes, created_by_user_id, created_at, updated_at
             FROM reservations
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, room_id: int|string, guest_id: int|string, room_tariff_id: int|string, start_time: string, end_time: string, deposit_amount: float|string, status: string, notes: ?string, created_by_user_id: int|string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $room = $this->room_repository->findById((int) $row['room_id']);
        $guest = $this->guest_repository->findById((int) $row['guest_id']);
        $tariff = $this->tariff_repository->findById((int) $row['room_tariff_id']);

        return new Reservation(
            id: (int) $row['id'],
            room_id: (int) $row['room_id'],
            guest_id: (int) $row['guest_id'],
            room_tariff_id: (int) $row['room_tariff_id'],
            reserved_check_in_at: $row['start_time'],
            reserved_check_out_at: $row['end_time'],
            advance_payment: (float) $row['deposit_amount'],
            status: $row['status'],
            notes: $row['notes'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            room: $room,
            guest: $guest,
            tariff: $tariff,
        );
    }

    public function lockById(int $id): bool
    {
        $sql = 'SELECT id FROM reservations WHERE id = :id';
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['id' => $id]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @return array<int, Reservation>
     */
    public function findAllActive(): array
    {
        $stmt = $this->db->query(
            'SELECT id, room_id, guest_id, room_tariff_id, start_time, end_time,
                    deposit_amount, status, notes, created_by_user_id, created_at, updated_at
             FROM reservations
             WHERE status IN ("pending", "confirmed")
             ORDER BY start_time ASC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, room_id: int|string, guest_id: int|string, room_tariff_id: int|string, start_time: string, end_time: string, deposit_amount: float|string, status: string, notes: ?string, created_by_user_id: int|string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): Reservation {
                $room = $this->room_repository->findById((int) $row['room_id']);
                $guest = $this->guest_repository->findById((int) $row['guest_id']);
                $tariff = $this->tariff_repository->findById((int) $row['room_tariff_id']);

                return new Reservation(
                    id: (int) $row['id'],
                    room_id: (int) $row['room_id'],
                    guest_id: (int) $row['guest_id'],
                    room_tariff_id: (int) $row['room_tariff_id'],
                    reserved_check_in_at: $row['start_time'],
                    reserved_check_out_at: $row['end_time'],
                    advance_payment: (float) $row['deposit_amount'],
                    status: $row['status'],
                    notes: $row['notes'],
                    created_by_user_id: (int) $row['created_by_user_id'],
                    created_at: $row['created_at'],
                    updated_at: $row['updated_at'],
                    room: $room,
                    guest: $guest,
                    tariff: $tariff,
                );
            },
            $rows
        );
    }

    /**
     * @return array<int, Reservation>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, room_id, guest_id, room_tariff_id, start_time, end_time,
                    deposit_amount, status, notes, created_by_user_id, created_at, updated_at
             FROM reservations
             ORDER BY start_time DESC, id DESC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, room_id: int|string, guest_id: int|string, room_tariff_id: int|string, start_time: string, end_time: string, deposit_amount: float|string, status: string, notes: ?string, created_by_user_id: int|string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): Reservation {
                $room = $this->room_repository->findById((int) $row['room_id']);
                $guest = $this->guest_repository->findById((int) $row['guest_id']);
                $tariff = $this->tariff_repository->findById((int) $row['room_tariff_id']);

                return new Reservation(
                    id: (int) $row['id'],
                    room_id: (int) $row['room_id'],
                    guest_id: (int) $row['guest_id'],
                    room_tariff_id: (int) $row['room_tariff_id'],
                    reserved_check_in_at: $row['start_time'],
                    reserved_check_out_at: $row['end_time'],
                    advance_payment: (float) $row['deposit_amount'],
                    status: $row['status'],
                    notes: $row['notes'],
                    created_by_user_id: (int) $row['created_by_user_id'],
                    created_at: $row['created_at'],
                    updated_at: $row['updated_at'],
                    room: $room,
                    guest: $guest,
                    tariff: $tariff,
                );
            },
            $rows
        );
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
        $sql = 'SELECT r.id, r.room_id, r.guest_id, r.room_tariff_id,
                       r.start_time AS reserved_check_in_at,
                       r.end_time AS reserved_check_out_at,
                       r.deposit_amount AS advance_payment,
                       r.status, r.notes, r.created_by_user_id, r.created_at, r.updated_at,
                       rm.number AS room_number, rm.floor AS room_floor, rc.name AS category_name,
                       g.name AS guest_name, g.document_type AS guest_document_type, g.document_number AS guest_document_number,
                       g.phone AS guest_phone, g.email AS guest_email,
                       rt.name AS tariff_name, rt.duration_hours AS tariff_duration_hours, rt.price AS tariff_price
                FROM reservations r
                INNER JOIN rooms rm ON r.room_id = rm.id
                LEFT JOIN room_categories rc ON rm.room_category_id = rc.id
                INNER JOIN guests g ON r.guest_id = g.id
                INNER JOIN room_tariffs rt ON r.room_tariff_id = rt.id
                WHERE 1=1';

        $params = [];
        if ($status !== null && trim($status) !== '' && $status !== 'all') {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status;
        }

        if ($search !== null && trim($search) !== '') {
            $sql .= ' AND (g.name LIKE :search OR g.document_number LIKE :search OR rm.number LIKE :search OR r.id LIKE :search)';
            $params['search'] = '%' . trim($search) . '%';
        }

        $sql .= ' ORDER BY r.start_time ASC, r.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        /** @var array<int, array{
         *     id: int|string,
         *     room_id: int|string,
         *     guest_id: int|string,
         *     room_tariff_id: int|string,
         *     reserved_check_in_at: string,
         *     reserved_check_out_at: string,
         *     advance_payment: float|string,
         *     status: string,
         *     notes: ?string,
         *     created_by_user_id: int|string,
         *     created_at: string,
         *     updated_at: string,
         *     room_number: string,
         *     room_floor: string,
         *     category_name: ?string,
         *     guest_name: string,
         *     guest_document_type: string,
         *     guest_document_number: string,
         *     guest_phone: ?string,
         *     guest_email: ?string,
         *     tariff_name: string,
         *     tariff_duration_hours: int|string,
         *     tariff_price: float|string
         * }> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $r) => [
                'id' => (int) $r['id'],
                'room_id' => (int) $r['room_id'],
                'guest_id' => (int) $r['guest_id'],
                'room_tariff_id' => (int) $r['room_tariff_id'],
                'reserved_check_in_at' => $r['reserved_check_in_at'],
                'reserved_check_out_at' => $r['reserved_check_out_at'],
                'advance_payment' => (float) $r['advance_payment'],
                'status' => $r['status'],
                'notes' => $r['notes'],
                'created_by_user_id' => (int) $r['created_by_user_id'],
                'created_at' => $r['created_at'],
                'updated_at' => $r['updated_at'],
                'room_number' => $r['room_number'],
                'room_floor' => $r['room_floor'],
                'category_name' => $r['category_name'] ?? 'General',
                'guest_name' => $r['guest_name'],
                'guest_document_type' => $r['guest_document_type'],
                'guest_document_number' => $r['guest_document_number'],
                'guest_phone' => $r['guest_phone'],
                'guest_email' => $r['guest_email'],
                'tariff_name' => $r['tariff_name'],
                'tariff_duration_hours' => (int) $r['tariff_duration_hours'],
                'tariff_price' => (float) $r['tariff_price'],
            ],
            $rows
        );
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
    public function getMetrics(): array
    {
        $stmt = $this->db->query(
            'SELECT 
                COUNT(*) AS total_reservations,
                SUM(CASE WHEN status IN ("pending", "confirmed") THEN 1 ELSE 0 END) AS active_reservations,
                SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) AS confirmed_count,
                SUM(CASE WHEN status = "checked_in" THEN 1 ELSE 0 END) AS checked_in_count,
                SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) AS cancelled_count,
                COALESCE(SUM(deposit_amount), 0.0) AS total_advance_amount
             FROM reservations'
        );

        /** @var array{total_reservations: int|string|null, active_reservations: int|string|null, pending_count: int|string|null, confirmed_count: int|string|null, checked_in_count: int|string|null, cancelled_count: int|string|null, total_advance_amount: float|string|null}|false $row */
        $row = $stmt !== false ? $stmt->fetch() : false;

        $today_start = date('Y-m-d 00:00:00');
        $today_end = date('Y-m-d 23:59:59');

        $stmt_today = $this->db->prepare(
            'SELECT COUNT(*) FROM reservations
             WHERE status IN ("pending", "confirmed")
               AND start_time BETWEEN :today_start AND :today_end'
        );
        $stmt_today->execute([
            'today_start' => $today_start,
            'today_end' => $today_end,
        ]);
        /** @var int|string $today_count */
        $today_count = $stmt_today->fetchColumn();

        return [
            'total_reservations' => (int) ($row['total_reservations'] ?? 0),
            'active_reservations' => (int) ($row['active_reservations'] ?? 0),
            'pending_count' => (int) ($row['pending_count'] ?? 0),
            'confirmed_count' => (int) ($row['confirmed_count'] ?? 0),
            'checked_in_count' => (int) ($row['checked_in_count'] ?? 0),
            'cancelled_count' => (int) ($row['cancelled_count'] ?? 0),
            'total_advance_amount' => (float) ($row['total_advance_amount'] ?? 0.0),
            'today_checkins_count' => (int) $today_count,
        ];
    }

    public function hasOverlappingReservation(int $room_id, string $check_in_at, string $check_out_at, ?int $exclude_id = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM reservations
                WHERE room_id = :room_id
                  AND status IN ("pending", "confirmed")
                  AND (
                      (:check_in_at < end_time AND :check_out_at > start_time)
                  )';

        $params = [
            'room_id' => $room_id,
            'check_in_at' => $check_in_at,
            'check_out_at' => $check_out_at,
        ];

        if ($exclude_id !== null && $exclude_id > 0) {
            $sql .= ' AND id != :exclude_id';
            $params['exclude_id'] = $exclude_id;
        }

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            /** @var int|string $count */
            $count = $stmt->fetchColumn();
            return (int) $count > 0;
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'no such table: reservations') || str_contains($e->getMessage(), "Table 'reservations' doesn't exist")) {
                return false;
            }
            throw $e;
        }
    }

    public function create(
        int $room_id,
        int $guest_id,
        int $room_tariff_id,
        string $reserved_check_in_at,
        string $reserved_check_out_at,
        float $advance_payment,
        int $created_by_user_id,
        ?string $notes = null
    ): Reservation {
        $status = $advance_payment > 0 ? 'confirmed' : 'pending';

        $stmt = $this->db->prepare(
            'INSERT INTO reservations (room_id, guest_id, room_tariff_id, start_time, end_time, deposit_amount, status, notes, created_by_user_id)
             VALUES (:room_id, :guest_id, :room_tariff_id, :start_time, :end_time, :deposit_amount, :status, :notes, :created_by_user_id)'
        );

        $stmt->execute([
            'room_id' => $room_id,
            'guest_id' => $guest_id,
            'room_tariff_id' => $room_tariff_id,
            'start_time' => $reserved_check_in_at,
            'end_time' => $reserved_check_out_at,
            'deposit_amount' => $advance_payment,
            'status' => $status,
            'notes' => $notes,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var Reservation */
        return $this->findById($id);
    }

    public function updateStatus(int $reservation_id, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE reservations
             SET status = :status
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $reservation_id,
            'status' => $status,
        ]);
    }
}
