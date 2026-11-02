<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS room_categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL UNIQUE,
                    description TEXT,
                    is_active INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS rooms (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_category_id INTEGER NOT NULL,
                    number TEXT NOT NULL UNIQUE,
                    status TEXT DEFAULT "free",
                    floor TEXT,
                    description TEXT,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (room_category_id) REFERENCES room_categories(id)
                );

                CREATE TABLE IF NOT EXISTS room_tariffs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_category_id INTEGER NOT NULL,
                    name TEXT NOT NULL,
                    duration_hours INTEGER NOT NULL,
                    price REAL NOT NULL,
                    is_active INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (room_category_id) REFERENCES room_categories(id)
                );

                CREATE TABLE IF NOT EXISTS stays (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_id INTEGER NOT NULL,
                    room_tariff_id INTEGER NOT NULL,
                    guest_id INTEGER,
                    check_in_at TEXT NOT NULL,
                    expected_check_out_at TEXT NOT NULL,
                    actual_check_out_at TEXT,
                    base_price REAL NOT NULL,
                    applied_price REAL NOT NULL,
                    discount_reason TEXT,
                    status TEXT DEFAULT "active",
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (room_id) REFERENCES rooms(id),
                    FOREIGN KEY (room_tariff_id) REFERENCES room_tariffs(id)
                );

                CREATE TABLE IF NOT EXISTS stay_extensions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    stay_id INTEGER NOT NULL,
                    added_hours INTEGER NOT NULL,
                    added_price REAL NOT NULL,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (stay_id) REFERENCES stays(id)
                );
            ');
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS room_categories (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                description TEXT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS rooms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_category_id INT NOT NULL,
                number VARCHAR(20) NOT NULL UNIQUE,
                status ENUM('free', 'occupied', 'cleaning', 'maintenance', 'reserved') NOT NULL DEFAULT 'free',
                floor VARCHAR(10) NULL,
                description TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (room_category_id) REFERENCES room_categories(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS room_tariffs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_category_id INT NOT NULL,
                name VARCHAR(100) NOT NULL,
                duration_hours INT NOT NULL,
                price DECIMAL(10, 2) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (room_category_id) REFERENCES room_categories(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS stays (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_id INT NOT NULL,
                room_tariff_id INT NOT NULL,
                guest_id INT NULL,
                check_in_at DATETIME NOT NULL,
                expected_check_out_at DATETIME NOT NULL,
                actual_check_out_at DATETIME NULL,
                base_price DECIMAL(10, 2) NOT NULL,
                applied_price DECIMAL(10, 2) NOT NULL,
                discount_reason VARCHAR(255) NULL,
                status ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id),
                FOREIGN KEY (room_tariff_id) REFERENCES room_tariffs(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS stay_extensions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stay_id INT NOT NULL,
                added_hours INT NOT NULL,
                added_price DECIMAL(10, 2) NOT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (stay_id) REFERENCES stays(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec('
            DROP TABLE IF EXISTS stay_extensions;
            DROP TABLE IF EXISTS stays;
            DROP TABLE IF EXISTS room_tariffs;
            DROP TABLE IF EXISTS rooms;
            DROP TABLE IF EXISTS room_categories;
        ');
    }
};
