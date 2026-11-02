<?php

declare(strict_types=1);

return new class {
    /**
     * @return array<int, array{child_table: string, child_column: string, parent_table: string, parent_column: string, constraint_name: string, index_name: string, delete_rule: string}>
     */
    private function foreignKeys(): array
    {
        return [
            ['child_table' => 'stays', 'child_column' => 'guest_id', 'parent_table' => 'guests', 'parent_column' => 'id', 'constraint_name' => 'fk_stays_guest_id', 'index_name' => 'idx_stays_guest_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'stays', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_stays_created_by_user_id', 'index_name' => 'idx_stays_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'stay_extensions', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_stay_extensions_created_by_user_id', 'index_name' => 'idx_stay_extensions_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'reservations', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_reservations_created_by_user_id', 'index_name' => 'idx_reservations_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'room_transfers', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_room_transfers_created_by_user_id', 'index_name' => 'idx_room_transfers_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'cashbox_shifts', 'child_column' => 'user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_cashbox_shifts_user_id', 'index_name' => 'idx_cashbox_shifts_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'cash_movements', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_cash_movements_created_by_user_id', 'index_name' => 'idx_cash_movements_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'folio_charges', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_folio_charges_created_by_user_id', 'index_name' => 'idx_folio_charges_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'folio_payments', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_folio_payments_created_by_user_id', 'index_name' => 'idx_folio_payments_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'store_sales', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_store_sales_created_by_user_id', 'index_name' => 'idx_store_sales_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'inventory_movements', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_inventory_movements_created_by_user_id', 'index_name' => 'idx_inventory_movements_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'expenses', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_expenses_created_by_user_id', 'index_name' => 'idx_expenses_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'other_incomes', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_other_incomes_created_by_user_id', 'index_name' => 'idx_other_incomes_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'housekeeping_tasks', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_housekeeping_tasks_created_by_user_id', 'index_name' => 'idx_housekeeping_tasks_created_by_user_id', 'delete_rule' => 'RESTRICT'],
            ['child_table' => 'housekeeping_tasks', 'child_column' => 'assigned_to_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_housekeeping_tasks_assigned_to_user_id', 'index_name' => 'idx_housekeeping_tasks_assigned_to_user_id', 'delete_rule' => 'SET NULL'],
            ['child_table' => 'invoices', 'child_column' => 'created_by_user_id', 'parent_table' => 'users', 'parent_column' => 'id', 'constraint_name' => 'fk_invoices_created_by_user_id', 'index_name' => 'idx_invoices_created_by_user_id', 'delete_rule' => 'RESTRICT'],
        ];
    }

    public function up(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        $foreign_keys = $this->foreignKeys();

        foreach ($foreign_keys as $foreign_key) {
            $this->assertColumnExists($db, $foreign_key['child_table'], $foreign_key['child_column']);
            $this->assertColumnExists($db, $foreign_key['parent_table'], $foreign_key['parent_column']);
            $this->assertNoOrphans($db, $foreign_key);
            $this->assertExistingConstraintIsCompatible($db, $foreign_key);
        }

        $this->assertExistingChargeValuesAreCanonical($db);

        foreach ($foreign_keys as $foreign_key) {
            $this->ensureIndex($db, $foreign_key);
            $this->ensureForeignKey($db, $foreign_key);
        }

        $db->exec(
            "ALTER TABLE `folio_charges` MODIFY COLUMN `charge_type` "
            . "ENUM('accommodation', 'store_item', 'service', 'extension', 'other', 'penalty', 'adjustment') "
            . 'NOT NULL'
        );
    }

    public function down(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
            return;
        }

        $statement = $db->query(
            "SELECT COUNT(*) FROM `folio_charges` WHERE `charge_type` IN ('penalty', 'adjustment')"
        );

        if ((int) $statement->fetchColumn() > 0) {
            throw new RuntimeException(
                'No se puede revertir el ENUM mientras existan cargos penalty o adjustment.'
            );
        }

        $db->exec(
            "ALTER TABLE `folio_charges` MODIFY COLUMN `charge_type` "
            . "ENUM('accommodation', 'store_item', 'service', 'extension', 'other') NOT NULL"
        );

        foreach (array_reverse($this->foreignKeys()) as $foreign_key) {
            if ($this->foreignKeyExists($db, $foreign_key['constraint_name'])) {
                $db->exec(
                    'ALTER TABLE ' . $this->identifier($foreign_key['child_table'])
                    . ' DROP FOREIGN KEY ' . $this->identifier($foreign_key['constraint_name'])
                );
            }

            if ($this->indexExists($db, $foreign_key['child_table'], $foreign_key['index_name'])) {
                $db->exec(
                    'ALTER TABLE ' . $this->identifier($foreign_key['child_table'])
                    . ' DROP INDEX ' . $this->identifier($foreign_key['index_name'])
                );
            }
        }
    }

    /**
     * @param array{child_table: string, child_column: string, parent_table: string, parent_column: string, constraint_name: string, index_name: string, delete_rule: string} $foreign_key
     */
    private function assertNoOrphans(PDO $db, array $foreign_key): void
    {
        $child_table = $this->identifier($foreign_key['child_table']);
        $child_column = $this->identifier($foreign_key['child_column']);
        $parent_table = $this->identifier($foreign_key['parent_table']);
        $parent_column = $this->identifier($foreign_key['parent_column']);

        $statement = $db->query(
            'SELECT COUNT(*) FROM ' . $child_table . ' AS child_row '
            . 'LEFT JOIN ' . $parent_table . ' AS parent_row '
            . 'ON child_row.' . $child_column . ' = parent_row.' . $parent_column . ' '
            . 'WHERE child_row.' . $child_column . ' IS NOT NULL '
            . 'AND parent_row.' . $parent_column . ' IS NULL'
        );

        if ((int) $statement->fetchColumn() > 0) {
            throw new RuntimeException(
                'No se puede agregar la relación por datos huérfanos en '
                . $foreign_key['child_table'] . '.' . $foreign_key['child_column'] . '.'
            );
        }
    }

    private function assertColumnExists(PDO $db, string $table_name, string $column_name): void
    {
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => $table_name,
            'column_name' => $column_name,
        ]);

        if ((int) $statement->fetchColumn() !== 1) {
            throw new RuntimeException("No existe la columna {$table_name}.{$column_name}.");
        }
    }

    /**
     * @param array{child_table: string, child_column: string, parent_table: string, parent_column: string, constraint_name: string, index_name: string, delete_rule: string} $foreign_key
     */
    private function assertExistingConstraintIsCompatible(PDO $db, array $foreign_key): void
    {
        $statement = $db->prepare(
            'SELECT kcu.REFERENCED_TABLE_NAME AS parent_table, '
            . 'kcu.REFERENCED_COLUMN_NAME AS parent_column, rc.DELETE_RULE AS delete_rule '
            . 'FROM information_schema.KEY_COLUMN_USAGE kcu '
            . 'INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS rc '
            . 'ON rc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA '
            . 'AND rc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME '
            . 'AND rc.TABLE_NAME = kcu.TABLE_NAME '
            . 'WHERE kcu.CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND kcu.TABLE_NAME = :table_name '
            . 'AND kcu.COLUMN_NAME = :column_name '
            . 'AND kcu.CONSTRAINT_NAME = :constraint_name'
        );
        $statement->execute([
            'table_name' => $foreign_key['child_table'],
            'column_name' => $foreign_key['child_column'],
            'constraint_name' => $foreign_key['constraint_name'],
        ]);
        $existing = $statement->fetch();

        if ($existing === false) {
            return;
        }

        if (
            $existing['parent_table'] !== $foreign_key['parent_table']
            || $existing['parent_column'] !== $foreign_key['parent_column']
            || $existing['delete_rule'] !== $foreign_key['delete_rule']
        ) {
            throw new RuntimeException(
                'La relación existente no coincide con el contrato aprobado en '
                . $foreign_key['child_table'] . '.' . $foreign_key['child_column'] . '.'
            );
        }
    }

    /**
     * @param array{child_table: string, child_column: string, parent_table: string, parent_column: string, constraint_name: string, index_name: string, delete_rule: string} $foreign_key
     */
    private function ensureIndex(PDO $db, array $foreign_key): void
    {
        if ($this->indexExists($db, $foreign_key['child_table'], $foreign_key['index_name'])) {
            return;
        }

        $db->exec(
            'ALTER TABLE ' . $this->identifier($foreign_key['child_table'])
            . ' ADD INDEX ' . $this->identifier($foreign_key['index_name'])
            . ' (' . $this->identifier($foreign_key['child_column']) . ')'
        );
    }

    /**
     * @param array{child_table: string, child_column: string, parent_table: string, parent_column: string, constraint_name: string, index_name: string, delete_rule: string} $foreign_key
     */
    private function ensureForeignKey(PDO $db, array $foreign_key): void
    {
        if ($this->foreignKeyExists($db, $foreign_key['constraint_name'])) {
            return;
        }

        $db->exec(
            'ALTER TABLE ' . $this->identifier($foreign_key['child_table'])
            . ' ADD CONSTRAINT ' . $this->identifier($foreign_key['constraint_name'])
            . ' FOREIGN KEY (' . $this->identifier($foreign_key['child_column']) . ')'
            . ' REFERENCES ' . $this->identifier($foreign_key['parent_table'])
            . ' (' . $this->identifier($foreign_key['parent_column']) . ')'
            . ' ON DELETE ' . $foreign_key['delete_rule']
        );
    }

    private function foreignKeyExists(PDO $db, string $constraint_name): bool
    {
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS '
            . 'WHERE CONSTRAINT_SCHEMA = DATABASE() '
            . 'AND CONSTRAINT_TYPE = :constraint_type AND CONSTRAINT_NAME = :constraint_name'
        );
        $statement->execute([
            'constraint_type' => 'FOREIGN KEY',
            'constraint_name' => $constraint_name,
        ]);

        return (int) $statement->fetchColumn() === 1;
    }

    private function indexExists(PDO $db, string $table_name, string $index_name): bool
    {
        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name'
        );
        $statement->execute([
            'table_name' => $table_name,
            'index_name' => $index_name,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function assertExistingChargeValuesAreCanonical(PDO $db): void
    {
        $statement = $db->query(
            "SELECT DISTINCT `charge_type` FROM `folio_charges` "
            . "WHERE `charge_type` NOT IN ('accommodation', 'store_item', 'service', 'extension', 'other')"
        );

        if ($statement->fetchColumn() !== false) {
            throw new RuntimeException('Existen valores de charge_type que no pertenecen al contrato conocido.');
        }
    }

    private function identifier(string $identifier): string
    {
        if (preg_match('/\\A[a-z][a-z0-9_]*\\z/', $identifier) !== 1) {
            throw new RuntimeException('Identificador de esquema no permitido.');
        }

        return '`' . $identifier . '`';
    }
};
