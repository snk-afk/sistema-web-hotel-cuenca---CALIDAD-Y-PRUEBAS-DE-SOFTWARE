<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\InvoiceTransmissionRepository;
use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;

final class InvoiceTransmissionRepositoryTest extends TestCase
{
    private PDO $pdo;
    private InvoiceTransmissionRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->pdo->exec(
            'CREATE TABLE invoice_transmissions ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'invoice_id INTEGER NOT NULL UNIQUE, '
            . 'provider TEXT NOT NULL, '
            . 'idempotency_key TEXT NOT NULL UNIQUE, '
            . 'status TEXT NOT NULL, '
            . 'provider_code TEXT, '
            . 'provider_message TEXT, '
            . 'attempts INTEGER NOT NULL DEFAULT 0, '
            . 'last_attempt_at TEXT, '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ')'
        );
        $this->repository = new InvoiceTransmissionRepository($this->pdo);
    }

    public function testCreatesOnePendingTransmissionAndRecordsAttempts(): void
    {
        $pending = $this->repository->createPending(7, 'sunat', 'idempotency-key');

        self::assertSame(7, $pending['invoice_id']);
        self::assertSame('pending', $pending['status']);
        self::assertSame(0, $pending['attempts']);

        $this->repository->recordAttempt(
            invoice_id: 7,
            status: 'accepted',
            provider_code: '0',
            provider_message: 'Aceptado',
            attempted_at: new DateTimeImmutable('2026-09-07 12:00:00'),
        );

        $stored = $this->repository->findByInvoiceId(7);
        self::assertNotNull($stored);
        self::assertSame('accepted', $stored['status']);
        self::assertSame(1, $stored['attempts']);
        self::assertSame('0', $stored['provider_code']);
    }

    public function testFindsExistingTransmissionByIdempotencyKey(): void
    {
        $this->repository->createPending(7, 'sunat', 'idempotency-key');

        $stored = $this->repository->findByIdempotencyKey('idempotency-key');

        self::assertNotNull($stored);
        self::assertSame(7, $stored['invoice_id']);
    }
}
