<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\ReservationRefund;
use PDO;

/**
 * @phpstan-type ReservationRefundRow array{
 *     id: int|string,
 *     cancellation_id: int|string,
 *     reservation_payment_id: int|string,
 *     cashbox_shift_id: int|string,
 *     amount: float|string,
 *     payment_method: string,
 *     reference_number: string|null,
 *     executed_by_user_id: int|string,
 *     reason: string,
 *     created_at: string
 * }
 */
final class ReservationRefundRepository
{
    private const string SELECT_COLUMNS = 'id, cancellation_id, reservation_payment_id, cashbox_shift_id, '
        . 'amount, payment_method, reference_number, executed_by_user_id, reason, created_at';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?ReservationRefund
    {
        $statement = $this->db->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM reservation_refunds WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        /** @var ReservationRefundRow|false $row */
        $row = $statement->fetch();

        return $row === false ? null : $this->mapRow($row);
    }

    /**
     * @return list<ReservationRefund>
     */
    public function findByCancellationId(int $cancellation_id): array
    {
        $statement = $this->db->prepare(
            'SELECT ' . self::SELECT_COLUMNS
            . ' FROM reservation_refunds WHERE cancellation_id = :cancellation_id ORDER BY id ASC'
        );
        $statement->execute(['cancellation_id' => $cancellation_id]);

        /** @var list<ReservationRefundRow> $rows */
        $rows = $statement->fetchAll();

        return array_map(fn(array $row): ReservationRefund => $this->mapRow($row), $rows);
    }

    public function findByReservationPaymentId(int $reservation_payment_id): ?ReservationRefund
    {
        $statement = $this->db->prepare(
            'SELECT ' . self::SELECT_COLUMNS
            . ' FROM reservation_refunds WHERE reservation_payment_id = :reservation_payment_id'
        );
        $statement->execute(['reservation_payment_id' => $reservation_payment_id]);

        /** @var ReservationRefundRow|false $row */
        $row = $statement->fetch();

        return $row === false ? null : $this->mapRow($row);
    }

    public function create(
        int $cancellation_id,
        int $reservation_payment_id,
        int $cashbox_shift_id,
        float $amount,
        string $payment_method,
        ?string $reference_number,
        int $executed_by_user_id,
        string $reason
    ): ReservationRefund {
        $statement = $this->db->prepare(
            'INSERT INTO reservation_refunds '
            . '(cancellation_id, reservation_payment_id, cashbox_shift_id, amount, payment_method, '
            . 'reference_number, executed_by_user_id, reason) '
            . 'VALUES (:cancellation_id, :reservation_payment_id, :cashbox_shift_id, :amount, :payment_method, '
            . ':reference_number, :executed_by_user_id, :reason)'
        );
        $statement->execute([
            'cancellation_id' => $cancellation_id,
            'reservation_payment_id' => $reservation_payment_id,
            'cashbox_shift_id' => $cashbox_shift_id,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'reference_number' => $reference_number,
            'executed_by_user_id' => $executed_by_user_id,
            'reason' => $reason,
        ]);

        $created = $this->findById((int) $this->db->lastInsertId());
        if ($created === null) {
            throw new \RuntimeException('No se pudo recuperar la devolución recién creada.');
        }

        return $created;
    }

    /**
     * @param ReservationRefundRow $row
     */
    private function mapRow(array $row): ReservationRefund
    {
        return new ReservationRefund(
            id: (int) $row['id'],
            cancellation_id: (int) $row['cancellation_id'],
            reservation_payment_id: (int) $row['reservation_payment_id'],
            cashbox_shift_id: (int) $row['cashbox_shift_id'],
            amount: (float) $row['amount'],
            payment_method: $row['payment_method'],
            reference_number: $row['reference_number'],
            executed_by_user_id: (int) $row['executed_by_user_id'],
            reason: $row['reason'],
            created_at: $row['created_at'],
        );
    }
}
