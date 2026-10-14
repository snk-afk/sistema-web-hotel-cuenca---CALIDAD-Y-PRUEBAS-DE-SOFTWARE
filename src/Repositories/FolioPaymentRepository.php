<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\FolioPayment;
use PDO;

final class FolioPaymentRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?FolioPayment
    {
        $stmt = $this->db->prepare(
            'SELECT id, folio_id, payment_type, amount, payment_method, reference_number, notes, created_by_user_id, created_at, cashbox_shift_id, reservation_payment_id
             FROM folio_payments
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, folio_id: int|string, payment_type: string, amount: float|string, payment_method: string, reference_number: ?string, notes: ?string, created_by_user_id: int|string, created_at: string, cashbox_shift_id: int|string|null, reservation_payment_id: int|string|null}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new FolioPayment(
            id: (int) $row['id'],
            folio_id: (int) $row['folio_id'],
            payment_type: $row['payment_type'],
            amount: (float) $row['amount'],
            payment_method: $row['payment_method'],
            reference_number: $row['reference_number'],
            notes: $row['notes'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            cashbox_shift_id: $row['cashbox_shift_id'] !== null ? (int) $row['cashbox_shift_id'] : null,
            reservation_payment_id: $row['reservation_payment_id'] !== null ? (int) $row['reservation_payment_id'] : null,
        );
    }

    /**
     * @return array<int, FolioPayment>
     */
    public function findByFolioId(int $folio_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, folio_id, payment_type, amount, payment_method, reference_number, notes, created_by_user_id, created_at, cashbox_shift_id, reservation_payment_id
             FROM folio_payments
             WHERE folio_id = :folio_id
             ORDER BY id ASC'
        );

        $stmt->execute(['folio_id' => $folio_id]);
        /** @var array<int, array{id: int|string, folio_id: int|string, payment_type: string, amount: float|string, payment_method: string, reference_number: ?string, notes: ?string, created_by_user_id: int|string, created_at: string, cashbox_shift_id: int|string|null, reservation_payment_id: int|string|null}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            fn(array $row): FolioPayment => new FolioPayment(
                id: (int) $row['id'],
                folio_id: (int) $row['folio_id'],
                payment_type: $row['payment_type'],
                amount: (float) $row['amount'],
                payment_method: $row['payment_method'],
                reference_number: $row['reference_number'],
                notes: $row['notes'],
                created_by_user_id: (int) $row['created_by_user_id'],
                created_at: $row['created_at'],
                cashbox_shift_id: $row['cashbox_shift_id'] !== null ? (int) $row['cashbox_shift_id'] : null,
                reservation_payment_id: $row['reservation_payment_id'] !== null ? (int) $row['reservation_payment_id'] : null,
            ),
            $rows
        );
    }

    public function findByReservationPaymentId(int $reservation_payment_id): ?FolioPayment
    {
        $stmt = $this->db->prepare(
            'SELECT id, folio_id, payment_type, amount, payment_method, reference_number, notes,
                    created_by_user_id, created_at, cashbox_shift_id, reservation_payment_id
             FROM folio_payments
             WHERE reservation_payment_id = :reservation_payment_id'
        );

        $stmt->execute(['reservation_payment_id' => $reservation_payment_id]);
        /** @var array{id: int|string, folio_id: int|string, payment_type: string, amount: float|string, payment_method: string, reference_number: ?string, notes: ?string, created_by_user_id: int|string, created_at: string, cashbox_shift_id: int|string|null, reservation_payment_id: int|string|null}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return new FolioPayment(
            id: (int) $row['id'],
            folio_id: (int) $row['folio_id'],
            payment_type: $row['payment_type'],
            amount: (float) $row['amount'],
            payment_method: $row['payment_method'],
            reference_number: $row['reference_number'],
            notes: $row['notes'],
            created_by_user_id: (int) $row['created_by_user_id'],
            created_at: $row['created_at'],
            cashbox_shift_id: $row['cashbox_shift_id'] !== null ? (int) $row['cashbox_shift_id'] : null,
            reservation_payment_id: $row['reservation_payment_id'] !== null ? (int) $row['reservation_payment_id'] : null,
        );
    }

    public function create(
        int $folio_id,
        string $payment_type,
        float $amount,
        string $payment_method,
        int $created_by_user_id,
        ?string $reference_number = null,
        ?string $notes = null,
        ?int $cashbox_shift_id = null,
        ?int $reservation_payment_id = null
    ): FolioPayment {
        $db_payment_type = match ($payment_type) {
            'advance' => 'advance',
            'refund' => 'refund',
            default => 'checkout_payment',
        };

        $stmt = $this->db->prepare(
            'INSERT INTO folio_payments (folio_id, payment_type, amount, payment_method, reference_number, notes, created_by_user_id, cashbox_shift_id, reservation_payment_id)
             VALUES (:folio_id, :payment_type, :amount, :payment_method, :reference_number, :notes, :created_by_user_id, :cashbox_shift_id, :reservation_payment_id)'
        );

        $stmt->execute([
            'folio_id' => $folio_id,
            'payment_type' => $db_payment_type,
            'amount' => $amount,
            'payment_method' => $payment_method,
            'reference_number' => $reference_number,
            'notes' => $notes,
            'created_by_user_id' => $created_by_user_id,
            'cashbox_shift_id' => $cashbox_shift_id,
            'reservation_payment_id' => $reservation_payment_id,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var FolioPayment */
        return $this->findById($id);
    }
}
