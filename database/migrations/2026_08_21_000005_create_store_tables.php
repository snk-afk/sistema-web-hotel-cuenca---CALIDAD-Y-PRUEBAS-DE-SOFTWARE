<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS products (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    sku TEXT UNIQUE,
                    name TEXT NOT NULL,
                    description TEXT,
                    cost_price REAL DEFAULT 0.0,
                    sale_price REAL NOT NULL,
                    stock INTEGER DEFAULT 0,
                    min_stock INTEGER DEFAULT 5,
                    is_active INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS store_sales (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    cashbox_shift_id INTEGER,
                    stay_id INTEGER,
                    payment_type TEXT DEFAULT "cash",
                    total_amount REAL NOT NULL,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id),
                    FOREIGN KEY (stay_id) REFERENCES stays(id)
                );

                CREATE TABLE IF NOT EXISTS store_sale_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    store_sale_id INTEGER NOT NULL,
                    product_id INTEGER NOT NULL,
                    quantity INTEGER NOT NULL,
                    unit_price REAL NOT NULL,
                    subtotal REAL NOT NULL,
                    FOREIGN KEY (store_sale_id) REFERENCES store_sales(id),
                    FOREIGN KEY (product_id) REFERENCES products(id)
                );

                CREATE TABLE IF NOT EXISTS inventory_movements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    product_id INTEGER NOT NULL,
                    movement_type TEXT NOT NULL,
                    quantity INTEGER NOT NULL,
                    previous_stock INTEGER NOT NULL,
                    new_stock INTEGER NOT NULL,
                    reference TEXT,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (product_id) REFERENCES products(id)
                );
            ');
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(50) NULL UNIQUE,
                name VARCHAR(150) NOT NULL,
                description TEXT NULL,
                cost_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                sale_price DECIMAL(10, 2) NOT NULL,
                stock INT NOT NULL DEFAULT 0,
                min_stock INT NOT NULL DEFAULT 5,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS store_sales (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cashbox_shift_id INT NULL,
                stay_id INT NULL,
                payment_type ENUM('cash', 'room_charge', 'card', 'yape', 'plin') NOT NULL DEFAULT 'cash',
                total_amount DECIMAL(10, 2) NOT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id),
                FOREIGN KEY (stay_id) REFERENCES stays(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS store_sale_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                store_sale_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity INT NOT NULL,
                unit_price DECIMAL(10, 2) NOT NULL,
                subtotal DECIMAL(10, 2) NOT NULL,
                FOREIGN KEY (store_sale_id) REFERENCES store_sales(id),
                FOREIGN KEY (product_id) REFERENCES products(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS inventory_movements (
                id INT AUTO_INCREMENT PRIMARY KEY,
                product_id INT NOT NULL,
                movement_type ENUM('in', 'out', 'adjustment') NOT NULL,
                quantity INT NOT NULL,
                previous_stock INT NOT NULL,
                new_stock INT NOT NULL,
                reference VARCHAR(255) NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (product_id) REFERENCES products(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec('
            DROP TABLE IF EXISTS inventory_movements;
            DROP TABLE IF EXISTS store_sale_items;
            DROP TABLE IF EXISTS store_sales;
            DROP TABLE IF EXISTS products;
        ');
    }
};
