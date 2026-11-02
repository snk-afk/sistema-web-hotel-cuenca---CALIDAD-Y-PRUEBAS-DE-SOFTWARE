<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Tests\Support\MySqlTestCase;

final class SchemaContractTest extends MySqlTestCase
{
    /**
     * @var array<int, array{child_table: string, child_column: string, parent_table: string, parent_column: string, delete_rule: string}>
     */
    private const APPROVED_FOREIGN_KEYS = [
        ['child_table' => 'stays', 'child_column' => 'guest_id', 'parent_table' => 'guests', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'stays', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'stay_extensions', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservations', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'room_transfers', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'cashbox_shifts', 'child_column' => 'user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'cash_movements', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'folio_charges', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'folio_payments', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'store_sales', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'inventory_movements', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'expenses', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'other_incomes', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'housekeeping_tasks', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'housekeeping_tasks', 'child_column' => 'assigned_to_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'SET NULL'],
        ['child_table' => 'invoices', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_payments', 'child_column' => 'reservation_id', 'parent_table' => 'reservations', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_payments', 'child_column' => 'cashbox_shift_id', 'parent_table' => 'cashbox_shifts', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_payments', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'folio_payments', 'child_column' => 'cashbox_shift_id', 'parent_table' => 'cashbox_shifts', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'folio_payments', 'child_column' => 'reservation_payment_id', 'parent_table' => 'reservation_payments', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_cancellations', 'child_column' => 'reservation_id', 'parent_table' => 'reservations', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_cancellations', 'child_column' => 'requested_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_cancellations', 'child_column' => 'approved_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_refunds', 'child_column' => 'cancellation_id', 'parent_table' => 'reservation_cancellations', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_refunds', 'child_column' => 'reservation_payment_id', 'parent_table' => 'reservation_payments', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_refunds', 'child_column' => 'cashbox_shift_id', 'parent_table' => 'cashbox_shifts', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
        ['child_table' => 'reservation_refunds', 'child_column' => 'executed_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'delete_rule' => 'RESTRICT'],
    ];

    public function testApprovedForeignKeysExistWithExpectedActions(): void
    {
        $statement = $this->pdo()->query(
            'SELECT kcu.TABLE_NAME AS child_table, '
            . 'kcu.COLUMN_NAME AS child_column, '
            . 'kcu.REFERENCED_TABLE_NAME AS parent_table, '
            . 'kcu.REFERENCED_COLUMN_NAME AS parent_column, '
            . 'rc.DELETE_RULE AS delete_rule '
            . 'FROM information_schema.KEY_COLUMN_USAGE kcu '
            . 'INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc '
            . 'ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA '
            . 'AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME '
            . 'AND rc.TABLE_NAME = kcu.TABLE_NAME '
            . 'WHERE kcu.CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND kcu.REFERENCED_TABLE_NAME IS NOT NULL'
        );
        $rows = $statement->fetchAll();

        $actual = [];
        foreach ($rows as $row) {
            $key = $row['child_table'] . '.' . $row['child_column'];
            $actual[$key] = $row;
        }

        foreach (self::APPROVED_FOREIGN_KEYS as $foreign_key) {
            $key = $foreign_key['child_table'] . '.' . $foreign_key['child_column'];

            self::assertArrayHasKey($key, $actual);
            self::assertSame($foreign_key['parent_table'], $actual[$key]['parent_table']);
            self::assertSame($foreign_key['parent_column'], $actual[$key]['parent_column']);
            self::assertSame($foreign_key['delete_rule'], $actual[$key]['delete_rule']);
        }
    }

    public function testPolymorphicColumnsDoNotHaveFalseForeignKeys(): void
    {
        $statement = $this->pdo()->query(
            'SELECT kcu.TABLE_NAME AS child_table, kcu.COLUMN_NAME AS child_column '
            . 'FROM information_schema.KEY_COLUMN_USAGE kcu '
            . 'WHERE kcu.CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND kcu.REFERENCED_TABLE_NAME IS NOT NULL '
            . 'AND ((kcu.TABLE_NAME = \'audit_logs\' AND kcu.COLUMN_NAME = \'entity_id\') '
            . 'OR (kcu.TABLE_NAME = \'cash_movements\' AND kcu.COLUMN_NAME = \'reference_id\'))'
        );

        self::assertSame([], $statement->fetchAll());
    }

    public function testAllTablesUseExpectedStorageContract(): void
    {
        $statement = $this->pdo()->query(
            'SELECT TABLE_NAME, ENGINE, TABLE_COLLATION '
            . 'FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\''
        );
        $tables = $statement->fetchAll();

        self::assertNotEmpty($tables);

        foreach ($tables as $table) {
            self::assertSame('InnoDB', $table['ENGINE'], $table['TABLE_NAME']);
            self::assertSame('utf8mb4_unicode_ci', $table['TABLE_COLLATION'], $table['TABLE_NAME']);
        }
    }

    public function testSessionSqlModeIsStrict(): void
    {
        $statement = $this->pdo()->query('SELECT @@SESSION.sql_mode');
        $sql_mode = (string) $statement->fetchColumn();

        self::assertStringContainsString('STRICT_TRANS_TABLES', $sql_mode);
        self::assertStringContainsString('NO_ENGINE_SUBSTITUTION', $sql_mode);
    }

    public function testFolioChargeEnumContainsCanonicalValues(): void
    {
        $statement = $this->pdo()->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => 'folio_charges',
            'column_name' => 'charge_type',
        ]);

        self::assertSame(
            "enum('accommodation','store_item','service','extension','other','penalty','adjustment')",
            strtolower((string) $statement->fetchColumn()),
        );
    }

    public function testEveryApprovedForeignKeyColumnHasAnIndex(): void
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(DISTINCT INDEX_NAME) FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );

