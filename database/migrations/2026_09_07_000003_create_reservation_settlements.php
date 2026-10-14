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
            "ALTER TABLE reservations MODIFY COLUMN status "
            . "ENUM('pending', 'confirmed', 'checked_in', 'cancelled', 'no_show') "
            . "NOT NULL DEFAULT 'pending'"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS reservation_cancellations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                reservation_id INT NOT NULL UNIQUE,
                event_type ENUM('cancelled', 'no_show') NOT NULL,
                policy_code ENUM('full_refund', 'partial_refund', 'no_refund') NOT NULL,
                refund_percentage DECIMAL(5, 2) NOT NULL,
                gross_paid_amount DECIMAL(10, 2) NOT NULL,
                refund_amount DECIMAL(10, 2) NOT NULL,
                penalty_amount DECIMAL(10, 2) NOT NULL,
                status ENUM('pending_refund', 'completed', 'no_refund') NOT NULL,
                reason VARCHAR(255) NOT NULL,
                requested_by_user_id INT NOT NULL,
                approved_by_user_id INT NULL,
                approved_at DATETIME NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CHECK (refund_percentage >= 0 AND refund_percentage <= 100),
                CHECK (gross_paid_amount >= 0 AND refund_amount >= 0 AND penalty_amount >= 0),
                INDEX idx_reservation_cancellations_status_created (status, created_at),
                CONSTRAINT fk_reservation_cancellations_reservation_id
                    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE RESTRICT,
                CONSTRAINT fk_reservation_cancellations_requested_by_user_id
                    FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
                CONSTRAINT fk_reservation_cancellations_approved_by_user_id
                    FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $db->exec(
            "CREATE TABLE IF NOT EXISTS reservation_refunds (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cancellation_id INT NOT NULL,
                reservation_payment_id INT NOT NULL UNIQUE,
                cashbox_shift_id INT NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL,
                reference_number VARCHAR(100) NULL,
                executed_by_user_id INT NOT NULL,
                reason VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CHECK (amount > 0),
                INDEX idx_reservation_refunds_cancellation_id (cancellation_id),
                CONSTRAINT fk_reservation_refunds_cancellation_id
                    FOREIGN KEY (cancellation_id) REFERENCES reservation_cancellations(id) ON DELETE RESTRICT,
                CONSTRAINT fk_reservation_refunds_reservation_payment_id
                    FOREIGN KEY (reservation_payment_id) REFERENCES reservation_payments(id) ON DELETE RESTRICT,
                CONSTRAINT fk_reservation_refunds_cashbox_shift_id
                    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id) ON DELETE RESTRICT,
                CONSTRAINT fk_reservation_refunds_executed_by_user_id
                    FOREIGN KEY (executed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS reservation_refunds');
        $db->exec('DROP TABLE IF EXISTS reservation_cancellations');

        if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return;
        }

        $statement = $db->query("SELECT COUNT(*) FROM reservations WHERE status = 'no_show'");
        $no_show_count = $statement !== false ? (int) $statement->fetchColumn() : 0;
        if ($no_show_count === 0) {
            $db->exec(
                "ALTER TABLE reservations MODIFY COLUMN status "
                . "ENUM('pending', 'confirmed', 'checked_in', 'cancelled') "
                . "NOT NULL DEFAULT 'pending'"
            );
        }
    }

    private function upSqlite(PDO $db): void
    {
        $db->exec(
            'CREATE TABLE IF NOT EXISTS reservation_cancellations ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'reservation_id INTEGER NOT NULL UNIQUE, '
            . 'event_type TEXT NOT NULL CHECK (event_type IN (\'cancelled\', \'no_show\')), '
            . 'policy_code TEXT NOT NULL CHECK (policy_code IN (\'full_refund\', \'partial_refund\', \'no_refund\')), '
            . 'refund_percentage REAL NOT NULL CHECK (refund_percentage >= 0 AND refund_percentage <= 100), '
            . 'gross_paid_amount REAL NOT NULL CHECK (gross_paid_amount >= 0), '
            . 'refund_amount REAL NOT NULL CHECK (refund_amount >= 0), '
            . 'penalty_amount REAL NOT NULL CHECK (penalty_amount >= 0), '
            . 'status TEXT NOT NULL CHECK (status IN (\'pending_refund\', \'completed\', \'no_refund\')), '
            . 'reason TEXT NOT NULL, '
            . 'requested_by_user_id INTEGER NOT NULL, '
            . 'approved_by_user_id INTEGER, '
            . 'approved_at TEXT, '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE RESTRICT, '
            . 'FOREIGN KEY (requested_by_user_id) REFERENCES users(id) ON DELETE RESTRICT, '
            . 'FOREIGN KEY (approved_by_user_id) REFERENCES users(id) ON DELETE RESTRICT'
            . ')'
        );

        $db->exec(
            'CREATE TABLE IF NOT EXISTS reservation_refunds ('
            . 'id INTEGER PRIMARY KEY AUTOINCREMENT, '
            . 'cancellation_id INTEGER NOT NULL, '
            . 'reservation_payment_id INTEGER NOT NULL UNIQUE, '
            . 'cashbox_shift_id INTEGER NOT NULL, '
            . 'amount REAL NOT NULL CHECK (amount > 0), '
            . 'payment_method TEXT NOT NULL CHECK (payment_method IN (\'cash\', \'yape\', \'plin\', \'card\', \'transfer\')), '
            . 'reference_number TEXT, '
            . 'executed_by_user_id INTEGER NOT NULL, '
            . 'reason TEXT NOT NULL, '
            . 'created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, '
            . 'FOREIGN KEY (cancellation_id) REFERENCES reservation_cancellations(id) ON DELETE RESTRICT, '
            . 'FOREIGN KEY (reservation_payment_id) REFERENCES reservation_payments(id) ON DELETE RESTRICT, '
            . 'FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id) ON DELETE RESTRICT, '
            . 'FOREIGN KEY (executed_by_user_id) REFERENCES users(id) ON DELETE RESTRICT'
            . ')'
        );

        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_reservation_cancellations_status_created '
            . 'ON reservation_cancellations (status, created_at)'
        );
        $db->exec(
            'CREATE INDEX IF NOT EXISTS idx_reservation_refunds_cancellation_id '
            . 'ON reservation_refunds (cancellation_id)'
        );
    }
};
