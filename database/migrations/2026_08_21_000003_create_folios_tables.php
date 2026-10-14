<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS folios (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    stay_id INTEGER NOT NULL UNIQUE,
                    status TEXT DEFAULT "open",
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (stay_id) REFERENCES stays(id)
                );

                CREATE TABLE IF NOT EXISTS folio_charges (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    folio_id INTEGER NOT NULL,
                    charge_type TEXT NOT NULL,
                    description TEXT NOT NULL,
                    quantity INTEGER DEFAULT 1,
                    unit_price REAL NOT NULL,
                    total_amount REAL NOT NULL,
                    is_reversal INTEGER DEFAULT 0,
                    reversal_reason TEXT,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (folio_id) REFERENCES folios(id)
                );

                CREATE TABLE IF NOT EXISTS folio_payments (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    folio_id INTEGER NOT NULL,
                    payment_type TEXT NOT NULL,
                    amount REAL NOT NULL,
                    payment_method TEXT DEFAULT "cash",
                    reference_number TEXT,
                    notes TEXT,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (folio_id) REFERENCES folios(id)
                );

                CREATE TABLE IF NOT EXISTS room_transfers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    stay_id INTEGER NOT NULL,
                    from_room_id INTEGER NOT NULL,
                    to_room_id INTEGER NOT NULL,
                    reason TEXT NOT NULL,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (stay_id) REFERENCES stays(id),
                    FOREIGN KEY (from_room_id) REFERENCES rooms(id),
                    FOREIGN KEY (to_room_id) REFERENCES rooms(id)
                );
            ');
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS folios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stay_id INT NOT NULL UNIQUE,
                status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (stay_id) REFERENCES stays(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS folio_charges (
                id INT AUTO_INCREMENT PRIMARY KEY,
                folio_id INT NOT NULL,
                charge_type ENUM('accommodation', 'store_item', 'service', 'extension', 'other') NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10, 2) NOT NULL,
                total_amount DECIMAL(10, 2) NOT NULL,
                is_reversal TINYINT(1) NOT NULL DEFAULT 0,
                reversal_reason VARCHAR(255) NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (folio_id) REFERENCES folios(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS folio_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                folio_id INT NOT NULL,
                payment_type ENUM('advance', 'checkout_payment', 'refund') NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
                reference_number VARCHAR(100) NULL,
                notes TEXT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (folio_id) REFERENCES folios(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS room_transfers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stay_id INT NOT NULL,
                from_room_id INT NOT NULL,
                to_room_id INT NOT NULL,
                reason TEXT NOT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (stay_id) REFERENCES stays(id),
                FOREIGN KEY (from_room_id) REFERENCES rooms(id),
                FOREIGN KEY (to_room_id) REFERENCES rooms(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec('
            DROP TABLE IF EXISTS room_transfers;
            DROP TABLE IF EXISTS folio_payments;
            DROP TABLE IF EXISTS folio_charges;
            DROP TABLE IF EXISTS folios;
        ');
    }
};
