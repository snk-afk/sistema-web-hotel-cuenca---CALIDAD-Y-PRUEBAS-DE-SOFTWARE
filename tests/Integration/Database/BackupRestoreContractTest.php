<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use Tests\Support\MySqlTestCase;

final class BackupRestoreContractTest extends MySqlTestCase
{
    public function testRunbookDefinesSafeBackupRestoreAndRollbackContract(): void
    {
        $runbook = (string) file_get_contents(__DIR__ . '/../../../docs/operations/production-runbook.md');
        $uat = (string) file_get_contents(__DIR__ . '/../../../docs/operations/hotel-uat-checklist.md');
        $backup_script = (string) file_get_contents(__DIR__ . '/../../../deploy/scripts/backup.sh');
        $restore_script = (string) file_get_contents(__DIR__ . '/../../../deploy/scripts/restore-verify.sh');

        foreach ([
            'sha256sum',
            'restaurar el backup en una base aislada',
            'php database/migrate.php',
            '/api/health/ready',
            'No se ejecuta un `down` automático sobre producción',
        ] as $required) {
            self::assertStringContainsString($required, $runbook);
        }

        foreach ([
            'mysqldump',
            '--single-transaction',
            '--routines',
            '--triggers',
            'sha256sum',
            'age --encrypt',
        ] as $required) {
            self::assertStringContainsString($required, $backup_script);
        }

        foreach (['cs_hotel_restore_', 'la base de restore no está vacía', 'age --decrypt'] as $required) {
            self::assertStringContainsString($required, $restore_script);
        }

        foreach (['UAT-02', 'UAT-08', 'UAT-11', 'UAT-15', 'UAT-16'] as $required) {
            self::assertStringContainsString($required, $uat);
        }

        self::assertStringContainsString('bucket privado', $runbook);
        self::assertStringContainsString('ruta autenticada', $runbook);
        self::assertStringNotContainsString('Admin123!', $runbook . $uat);
        self::assertStringNotContainsString('password=', $runbook . $uat);

        $tables = $this->pdo()->query(
            "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()"
        )->fetchAll(\PDO::FETCH_COLUMN);
        self::assertContains('migrations', $tables);
        self::assertContains('cashbox_shifts', $tables);
        self::assertContains('cash_movements', $tables);
        self::assertContains('invoice_transmissions', $tables);
    }
}
