<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Database\Migrator;
use PDO;
use PHPUnit\Framework\TestCase;

final class SqliteMigrationCompatibilityTest extends TestCase
{
    public function testRelationalIntegrityMigrationKeepsSqliteRunnerCompatible(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $migrator = new Migrator($pdo, __DIR__ . '/../../../database/migrations');
        $executed = $migrator->migrate();

        $this->assertContains('2026_09_07_000001_harden_relational_integrity.php', $executed);

        $table_statement = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'folio_charges'"
        );
        $this->assertNotFalse($table_statement);
        $this->assertSame('folio_charges', $table_statement->fetchColumn());

        $columns = $pdo->query('PRAGMA table_info(folio_charges)');
        $this->assertNotFalse($columns);

        /** @var array<int, array<int, mixed>> $column_rows */
        $column_rows = $columns->fetchAll(PDO::FETCH_NUM);
        $charge_type = array_find(
            $column_rows,
            static fn(array $column): bool => $column[1] === 'charge_type'
        );

        $this->assertIsArray($charge_type);
        $this->assertSame('TEXT', strtoupper((string) $charge_type[2]));
    }

    public function testFinancialPaymentMigrationKeepsSqliteRunnerCompatible(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $migrator = new \App\Database\Migrator($pdo, __DIR__ . '/../../../database/migrations');
        $executed = $migrator->migrate();

        $this->assertContains('2026_09_07_000002_create_financial_payment_links.php', $executed);

        $table_statement = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'reservation_payments'"
        );
        $this->assertNotFalse($table_statement);
        $this->assertSame('reservation_payments', $table_statement->fetchColumn());
    }

    public function testReservationSettlementMigrationKeepsSqliteRunnerCompatible(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $migrator = new Migrator($pdo, __DIR__ . '/../../../database/migrations');
        $executed = $migrator->migrate();

        $this->assertContains('2026_09_07_000003_create_reservation_settlements.php', $executed);

        foreach (['reservation_cancellations', 'reservation_refunds'] as $table_name) {
            $table_statement = $pdo->prepare(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table_name"
            );
            $table_statement->execute(['table_name' => $table_name]);
            $this->assertSame($table_name, $table_statement->fetchColumn());
        }

        $reservation_columns = $pdo->query('PRAGMA table_info(reservations)');
        $this->assertNotFalse($reservation_columns);
        /** @var array<int, array<int, mixed>> $reservation_column_rows */
        $reservation_column_rows = $reservation_columns->fetchAll(PDO::FETCH_NUM);
        $this->assertTrue(array_any(
            $reservation_column_rows,
            static fn(array $column): bool => $column[1] === 'status'
        ));
    }

    public function testProductionReadinessMigrationKeepsSqliteRunnerCompatible(): void
    {
        $pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $migrator = new Migrator($pdo, __DIR__ . '/../../../database/migrations');
        $executed = $migrator->migrate();

        $this->assertContains('2026_09_07_000004_create_production_readiness_tables.php', $executed);

        foreach (['auth_login_attempts', 'invoice_transmissions'] as $table_name) {
            $statement = $pdo->prepare(
                "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :table_name"
            );
            $statement->execute(['table_name' => $table_name]);
            $this->assertSame($table_name, $statement->fetchColumn());
        }

        $store_columns = $pdo->query('PRAGMA table_info(store_sales)');
        $store_column_rows = $store_columns !== false ? $store_columns->fetchAll(PDO::FETCH_ASSOC) : [];
        $this->assertTrue(array_any(
            $store_column_rows,
            static fn(array $column): bool => ($column['name'] ?? null) === 'idempotency_key'
        ));
        $this->assertContains('2026_09_07_000005_add_store_sale_idempotency.php', $executed);
    }
}
