<?php

declare(strict_types=1);

return new class {
    public function up(PDO $db): void
    {
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS housekeeping_tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    room_id INTEGER NOT NULL,
                    task_type TEXT DEFAULT "checkout_cleaning",
                    status TEXT DEFAULT "pending",
                    priority TEXT DEFAULT "normal",
                    assigned_to_user_id INTEGER,
                    started_at TEXT,
                    completed_at TEXT,
                    notes TEXT,
                    created_by_user_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (room_id) REFERENCES rooms(id)
                );
            ');
            return;
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS housekeeping_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                room_id INT NOT NULL,
                task_type ENUM('checkout_cleaning', 'deep_cleaning', 'maintenance_repair', 'routine_inspection') NOT NULL DEFAULT 'checkout_cleaning',
                status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
                priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
                assigned_to_user_id INT NULL,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                notes TEXT NULL,
                created_by_user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (room_id) REFERENCES rooms(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS housekeeping_tasks;');
    }
};
