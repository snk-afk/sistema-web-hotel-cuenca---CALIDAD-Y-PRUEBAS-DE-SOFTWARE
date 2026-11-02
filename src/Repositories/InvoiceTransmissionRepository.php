<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

final class InvoiceTransmissionRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array{id: int, invoice_id: int, provider: string, idempotency_key: string, status: string, provider_code: ?string, provider_message: ?string, attempts: int, last_attempt_at: ?string, created_at: string, updated_at: string}
     */
    public function createPending(int $invoice_id, string $provider, string $idempotency_key): array
    {
        $statement = $this->db->prepare(
            'INSERT INTO invoice_transmissions '
            . '(invoice_id, provider, idempotency_key, status) '
            . 'VALUES (:invoice_id, :provider, :idempotency_key, :status)'
        );
        $statement->execute([
            'invoice_id' => $invoice_id,
            'provider' => $provider,
            'idempotency_key' => $idempotency_key,
            'status' => 'pending',
        ]);

        $transmission = $this->findByInvoiceId($invoice_id);
        if ($transmission === null) {
            throw new InvalidArgumentException('No se pudo crear la transmisión del comprobante.');
        }

        return $transmission;
    }

    /**
     * @return array{id: int, invoice_id: int, provider: string, idempotency_key: string, status: string, provider_code: ?string, provider_message: ?string, attempts: int, last_attempt_at: ?string, created_at: string, updated_at: string}|null
     */
    public function findByInvoiceId(int $invoice_id): ?array
    {
        $statement = $this->db->prepare($this->selectSql() . ' WHERE invoice_id = :invoice_id');
        $statement->execute(['invoice_id' => $invoice_id]);

        return $this->fetchOne($statement);
    }

    /**
     * @return array{id: int, invoice_id: int, provider: string, idempotency_key: string, status: string, provider_code: ?string, provider_message: ?string, attempts: int, last_attempt_at: ?string, created_at: string, updated_at: string}|null
     */
    public function findByIdempotencyKey(string $idempotency_key): ?array
    {
        $statement = $this->db->prepare($this->selectSql() . ' WHERE idempotency_key = :idempotency_key');
        $statement->execute(['idempotency_key' => $idempotency_key]);

        return $this->fetchOne($statement);
    }

    public function recordAttempt(
        int $invoice_id,
        string $status,
        ?string $provider_code,
        ?string $provider_message,
        DateTimeImmutable $attempted_at
    ): void {
        if (!in_array($status, ['pending', 'sent', 'accepted', 'rejected', 'failed'], true)) {
            throw new InvalidArgumentException('Estado de transmisión inválido.');
        }

        $statement = $this->db->prepare(
            'UPDATE invoice_transmissions '
            . 'SET status = :status, provider_code = :provider_code, '
            . 'provider_message = :provider_message, attempts = attempts + 1, '
            . 'last_attempt_at = :last_attempt_at, updated_at = CURRENT_TIMESTAMP '
            . 'WHERE invoice_id = :invoice_id'
        );
        $statement->execute([
            'status' => $status,
            'provider_code' => $provider_code,
            'provider_message' => $provider_message,
            'last_attempt_at' => $attempted_at->format('Y-m-d H:i:s'),
            'invoice_id' => $invoice_id,
        ]);
    }

    private function selectSql(): string
    {
        return 'SELECT id, invoice_id, provider, idempotency_key, status, provider_code, '
            . 'provider_message, attempts, last_attempt_at, created_at, updated_at '
            . 'FROM invoice_transmissions';
    }

    /**
     * @param \PDOStatement $statement
     * @return array{id: int, invoice_id: int, provider: string, idempotency_key: string, status: string, provider_code: ?string, provider_message: ?string, attempts: int, last_attempt_at: ?string, created_at: string, updated_at: string}|null
     */
    private function fetchOne(\PDOStatement $statement): ?array
    {
        /** @var array{id: int|string, invoice_id: int|string, provider: string, idempotency_key: string, status: string, provider_code: ?string, provider_message: ?string, attempts: int|string, last_attempt_at: ?string, created_at: string, updated_at: string}|false $row */
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'invoice_id' => (int) $row['invoice_id'],
            'provider' => $row['provider'],
            'idempotency_key' => $row['idempotency_key'],
            'status' => $row['status'],
            'provider_code' => $row['provider_code'],
            'provider_message' => $row['provider_message'],
            'attempts' => (int) $row['attempts'],
            'last_attempt_at' => $row['last_attempt_at'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }
}
