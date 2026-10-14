<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Folio;
use PDO;

final class FolioRepository
{
    private PDO $db;
    private FolioChargeRepository $charge_repository;
    private FolioPaymentRepository $payment_repository;

    public function __construct(
        ?PDO $db = null,
        ?FolioChargeRepository $charge_repository = null,
        ?FolioPaymentRepository $payment_repository = null
    ) {
        $this->db = $db ?? Database::connection();
        $this->charge_repository = $charge_repository ?? new FolioChargeRepository($this->db);
        $this->payment_repository = $payment_repository ?? new FolioPaymentRepository($this->db);
    }

    public function findById(int $id): ?Folio
    {
        $stmt = $this->db->prepare(
            'SELECT id, stay_id, status, created_at, updated_at
             FROM folios
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, stay_id: int|string, status: string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $charges = $this->charge_repository->findByFolioId((int) $row['id']);
        $payments = $this->payment_repository->findByFolioId((int) $row['id']);

        $total_charges = array_reduce(
            $charges,
            fn(float $acc, $c): float => $acc + $c->total_amount,
            0.0
        );

        $total_payments = array_reduce(
            $payments,
            fn(float $acc, $p): float => $acc + $p->amount,
            0.0
        );

        $pending_balance = $total_charges - $total_payments;

        return new Folio(
            id: (int) $row['id'],
            stay_id: (int) $row['stay_id'],
            status: $row['status'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            charges: $charges,
            payments: $payments,
            total_charges: $total_charges,
            total_payments: $total_payments,
            pending_balance: $pending_balance,
        );
    }

    public function findByStayId(int $stay_id): ?Folio
    {
        $stmt = $this->db->prepare(
            'SELECT id, stay_id, status, created_at, updated_at
             FROM folios
             WHERE stay_id = :stay_id'
        );

        $stmt->execute(['stay_id' => $stay_id]);
        /** @var array{id: int|string, stay_id: int|string, status: string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return $this->findById((int) $row['id']);
    }

    public function create(int $stay_id): Folio
    {
        $stmt = $this->db->prepare(
            'INSERT INTO folios (stay_id, status)
             VALUES (:stay_id, "open")'
        );

        $stmt->execute(['stay_id' => $stay_id]);
        $id = (int) $this->db->lastInsertId();

        /** @var Folio */
        return $this->findById($id);
    }

    public function updateStatus(int $folio_id, string $status): void
    {
        $stmt = $this->db->prepare(
            'UPDATE folios
             SET status = :status
             WHERE id = :id'
        );

        $stmt->execute(['id' => $folio_id, 'status' => $status]);
    }

    public function closeFolio(int $folio_id): void
    {
        $this->updateStatus($folio_id, 'closed');
    }
}
