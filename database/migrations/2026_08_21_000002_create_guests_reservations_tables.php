<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS guests (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    document_type TEXT NOT NULL,
                    document_number TEXT NOT NULL UNIQUE,
                    name TEXT NOT NULL,
                    address TEXT,
                    phone TEXT,
                    email TEXT,
                    notes TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS stay_guests (
                    stay_id INTEGER NOT NULL,
                    guest_id INTEGER NOT NULL,
                    is_primary INTEGER DEFAULT 1,
                    PRIMARY KEY (stay_id, guest_id),
                    FOREIGN KEY (stay_id) REFERENCES stays(id),
                    FOREIGN KEY (guest_id) REFERENCES guests(id)
                );

                CREATE TABLE IF NOT EXISTS reservations (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_id INTEGER NOT NULL,
                    guest_id INTEGER NOT NULL,
                    room_tariff_id INTEGER NOT NULL,
                    start_time TEXT NOT NULL,
                    end_time TEXT NOT NULL,
                    deposit_amount REAL DEFAULT 0.0,
                    status TEXT DEFAULT "pending",
                    notes TEXT,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (room_id) REFERENCES rooms(id),
                    FOREIGN KEY (guest_id) REFERENCES guests(id),
                    FOREIGN KEY (room_tariff_id) REFERENCES room_tariffs(id)
                );
            ');
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS guests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                document_type ENUM('DNI', 'RUC', 'CE', 'PASAPORTE') NOT NULL,
                document_number VARCHAR(20) NOT NULL UNIQUE,
                name VARCHAR(150) NOT NULL,
                address TEXT NULL,
                phone VARCHAR(30) NULL,
                email VARCHAR(100) NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS stay_guests (
                stay_id INT NOT NULL,
                guest_id INT NOT NULL,
                is_primary TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (stay_id, guest_id),
                FOREIGN KEY (stay_id) REFERENCES stays(id) ON DELETE CASCADE,
                FOREIGN KEY (guest_id) REFERENCES guests(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS reservations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_id INT NOT NULL,
                guest_id INT NOT NULL,
                room_tariff_id INT NOT NULL,
                start_time DATETIME NOT NULL,
                end_time DATETIME NOT NULL,
                deposit_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                status ENUM('pending', 'confirmed', 'checked_in', 'cancelled') NOT NULL DEFAULT 'pending',
                notes TEXT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id),
                FOREIGN KEY (guest_id) REFERENCES guests(id),
                FOREIGN KEY (room_tariff_id) REFERENCES room_tariffs(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec('
            DROP TABLE IF EXISTS reservations;
            DROP TABLE IF EXISTS stay_guests;
            DROP TABLE IF EXISTS guests;
        ');
    }
};
