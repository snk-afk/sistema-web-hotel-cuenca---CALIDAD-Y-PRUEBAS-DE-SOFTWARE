<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS expenses (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cashbox_shift_id INTEGER NOT NULL,
                    category TEXT DEFAULT "petty_cash",
                    concept TEXT NOT NULL,
                    amount REAL NOT NULL,
                    payment_method TEXT DEFAULT "cash",
                    supplier_name TEXT,
                    receipt_number TEXT,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
                );

                CREATE TABLE IF NOT EXISTS other_incomes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cashbox_shift_id INTEGER NOT NULL,
                    category TEXT DEFAULT "other_income",
                    concept TEXT NOT NULL,
                    amount REAL NOT NULL,
                    payment_method TEXT DEFAULT "cash",
                    notes TEXT,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
                );
            ');
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS expenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cashbox_shift_id INT NOT NULL,
                category ENUM('maintenance', 'supplies', 'utilities', 'petty_cash', 'other_expense') NOT NULL DEFAULT 'petty_cash',
                concept VARCHAR(255) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
                supplier_name VARCHAR(150) NULL,
                receipt_number VARCHAR(50) NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS other_incomes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cashbox_shift_id INT NOT NULL,
                category ENUM('parking', 'laundry_external', 'events', 'penalty_damage', 'recycling', 'other_income') NOT NULL DEFAULT 'other_income',
                concept VARCHAR(255) NOT NULL,
                amount DECIMAL(10, 2) NOT NULL,
                payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
                notes TEXT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec('
            DROP TABLE IF EXISTS other_incomes;
            DROP TABLE IF EXISTS expenses;
        ');
    }
};
