<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\ReservationCancellation;
use PDO;

/**
 * @phpstan-type ReservationCancellationRow array{
 *     id: int|string,
 *     reservation_id: int|string,
 *     event_type: string,
 *     policy_code: string,
 *     refund_percentage: float|string,
 *     gross_paid_amount: float|string,
 *     refund_amount: float|string,
 *     penalty_amount: float|string,
 *     status: string,
 *     reason: string,
 *     requested_by_user_id: int|string,
 *     approved_by_user_id: int|string|null,
 *     approved_at: string|null,
 *     created_at: string,
 *     updated_at: string
 * }
 */
final class ReservationCancellationRepository
{
    private const string SELECT_COLUMNS = 'id, reservation_id, event_type, policy_code, '
        . 'refund_percentage, gross_paid_amount, refund_amount, penalty_amount, status, reason, '
        . 'requested_by_user_id, approved_by_user_id, approved_at, created_at, updated_at';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?ReservationCancellation
    {
        $statement = $this->db->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM reservation_cancellations WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        /** @var ReservationCancellationRow|false $row */
        $row = $statement->fetch();

        return $row === false ? null : $this->mapRow($row);
    }

    public function lockById(int $id): ?ReservationCancellation
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS
            . ' FROM reservation_cancellations WHERE id = :id';
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['id' => $id]);

        /** @var ReservationCancellationRow|false $row */
        $row = $statement->fetch();

        return $row === false ? null : $this->mapRow($row);
    }

    public function findByReservationId(int $reservation_id): ?ReservationCancellation
    {
        $statement = $this->db->prepare(
            'SELECT ' . self::SELECT_COLUMNS
            . ' FROM reservation_cancellations WHERE reservation_id = :reservation_id'
        );
        $statement->execute(['reservation_id' => $reservation_id]);

        /** @var ReservationCancellationRow|false $row */
        $row = $statement->fetch();

        return $row === false ? null : $this->mapRow($row);
    }

    public function lockByReservationId(int $reservation_id): ?ReservationCancellation
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS
            . ' FROM reservation_cancellations WHERE reservation_id = :reservation_id';
        if ($this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['reservation_id' => $reservation_id]);

        /** @var ReservationCancellationRow|false $row */
        $row = $statement->fetch();

        return $row === false ? null : $this->mapRow($row);
    }

    public function create(
        int $reservation_id,
        string $event_type,
        string $policy_code,
        float $refund_percentage,
        float $gross_paid_amount,
        float $refund_amount,
        float $penalty_amount,
        string $status,
        string $reason,
        int $requested_by_user_id
    ): ReservationCancellation {
        $statement = $this->db->prepare(
            'INSERT INTO reservation_cancellations '
            . '(reservation_id, event_type, policy_code, refund_percentage, gross_paid_amount, '
            . 'refund_amount, penalty_amount, status, reason, requested_by_user_id) '
            . 'VALUES (:reservation_id, :event_type, :policy_code, :refund_percentage, :gross_paid_amount, '
            . ':refund_amount, :penalty_amount, :status, :reason, :requested_by_user_id)'
        );
        $statement->execute([
            'reservation_id' => $reservation_id,
            'event_type' => $event_type,
            'policy_code' => $policy_code,
            'refund_percentage' => $refund_percentage,
            'gross_paid_amount' => $gross_paid_amount,
            'refund_amount' => $refund_amount,
            'penalty_amount' => $penalty_amount,
            'status' => $status,
            'reason' => $reason,
            'requested_by_user_id' => $requested_by_user_id,
        ]);

        $created = $this->findById((int) $this->db->lastInsertId());
        if ($created === null) {
            throw new \RuntimeException('No se pudo recuperar la liquidación recién creada.');
        }

        return $created;
    }

    public function markCompleted(int $id, int $approved_by_user_id, string $approved_at): int
    {
        $statement = $this->db->prepare(
            "UPDATE reservation_cancellations
             SET status = 'completed', approved_by_user_id = :approved_by_user_id,
                 approved_at = :approved_at, updated_at = CURRENT_TIMESTAMP
             WHERE id = :id AND status = 'pending_refund'"
        );
        $statement->execute([
            'id' => $id,
            'approved_by_user_id' => $approved_by_user_id,
            'approved_at' => $approved_at,
        ]);

        return $statement->rowCount();
    }

    /**
     * @param ReservationCancellationRow $row
     */
    private function mapRow(array $row): ReservationCancellation
    {
        return new ReservationCancellation(
            id: (int) $row['id'],
            reservation_id: (int) $row['reservation_id'],
            event_type: $row['event_type'],
            policy_code: $row['policy_code'],
            refund_percentage: (float) $row['refund_percentage'],
            gross_paid_amount: (float) $row['gross_paid_amount'],
            refund_amount: (float) $row['refund_amount'],
            penalty_amount: (float) $row['penalty_amount'],
            status: $row['status'],
            reason: $row['reason'],
            requested_by_user_id: (int) $row['requested_by_user_id'],
            approved_by_user_id: $row['approved_by_user_id'] !== null
                ? (int) $row['approved_by_user_id']
                : null,
            approved_at: $row['approved_at'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
        );
    }
}
