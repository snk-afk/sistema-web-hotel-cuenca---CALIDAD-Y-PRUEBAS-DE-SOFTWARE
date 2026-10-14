<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS cashbox_shifts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    opened_at TEXT NOT NULL,
                    closed_at TEXT,
                    initial_cash REAL DEFAULT 0.0,
                    expected_cash REAL DEFAULT 0.0,
                    real_cash_declared REAL,
                    difference REAL,
                    status TEXT DEFAULT "open",
                    balance_status TEXT DEFAULT "pending",
                    notes TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS cash_movements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cashbox_shift_id INTEGER NOT NULL,
                    type TEXT NOT NULL,
                    category TEXT NOT NULL,
                    concept TEXT NOT NULL,
                    amount REAL NOT NULL,
                    payment_method TEXT DEFAULT "cash",
                    reference_id INTEGER,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
                );
            ');
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS cashbox_shifts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                opened_at DATETIME NOT NULL,
                closed_at DATETIME NULL,
                initial_cash DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                expected_cash DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                real_cash_declared DECIMAL(10, 2) NULL,
                difference DECIMAL(10, 2) NULL,
                status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
                balance_status ENUM('balanced', 'surplus', 'shortage', 'pending') NOT NULL DEFAULT 'pending',
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS cash_movements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cashbox_shift_id INT NOT NULL,
                type ENUM('income', 'expense') NOT NULL,
                category VARCHAR(50) NOT NULL,
                concept VARCHAR(255) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
                reference_id INT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec('
            DROP TABLE IF EXISTS cash_movements;
            DROP TABLE IF EXISTS cashbox_shifts;
        ');
    }
};
