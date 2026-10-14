<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS invoice_series (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    document_type TEXT NOT NULL,
                    series TEXT NOT NULL UNIQUE,
                    current_number INTEGER DEFAULT 0,
                    is_active INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                );

                CREATE TABLE IF NOT EXISTS invoices (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    stay_id INTEGER,
                    store_sale_id INTEGER,
                    document_type TEXT NOT NULL,
                    series TEXT NOT NULL,
                    number INTEGER NOT NULL,
                    full_number TEXT NOT NULL UNIQUE,
                    customer_document_type TEXT NOT NULL,
                    customer_document_number TEXT NOT NULL,
                    customer_name TEXT NOT NULL,
                    customer_address TEXT,
                    taxable_amount REAL NOT NULL,
                    igv_amount REAL NOT NULL,
                    total_amount REAL NOT NULL,
                    sunat_status TEXT DEFAULT "pending",
                    sunat_response_code TEXT,
                    pdf_url TEXT,
                    xml_url TEXT,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (stay_id) REFERENCES stays(id),
                    FOREIGN KEY (store_sale_id) REFERENCES store_sales(id)
                );

                CREATE TABLE IF NOT EXISTS invoice_items (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    invoice_id INTEGER NOT NULL,
                    description TEXT NOT NULL,
                    quantity INTEGER DEFAULT 1,
                    unit_price REAL NOT NULL,
                    subtotal REAL NOT NULL,
                    FOREIGN KEY (invoice_id) REFERENCES invoices(id)
                );
            ');
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS invoice_series (
                id INT AUTO_INCREMENT PRIMARY KEY,
                document_type ENUM('BOLETA', 'FACTURA', 'NOTA_VENTA') NOT NULL,
                series VARCHAR(10) NOT NULL UNIQUE,
                current_number INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS invoices (
                id INT AUTO_INCREMENT PRIMARY KEY,
                stay_id INT NULL,
                store_sale_id INT NULL,
                document_type ENUM('BOLETA', 'FACTURA', 'NOTA_VENTA') NOT NULL,
                series VARCHAR(10) NOT NULL,
                number INT NOT NULL,
                full_number VARCHAR(20) NOT NULL UNIQUE,
                customer_document_type ENUM('DNI', 'RUC', 'CE', 'PASAPORTE') NOT NULL,
                customer_document_number VARCHAR(20) NOT NULL,
                customer_name VARCHAR(150) NOT NULL,
                customer_address TEXT NULL,
                taxable_amount DECIMAL(10, 2) NOT NULL,
                igv_amount DECIMAL(10, 2) NOT NULL,
                total_amount DECIMAL(10, 2) NOT NULL,
                sunat_status ENUM('simulated', 'accepted', 'rejected', 'pending') NOT NULL DEFAULT 'pending',
                sunat_response_code VARCHAR(20) NULL,
                pdf_url TEXT NULL,
                xml_url TEXT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (stay_id) REFERENCES stays(id),
                FOREIGN KEY (store_sale_id) REFERENCES store_sales(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS invoice_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                invoice_id INT NOT NULL,
                description VARCHAR(255) NOT NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10, 2) NOT NULL,
                subtotal DECIMAL(10, 2) NOT NULL,
                FOREIGN KEY (invoice_id) REFERENCES invoices(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec('
            DROP TABLE IF EXISTS invoice_items;
            DROP TABLE IF EXISTS invoices;
            DROP TABLE IF EXISTS invoice_series;
        ');
    }
};
