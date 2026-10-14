<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = $db->query('PRAGMA table_info(store_sales)')->fetchAll(PDO::FETCH_ASSOC);
            $has_column = array_any(
                $columns,
                static fn(array $column): bool => ($column['name'] ?? null) === 'idempotency_key',
            );

            if (!$has_column) {
                $db->exec('ALTER TABLE store_sales ADD COLUMN idempotency_key TEXT NULL');
            }

            $db->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS idx_store_sales_idempotency_key '
                . 'ON store_sales (idempotency_key)'
            );
            return;
        }

        $statement = $db->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() '
            . 'AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name'
        );
        $statement->execute([
            'table_name' => 'store_sales',
            'column_name' => 'idempotency_key',
        ]);

        if ((int) $statement->fetchColumn() === 0) {
            $db->exec('ALTER TABLE store_sales ADD COLUMN idempotency_key CHAR(64) NULL');
        }

        $db->exec(
            'CREATE UNIQUE INDEX idx_store_sales_idempotency_key ON store_sales (idempotency_key)'
        );
    }

    public function down(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $db->exec('DROP INDEX IF EXISTS idx_store_sales_idempotency_key');
            $db->exec('ALTER TABLE store_sales DROP COLUMN idempotency_key');
            return;
        }

        $db->exec('DROP INDEX idx_store_sales_idempotency_key ON store_sales');
        $db->exec('ALTER TABLE store_sales DROP COLUMN idempotency_key');
    }
};