        foreach (self::APPROVED_FOREIGN_KEYS as $foreign_key) {
            $statement->execute([
                'table_name' => $foreign_key['child_table'],
                'column_name' => $foreign_key['child_column'],
            ]);

            self::assertGreaterThan(
                0,
                (int) $statement->fetchColumn(),
                $foreign_key['child_table'] . '.' . $foreign_key['child_column'],
            );
        }
    }

    public function testFinancialPaymentTablesAndColumnsExist(): void
    {
        $table_statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );
        $table_statement->execute(['table_name' => 'reservation_payments']);
        self::assertSame(1, (int) $table_statement->fetchColumn());

        $columns_statement = $this->pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'ORDER BY ORDINAL_POSITION'
        );

        $expected_columns = [
            'id',
            'reservation_id',
            'cashbox_shift_id',
            'amount',
            'payment_method',
            'reference_number',
            'notes',
            'created_by_user_id',
            'created_at',
        ];

        $columns_statement->execute(['table_name' => 'reservation_payments']);
        self::assertSame($expected_columns, $columns_statement->fetchAll(\PDO::FETCH_COLUMN));

        foreach ([
            'cashbox_shift_id',
            'reservation_payment_id',
        ] as $column_name) {
            $columns_statement->execute(['table_name' => 'folio_payments']);
            self::assertContains($column_name, $columns_statement->fetchAll(\PDO::FETCH_COLUMN));
        }

        foreach ([
            'reference_type',
        ] as $column_name) {
            $columns_statement->execute(['table_name' => 'cash_movements']);
            self::assertContains($column_name, $columns_statement->fetchAll(\PDO::FETCH_COLUMN));
        }

        foreach ([
            'expected_yape',
            'expected_plin',
            'expected_card',
            'expected_transfer',
            'real_yape_declared',
            'real_plin_declared',
            'real_card_declared',
            'real_transfer_declared',
            'difference_yape',
            'difference_plin',
            'difference_card',
            'difference_transfer',
        ] as $column_name) {
            $columns_statement->execute(['table_name' => 'cashbox_shifts']);
            self::assertContains($column_name, $columns_statement->fetchAll(\PDO::FETCH_COLUMN));
        }
    }

    public function testProductionReadinessTablesAndColumnsExist(): void
    {
        $table_statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );

        foreach (['auth_login_attempts', 'invoice_transmissions'] as $table_name) {
            $table_statement->execute(['table_name' => $table_name]);
            self::assertSame(1, (int) $table_statement->fetchColumn(), $table_name);
        }

        $columns_statement = $this->pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'ORDER BY ORDINAL_POSITION'
        );

        $columns_statement->execute(['table_name' => 'auth_login_attempts']);
        self::assertSame(
            ['id', 'username_hash', 'source_hash', 'attempted_at', 'was_successful', 'created_at'],
            array_column($columns_statement->fetchAll(), 'COLUMN_NAME'),
        );

        $columns_statement->execute(['table_name' => 'invoice_transmissions']);
        self::assertSame(
            ['id', 'invoice_id', 'provider', 'idempotency_key', 'status', 'provider_code', 'provider_message', 'attempts', 'last_attempt_at', 'created_at', 'updated_at'],
            array_column($columns_statement->fetchAll(), 'COLUMN_NAME'),
        );

        $columns_statement->execute(['table_name' => 'store_sales']);
        self::assertContains(
            'idempotency_key',
            array_column($columns_statement->fetchAll(), 'COLUMN_NAME'),
        );
    }

    public function testReservationSettlementTablesAndColumnsExist(): void
    {
        $table_statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name'
        );

        foreach (['reservation_cancellations', 'reservation_refunds'] as $table_name) {
            $table_statement->execute(['table_name' => $table_name]);
            self::assertSame(1, (int) $table_statement->fetchColumn(), $table_name);
        }

        $columns_statement = $this->pdo()->prepare(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name '
            . 'ORDER BY ORDINAL_POSITION'
        );

        $columns_statement->execute(['table_name' => 'reservation_cancellations']);
        self::assertSame([
            'id', 'reservation_id', 'event_type', 'policy_code', 'refund_percentage',
            'gross_paid_amount', 'refund_amount', 'penalty_amount', 'status', 'reason',
            'requested_by_user_id', 'approved_by_user_id', 'approved_at', 'created_at', 'updated_at',
        ], $columns_statement->fetchAll(\PDO::FETCH_COLUMN));

        $columns_statement->execute(['table_name' => 'reservation_refunds']);
        self::assertSame([
            'id', 'cancellation_id', 'reservation_payment_id', 'cashbox_shift_id',
            'amount', 'payment_method', 'reference_number', 'executed_by_user_id',
            'reason', 'created_at',
        ], $columns_statement->fetchAll(\PDO::FETCH_COLUMN));

        $status_statement = $this->pdo()->prepare(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $status_statement->execute([
            'table_name' => 'reservations',
            'column_name' => 'status',
        ]);
        self::assertSame(
            "enum('pending','confirmed','checked_in','cancelled','no_show')",
            strtolower((string) $status_statement->fetchColumn()),
        );
    }
}
