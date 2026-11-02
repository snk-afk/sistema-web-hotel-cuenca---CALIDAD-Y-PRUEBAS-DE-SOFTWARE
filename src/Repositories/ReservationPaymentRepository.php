<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\ReservationPayment;
use PDO;

final class ReservationPaymentRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?ReservationPayment
    {
        $statement = $this->db->prepare(
            'SELECT id, reservation_id, cashbox_shift_id, amount, payment_method, '
            . 'reference_number, notes, created_by_user_id, created_at '
            . 'FROM reservation_payments WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        /** @var array{id: int|string, reservation_id: int|string, cashbox_shift_id: int|string, amount: float|string, payment_method: string, reference_number: ?string, notes: ?string, created_by_user_id: int|string, created_at: string}|false $row */
        $row = $statement->fetch();

        return $row === false ? null : $this->mapRow($row);
    }

    /**
     * @return array<int, ReservationPayment>
     */
    public function findByReservationId(int $reservation_id): array
    {
        $statement = $this->db->prepare(
            'SELECT id, reservation_id, cashbox_shift_id, amount, payment_method, '
            . 'reference_number, notes, created_by_user_id, created_at '
            . 'FROM reservation_payments '
            . 'WHERE reservation_id = :reservation_id ORDER BY id ASC'
        );
        $statement->execute(['reservation_id' => $reservation_id]);

        /** @var array<int, array{id: int|string, reservation_id: int|string, cashbox_shift_id: int|string, amount: float|string, payment_method: string, reference_number: ?string, notes: ?string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $statement->fetchAll();

        return array_map(fn(array $row): ReservationPayment => $this->mapRow($row), $rows);
    }

    /**
     * @return array<int, ReservationPayment>
     */
    public function findByReservationIdForUpdate(int $reservation_id): array
    {
        $sql = 'SELECT id, reservation_id, cashbox_shift_id, amount, payment_method, '
            . 'reference_number, notes, created_by_user_id, created_at '
            . 'FROM reservation_payments WHERE reservation_id = :reservation_id ORDER BY id ASC';
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['reservation_id' => $reservation_id]);

        /** @var array<int, array{id: int|string, reservation_id: int|string, cashbox_shift_id: int|string, amount: float|string, payment_method: string, reference_number: ?string, notes: ?string, created_by_user_id: int|string, created_at: string}> $rows */
        $rows = $statement->fetchAll();

        return array_map(fn(array $row): ReservationPayment => $this->mapRow($row), $rows);
    }

    public function hasAppliedFolioPayment(int $reservation_payment_id): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM folio_payments '
            . 'WHERE reservation_payment_id = :reservation_payment_id LIMIT 1'
        );
        $statement->execute(['reservation_payment_id' => $reservation_payment_id]);

        return $statement->fetchColumn() !== false;
    }

    public function create(
        int $reservation_id,
        int $cashbox_shift_id,
        float $amount,
        string $payment_method,
        int $created_by_user_id,
        ?string $reference_number = null,
        ?string $notes = null
    ): ReservationPayment {
        $statement = $this->db->prepare(
            'INSERT INTO reservation_payments '
            . '(reservation_id, cashbox_shift_id, amount, payment_method, reference_number, notes, created_by_user_id) '
            . 'VALUES (:reservation_id, :cashbox_shift_id, :amount, :payment_method, :reference_number, :notes, :created_by_user_id)'
        );
        $statement->execute([
            'reservation_id' => $reservation_id,
            'cashbox_shift_id' => $cashbox_shift_id,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'reference_number' => $reference_number,
            'notes' => $notes,
            'created_by_user_id' => $created_by_user_id,
        ]);

        $payment = $this->findById((int) $this->db->lastInsertId());
        if ($payment === null) {
            throw new \RuntimeException('No se pudo recuperar el adelanto de reserva recién creado.');
        }

        return $payment;
    }

    /**
     * @param array{id: int|string, reservation_id: int|string, cashbox_shift_id: int|string, amount: float|string, payment_method: string, reference_number: ?string, notes: ?string, created_by_user_id: int|string, created_at: string} $row
     */
    private function mapRow(array $row): ReservationPayment
    {
        return new ReservationPayment(
            id: (int) $row['id'],
            reservation_id: (int) $row['reservation_id'],
            cashbox_shift_id: (int) $row['cashbox_shift_id'],
            amount: (float) $row['amount'],
            payment_method: $row['payment_method'],
            reference_number: $row['reference_number'],
            notes: $row['notes'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
        );
    }
}
