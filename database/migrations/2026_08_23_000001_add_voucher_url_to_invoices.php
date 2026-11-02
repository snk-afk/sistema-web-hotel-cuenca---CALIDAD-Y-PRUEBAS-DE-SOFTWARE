<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('ALTER TABLE invoices ADD COLUMN voucher_url TEXT NULL');
            return;
        }

        $db->exec('ALTER TABLE invoices ADD COLUMN voucher_url VARCHAR(255) NULL AFTER pdf_url');
    }

    public function down(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('ALTER TABLE invoices DROP COLUMN voucher_url');
            return;
        }

        $db->exec('ALTER TABLE invoices DROP COLUMN voucher_url');
    }
};
