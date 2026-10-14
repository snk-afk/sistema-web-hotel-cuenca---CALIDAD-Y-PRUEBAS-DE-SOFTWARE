<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->upSqlite($db);
            return;
        }

        $this->upMysql($db);
    }

    public function down(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            foreach ([
                'uq_folio_payments_reservation_payment_id',
                'idx_folio_payments_cashbox_shift_id',
                'idx_cash_movements_reference',
                'idx_reservation_payments_reservation_id',
                'idx_reservation_payments_cashbox_shift_id',
                'idx_reservation_payments_created_by_user_id',
            ] as $index) {
                $db->exec('DROP INDEX IF EXISTS "' . $index . '"');
            }

            foreach ([
                ['table' => 'folio_payments', 'column' => 'reservation_payment_id'],
                ['table' => 'folio_payments', 'column' => 'cashbox_shift_id'],
                ['table' => 'cash_movements', 'column' => 'reference_type'],
                ['table' => 'cashbox_shifts', 'column' => 'expected_yape'],
                ['table' => 'cashbox_shifts', 'column' => 'expected_plin'],
                ['table' => 'cashbox_shifts', 'column' => 'expected_card'],
                ['table' => 'cashbox_shifts', 'column' => 'expected_transfer'],
                ['table' => 'cashbox_shifts', 'column' => 'real_yape_declared'],
                ['table' => 'cashbox_shifts', 'column' => 'real_plin_declared'],
                ['table' => 'cashbox_shifts', 'column' => 'real_card_declared'],
                ['table' => 'cashbox_shifts', 'column' => 'real_transfer_declared'],
                ['table' => 'cashbox_shifts', 'column' => 'difference_yape'],
                ['table' => 'cashbox_shifts', 'column' => 'difference_plin'],
                ['table' => 'cashbox_shifts', 'column' => 'difference_card'],
                ['table' => 'cashbox_shifts', 'column' => 'difference_transfer'],
            ] as $column) {
                if ($this->sqliteColumnExists($db, $column['table'], $column['column'])) {
                    $db->exec(
                        'ALTER TABLE "' . $column['table'] . '" DROP COLUMN "' . $column['column'] . '"'
                    );
                }
            }

            $db->exec('DROP TABLE IF EXISTS reservation_payments');
            return;
        }

        foreach ([
            ['table' => 'folio_payments', 'constraint' => 'fk_folio_payments_reservation_payment_id'],
            ['table' => 'folio_payments', 'constraint' => 'fk_folio_payments_cashbox_shift_id'],
        ] as $foreign_key) {
            if ($this->foreignKeyExists($db, $foreign_key['constraint'])) {
                $db->exec(
                    'ALTER TABLE `' . $foreign_key['table'] . '` DROP FOREIGN KEY `' . $foreign_key['constraint'] . '`'
                );
            }
        }

        foreach ([
            ['table' => 'folio_payments', 'index' => 'uq_folio_payments_reservation_payment_id'],
            ['table' => 'folio_payments', 'index' => 'idx_folio_payments_cashbox_shift_id'],
            ['table' => 'cash_movements', 'index' => 'idx_cash_movements_reference'],
            ['table' => 'reservation_payments', 'index' => 'idx_reservation_payments_reservation_id'],
            ['table' => 'reservation_payments', 'index' => 'idx_reservation_payments_cashbox_shift_id'],
            ['table' => 'reservation_payments', 'index' => 'idx_reservation_payments_created_by_user_id'],
        ] as $index) {
            if ($this->indexExists($db, $index['table'], $index['index'])) {
                $db->exec(
                    'ALTER TABLE `' . $index['table'] . '` DROP INDEX `' . $index['index'] . '`'
                );
            }
        }

        if ($this->columnExists($db, 'folio_payments', 'reservation_payment_id')) {
            $db->exec('ALTER TABLE `folio_payments` DROP COLUMN `reservation_payment_id`');
        }
        if ($this->columnExists($db, 'folio_payments', 'cashbox_shift_id')) {
            $db->exec('ALTER TABLE `folio_payments` DROP COLUMN `cashbox_shift_id`');
        }
        if ($this->columnExists($db, 'cash_movements', 'reference_type')) {
            $db->exec('ALTER TABLE `cash_movements` DROP COLUMN `reference_type`');
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
        ] as $column) {
            if ($this->columnExists($db, 'cashbox_shifts', $column)) {
                $db->exec('ALTER TABLE `cashbox_shifts` DROP COLUMN `' . $column . '`');
            }
        }

        $db->exec('DROP TABLE IF EXISTS `reservation_payments`');
    }

    private function upSqlite(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS reservation_payments ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'reservation_id INTEGER NOT NULL, '
            . 'cashbox_shift_id INTEGER NOT NULL, '
            . 'amount REAL NOT NULL CHECK (amount > 0), '
            . 'payment_method TEXT NOT NULL CHECK (payment_method IN (\'cash\', \'yape\', \'plin\', \'card\', \'transfer\')), '
            . 'reference_number TEXT, '
            . 'notes TEXT, '
            . 'created_by_user_id INTEGER NOT NULL, '
            . 'created_at TEXT DEFAULT CURRENT_TIMESTAMP, '
            . 'FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE RESTRICT, '
            . 'FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id) ON DELETE RESTRICT, '
            . 'FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT'
            . ')'
        );

        $this->addSqliteColumn($db, 'folio_payments', 'cashbox_shift_id', 'INTEGER REFERENCES cashbox_shifts(id) ON DELETE RESTRICT');
        $this->addSqliteColumn($db, 'folio_payments', 'reservation_payment_id', 'INTEGER REFERENCES reservation_payments(id) ON DELETE RESTRICT');
        $this->addSqliteColumn($db, 'cash_movements', 'reference_type', 'TEXT');

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
        ] as $column) {
            $this->addSqliteColumn($db, 'cashbox_shifts', $column, 'REAL NOT NULL DEFAULT 0.0');
        }

        $db->exec('CREATE INDEX IF NOT EXISTS idx_reservation_payments_reservation_id ON reservation_payments (reservation_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_reservation_payments_cashbox_shift_id ON reservation_payments (cashbox_shift_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_reservation_payments_created_by_user_id ON reservation_payments (created_by_user_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_folio_payments_cashbox_shift_id ON folio_payments (cashbox_shift_id)');
        $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS uq_folio_payments_reservation_payment_id ON folio_payments (reservation_payment_id)');
        $db->exec('CREATE INDEX IF NOT EXISTS idx_cash_movements_reference ON cash_movements (reference_type, reference_id)');
    }

    private function upMysql(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS `reservation_payments` ('
            . '`id` INT AUTO_INCREMENT PRIMARY KEY, '
            . '`reservation_id` INT NOT NULL, '
            . '`cashbox_shift_id` INT NOT NULL, '
            . '`amount` DECIMAL(10, 2) NOT NULL, '
            . '`payment_method` ENUM(\'cash\', \'yape\', \'plin\', \'card\', \'transfer\') NOT NULL, '
            . '`reference_number` VARCHAR(100) NULL, '
            . '`notes` TEXT NULL, '
            . '`created_by_user_id` INT NOT NULL, '
            . '`created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'CHECK (`amount` > 0), '
            . 'INDEX `idx_reservation_payments_reservation_id` (`reservation_id`), '
            . 'INDEX `idx_reservation_payments_cashbox_shift_id` (`cashbox_shift_id`), '
            . 'INDEX `idx_reservation_payments_created_by_user_id` (`created_by_user_id`), '
            . 'CONSTRAINT `fk_reservation_payments_reservation_id` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE RESTRICT, '
            . 'CONSTRAINT `fk_reservation_payments_cashbox_shift_id` FOREIGN KEY (`cashbox_shift_id`) REFERENCES `cashbox_shifts` (`id`) ON DELETE RESTRICT, '
            . 'CONSTRAINT `fk_reservation_payments_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->addMysqlColumn($db, 'folio_payments', 'cashbox_shift_id', 'INT NULL');
        $this->addMysqlColumn($db, 'folio_payments', 'reservation_payment_id', 'INT NULL');
        $this->addMysqlColumn($db, 'cash_movements', 'reference_type', 'VARCHAR(50) NULL');

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
        ] as $column) {
            $this->addMysqlColumn($db, 'cashbox_shifts', $column, 'DECIMAL(10, 2) NOT NULL DEFAULT 0.00');
        }

        $this->ensureIndex($db, 'folio_payments', 'idx_folio_payments_cashbox_shift_id', 'INDEX', '(`cashbox_shift_id`)');
        $this->ensureIndex($db, 'folio_payments', 'uq_folio_payments_reservation_payment_id', 'UNIQUE INDEX', '(`reservation_payment_id`)');
        $this->ensureIndex($db, 'cash_movements', 'idx_cash_movements_reference', 'INDEX', '(`reference_type`, `reference_id`)');
        $this->ensureForeignKey($db, 'folio_payments', 'fk_folio_payments_cashbox_shift_id', 'cashbox_shift_id', 'cashbox_shifts', 'id');
        $this->ensureForeignKey($db, 'folio_payments', 'fk_folio_payments_reservation_payment_id', 'reservation_payment_id', 'reservation_payments', 'id');
    }

    private function addSqliteColumn(PDO $db, string $table, string $column, string $definition): void
    {
        $statement = $db->query('PRAGMA table_info(`' . $table . '`)');
        $columns = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($columns as $row) {
            if (($row['name'] ?? null) === $column) {
                return;
            }
        }

        $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
    }

    private function sqliteColumnExists(PDO $db, string $table, string $column): bool
    {
        $statement = $db->query('PRAGMA table_info("' . $table . '")');
        $columns = $statement !== false ? $statement->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($columns as $row) {
            if (($row['name'] ?? null) === $column) {
                return true;
            }
        }

        return false;
    }

    private function addMysqlColumn(PDO $db, string $table, string $column, string $definition): void
    {
        if (!$this->columnExists($db, $table, $column)) {
            $db->exec('ALTER TABLE `' . $table . '` ADD COLUMN `' . $column . '` ' . $definition);
        }
    }

    private function ensureIndex(PDO $db, string $table, string $index, string $type, string $columns): void
    {
        if (!$this->indexExists($db, $table, $index)) {
            $db->exec('ALTER TABLE `' . $table . '` ADD ' . $type . ' `' . $index . '` ' . $columns);
        }
    }

    private function ensureForeignKey(PDO $db, string $table, string $constraint, string $column, string $parent_table, string $parent_column): void
    {
        if (!$this->foreignKeyExists($db, $constraint)) {
            $db->exec(
                'ALTER TABLE `' . $table . '` ADD CONSTRAINT `' . $constraint . '` FOREIGN KEY (`' . $column . '`)'
                . ' REFERENCES `' . $parent_table . '` (`' . $parent_column . '`) ON DELETE RESTRICT'
            );
        }
    }

    private function columnExists(PDO $db, string $table, string $column): bool
    {
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $statement->execute(['table_name' => $table, 'column_name' => $column]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function indexExists(PDO $db, string $table, string $index): bool
    {
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
        );
        $statement->execute(['table_name' => $table, 'index_name' => $index]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function foreignKeyExists(PDO $db, string $constraint): bool
    {
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_TYPE = :constraint_type '
            . 'AND CONSTRAINT_NAME = :constraint_name'
        );
        $statement->execute(['constraint_type' => 'FOREIGN KEY', 'constraint_name' => $constraint]);

        return (int) $statement->fetchColumn() === 1;
    }
};
