<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->upSqlite($db);
            return;
        }

        $db->exec(
            "CREATE TABLE IF NOT EXISTS auth_login_attempts (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                username_hash CHAR(64) NOT NULL,
                source_hash CHAR(64) NOT NULL,
                attempted_at DATETIME NOT NULL,
                was_successful TINYINT(1) NOT NULL DEFAULT 0,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_auth_login_attempts_lookup (username_hash, source_hash, attempted_at),
                INDEX idx_auth_login_attempts_cleanup (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS invoice_transmissions (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL UNIQUE,
                provider VARCHAR(50) NOT NULL,
                idempotency_key CHAR(64) NOT NULL UNIQUE,
                status ENUM('pending', 'sent', 'accepted', 'rejected', 'failed') NOT NULL DEFAULT 'pending',
                provider_code VARCHAR(100) NULL,
                provider_message VARCHAR(500) NULL,
                attempts INT NOT NULL DEFAULT 0,
                last_attempt_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CHECK (attempts >= 0),
                INDEX idx_invoice_transmissions_status_attempt (status, last_attempt_at),
                CONSTRAINT fk_invoice_transmissions_invoice_id
                    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS invoice_transmissions');
        $db->exec('DROP TABLE IF EXISTS auth_login_attempts');
    }

    private function upSqlite(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS auth_login_attempts ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'username_hash TEXT NOT NULL, '
            . 'source_hash TEXT NOT NULL, '
            . 'attempted_at TEXT NOT NULL, '
            . 'was_successful INTEGER NOT NULL DEFAULT 0 CHECK (was_successful IN (0, 1)), '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ')'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_auth_login_attempts_lookup '
            . 'ON auth_login_attempts (username_hash, source_hash, attempted_at)'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_auth_login_attempts_cleanup '
            . 'ON auth_login_attempts (created_at)'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS invoice_transmissions ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'invoice_id INTEGER NOT NULL UNIQUE, '
            . 'provider TEXT NOT NULL, '
            . 'idempotency_key TEXT NOT NULL UNIQUE, '
            . 'status TEXT NOT NULL DEFAULT \'pending\' CHECK (status IN (\'pending\', \'sent\', \'accepted\', \'rejected\', \'failed\')), '
            . 'provider_code TEXT, '
            . 'provider_message TEXT, '
            . 'attempts INTEGER NOT NULL DEFAULT 0 CHECK (attempts >= 0), '
            . 'last_attempt_at TEXT, '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT'
            . ')'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_invoice_transmissions_status_attempt '
            . 'ON invoice_transmissions (status, last_attempt_at)'
        );
    }
};
