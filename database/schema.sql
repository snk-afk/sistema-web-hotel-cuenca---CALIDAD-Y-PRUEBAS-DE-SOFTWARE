-- =============================================================================
-- CS-HOTEL - Esquema Consolidado de Base de Datos (MySQL / MariaDB)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------------------------------
-- 1. CONTROL DE MIGRACIONES (Migrator)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `migrations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `migration` VARCHAR(255) NOT NULL UNIQUE,
    `batch` INT NOT NULL,
    `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. SEGURIDAD: USUARIOS, ROLES, PERMISOS Y AUDITORÍA
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `roles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `key_name` VARCHAR(100) NOT NULL UNIQUE,
    `description` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
    `role_id` INT NOT NULL,
    `permission_id` INT NOT NULL,
    PRIMARY KEY (`role_id`, `permission_id`),
    CONSTRAINT `fk_role_permissions_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_role_permissions_permission_id` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT NOT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(150) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_name` VARCHAR(100) NOT NULL,
    `entity_id` INT NOT NULL,
    `old_values` JSON NULL,
    `new_values` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_audit_logs_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_login_attempts` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `username_hash` CHAR(64) NOT NULL,
    `source_hash` CHAR(64) NOT NULL,
    `attempted_at` DATETIME NOT NULL,
    `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_auth_login_attempts_lookup` (`username_hash`, `source_hash`, `attempted_at`),
    INDEX `idx_auth_login_attempts_cleanup` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. HABITACIONES, CATEGORÍAS Y TARIFAS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `room_categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rooms` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_category_id` INT NOT NULL,
    `number` VARCHAR(20) NOT NULL UNIQUE,
    `status` ENUM('free', 'occupied', 'cleaning', 'maintenance', 'reserved') NOT NULL DEFAULT 'free',
    `floor` VARCHAR(10) NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_rooms_room_category_id` FOREIGN KEY (`room_category_id`) REFERENCES `room_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_tariffs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_category_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `duration_hours` INT NOT NULL,
    `price` DECIMAL(10, 2) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_room_tariffs_room_category_id` FOREIGN KEY (`room_category_id`) REFERENCES `room_categories` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. HUÉSPEDES, ESTANCIAS Y RESERVAS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `guests` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `document_type` ENUM('DNI', 'RUC', 'CE', 'PASAPORTE') NOT NULL,
    `document_number` VARCHAR(20) NOT NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `address` TEXT NULL,
    `phone` VARCHAR(30) NULL,
    `email` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stays` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `room_tariff_id` INT NOT NULL,
    `guest_id` INT NULL,
    `check_in_at` DATETIME NOT NULL,
    `expected_check_out_at` DATETIME NOT NULL,
    `actual_check_out_at` DATETIME NULL,
    `base_price` DECIMAL(10, 2) NOT NULL,
    `applied_price` DECIMAL(10, 2) NOT NULL,
    `discount_reason` VARCHAR(255) NULL,
    `status` ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_stays_guest_id` (`guest_id`),
    INDEX `idx_stays_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_stays_room_id` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`),
    CONSTRAINT `fk_stays_room_tariff_id` FOREIGN KEY (`room_tariff_id`) REFERENCES `room_tariffs` (`id`),
    CONSTRAINT `fk_stays_guest_id` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_stays_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stay_extensions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stay_id` INT NOT NULL,
    `added_hours` INT NOT NULL,
    `added_price` DECIMAL(10, 2) NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_stay_extensions_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_stay_extensions_stay_id` FOREIGN KEY (`stay_id`) REFERENCES `stays` (`id`),
    CONSTRAINT `fk_stay_extensions_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `stay_guests` (
    `stay_id` INT NOT NULL,
    `guest_id` INT NOT NULL,
    `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (`stay_id`, `guest_id`),
    CONSTRAINT `fk_stay_guests_stay_id` FOREIGN KEY (`stay_id`) REFERENCES `stays` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_stay_guests_guest_id` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reservations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `guest_id` INT NOT NULL,
    `room_tariff_id` INT NOT NULL,
    `start_time` DATETIME NOT NULL,
    `end_time` DATETIME NOT NULL,
    `deposit_amount` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('pending', 'confirmed', 'checked_in', 'cancelled', 'no_show') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_reservations_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_reservations_room_id` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`),
    CONSTRAINT `fk_reservations_guest_id` FOREIGN KEY (`guest_id`) REFERENCES `guests` (`id`),
    CONSTRAINT `fk_reservations_room_tariff_id` FOREIGN KEY (`room_tariff_id`) REFERENCES `room_tariffs` (`id`),
    CONSTRAINT `fk_reservations_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. CAJAS, TURNOS Y MOVIMIENTOS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `cashbox_shifts` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `opened_at` DATETIME NOT NULL,
    `closed_at` DATETIME NULL,
    `initial_cash` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `expected_cash` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `real_cash_declared` DECIMAL(10, 2) NULL,
    `difference` DECIMAL(10, 2) NULL,
    `expected_yape` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `expected_plin` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `expected_card` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `expected_transfer` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `real_yape_declared` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `real_plin_declared` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `real_card_declared` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `real_transfer_declared` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `difference_yape` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `difference_plin` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `difference_card` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `difference_transfer` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    `balance_status` ENUM('balanced', 'surplus', 'shortage', 'pending') NOT NULL DEFAULT 'pending',
    `notes` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_cashbox_shifts_user_id` (`user_id`),
    CONSTRAINT `fk_cashbox_shifts_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cash_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cashbox_shift_id` INT NOT NULL,
    `type` ENUM('income', 'expense') NOT NULL,
    `category` VARCHAR(50) NOT NULL,
    `concept` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    `reference_id` INT NULL,
    `reference_type` VARCHAR(50) NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cash_movements_created_by_user_id` (`created_by_user_id`),
    INDEX `idx_cash_movements_reference` (`reference_type`, `reference_id`),
    CONSTRAINT `fk_cash_movements_cashbox_shift_id` FOREIGN KEY (`cashbox_shift_id`) REFERENCES `cashbox_shifts` (`id`),
    CONSTRAINT `fk_cash_movements_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. PAGOS, CANCELACIONES Y REEMBOLSOS DE RESERVAS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reservation_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reservation_id` INT NOT NULL,
    `cashbox_shift_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL,
    `reference_number` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (`amount` > 0),
    INDEX `idx_reservation_payments_reservation_id` (`reservation_id`),
    INDEX `idx_reservation_payments_cashbox_shift_id` (`cashbox_shift_id`),
    INDEX `idx_reservation_payments_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_reservation_payments_reservation_id` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reservation_payments_cashbox_shift_id` FOREIGN KEY (`cashbox_shift_id`) REFERENCES `cashbox_shifts` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reservation_payments_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reservation_cancellations` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reservation_id` INT NOT NULL UNIQUE,
    `event_type` ENUM('cancelled', 'no_show') NOT NULL,
    `policy_code` ENUM('full_refund', 'partial_refund', 'no_refund') NOT NULL,
    `refund_percentage` DECIMAL(5, 2) NOT NULL,
    `gross_paid_amount` DECIMAL(10, 2) NOT NULL,
    `refund_amount` DECIMAL(10, 2) NOT NULL,
    `penalty_amount` DECIMAL(10, 2) NOT NULL,
    `status` ENUM('pending_refund', 'completed', 'no_refund') NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `requested_by_user_id` INT NOT NULL,
    `approved_by_user_id` INT NULL,
    `approved_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (`refund_percentage` >= 0 AND `refund_percentage` <= 100),
    CHECK (`gross_paid_amount` >= 0 AND `refund_amount` >= 0 AND `penalty_amount` >= 0),
    INDEX `idx_reservation_cancellations_status_created` (`status`, `created_at`),
    CONSTRAINT `fk_reservation_cancellations_reservation_id` FOREIGN KEY (`reservation_id`) REFERENCES `reservations` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reservation_cancellations_requested_by_user_id` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reservation_cancellations_approved_by_user_id` FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `reservation_refunds` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cancellation_id` INT NOT NULL,
    `reservation_payment_id` INT NOT NULL UNIQUE,
    `cashbox_shift_id` INT NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL,
    `reference_number` VARCHAR(100) NULL,
    `executed_by_user_id` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CHECK (`amount` > 0),
    INDEX `idx_reservation_refunds_cancellation_id` (`cancellation_id`),
    CONSTRAINT `fk_reservation_refunds_cancellation_id` FOREIGN KEY (`cancellation_id`) REFERENCES `reservation_cancellations` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reservation_refunds_reservation_payment_id` FOREIGN KEY (`reservation_payment_id`) REFERENCES `reservation_payments` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reservation_refunds_cashbox_shift_id` FOREIGN KEY (`cashbox_shift_id`) REFERENCES `cashbox_shifts` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_reservation_refunds_executed_by_user_id` FOREIGN KEY (`executed_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. FOLIOS, CARGOS, PAGOS Y TRASLADOS DE HABITACIÓN
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `folios` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stay_id` INT NOT NULL UNIQUE,
    `status` ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_folios_stay_id` FOREIGN KEY (`stay_id`) REFERENCES `stays` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `folio_charges` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `folio_id` INT NOT NULL,
    `charge_type` ENUM('accommodation', 'store_item', 'service', 'extension', 'other', 'penalty', 'adjustment') NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `is_reversal` TINYINT(1) NOT NULL DEFAULT 0,
    `reversal_reason` VARCHAR(255) NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_folio_charges_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_folio_charges_folio_id` FOREIGN KEY (`folio_id`) REFERENCES `folios` (`id`),
    CONSTRAINT `fk_folio_charges_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `folio_payments` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `folio_id` INT NOT NULL,
    `cashbox_shift_id` INT NULL,
    `reservation_payment_id` INT NULL,
    `payment_type` ENUM('advance', 'checkout_payment', 'refund') NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    `reference_number` VARCHAR(100) NULL,
    `notes` TEXT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_folio_payments_created_by_user_id` (`created_by_user_id`),
    INDEX `idx_folio_payments_cashbox_shift_id` (`cashbox_shift_id`),
    UNIQUE INDEX `uq_folio_payments_reservation_payment_id` (`reservation_payment_id`),
    CONSTRAINT `fk_folio_payments_folio_id` FOREIGN KEY (`folio_id`) REFERENCES `folios` (`id`),
    CONSTRAINT `fk_folio_payments_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_folio_payments_cashbox_shift_id` FOREIGN KEY (`cashbox_shift_id`) REFERENCES `cashbox_shifts` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_folio_payments_reservation_payment_id` FOREIGN KEY (`reservation_payment_id`) REFERENCES `reservation_payments` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `room_transfers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stay_id` INT NOT NULL,
    `from_room_id` INT NOT NULL,
    `to_room_id` INT NOT NULL,
    `reason` TEXT NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_room_transfers_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_room_transfers_stay_id` FOREIGN KEY (`stay_id`) REFERENCES `stays` (`id`),
    CONSTRAINT `fk_room_transfers_from_room_id` FOREIGN KEY (`from_room_id`) REFERENCES `rooms` (`id`),
    CONSTRAINT `fk_room_transfers_to_room_id` FOREIGN KEY (`to_room_id`) REFERENCES `rooms` (`id`),
    CONSTRAINT `fk_room_transfers_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 8. TIENDA, PRODUCTOS, VENTAS E INVENTARIO
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sku` VARCHAR(50) NULL UNIQUE,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `cost_price` DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    `sale_price` DECIMAL(10, 2) NOT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `min_stock` INT NOT NULL DEFAULT 5,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_sales` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cashbox_shift_id` INT NULL,
    `stay_id` INT NULL,
    `idempotency_key` CHAR(64) NULL,
    `payment_type` ENUM('cash', 'room_charge', 'card', 'yape', 'plin') NOT NULL DEFAULT 'cash',
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_store_sales_created_by_user_id` (`created_by_user_id`),
    UNIQUE INDEX `idx_store_sales_idempotency_key` (`idempotency_key`),
    CONSTRAINT `fk_store_sales_cashbox_shift_id` FOREIGN KEY (`cashbox_shift_id`) REFERENCES `cashbox_shifts` (`id`),
    CONSTRAINT `fk_store_sales_stay_id` FOREIGN KEY (`stay_id`) REFERENCES `stays` (`id`),
    CONSTRAINT `fk_store_sales_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `store_sale_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `store_sale_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    CONSTRAINT `fk_store_sale_items_store_sale_id` FOREIGN KEY (`store_sale_id`) REFERENCES `store_sales` (`id`),
    CONSTRAINT `fk_store_sale_items_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inventory_movements` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `product_id` INT NOT NULL,
    `movement_type` ENUM('in', 'out', 'adjustment') NOT NULL,
    `quantity` INT NOT NULL,
    `previous_stock` INT NOT NULL,
    `new_stock` INT NOT NULL,
    `reference` VARCHAR(255) NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_inventory_movements_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_inventory_movements_product_id` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
    CONSTRAINT `fk_inventory_movements_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 9. GASTOS Y OTROS INGRESOS
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `expenses` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cashbox_shift_id` INT NOT NULL,
    `category` ENUM('maintenance', 'supplies', 'utilities', 'petty_cash', 'other_expense') NOT NULL DEFAULT 'petty_cash',
    `concept` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    `supplier_name` VARCHAR(150) NULL,
    `receipt_number` VARCHAR(50) NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_expenses_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_expenses_cashbox_shift_id` FOREIGN KEY (`cashbox_shift_id`) REFERENCES `cashbox_shifts` (`id`),
    CONSTRAINT `fk_expenses_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 10. LIMPIEZA Y MANTENIMIENTO (HOUSEKEEPING)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `other_incomes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `cashbox_shift_id` INT NOT NULL,
    `category` ENUM('parking', 'laundry_external', 'events', 'penalty_damage', 'recycling', 'other_income') NOT NULL DEFAULT 'other_income',
    `concept` VARCHAR(255) NOT NULL,
    `amount` DECIMAL(10, 2) NOT NULL,
    `payment_method` ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    `notes` TEXT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_other_incomes_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_other_incomes_cashbox_shift_id` FOREIGN KEY (`cashbox_shift_id`) REFERENCES `cashbox_shifts` (`id`),
    CONSTRAINT `fk_other_incomes_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `housekeeping_tasks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `room_id` INT NOT NULL,
    `task_type` ENUM('checkout_cleaning', 'deep_cleaning', 'maintenance_repair', 'routine_inspection') NOT NULL DEFAULT 'checkout_cleaning',
    `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    `assigned_to_user_id` INT NULL,
    `started_at` DATETIME NULL,
    `completed_at` DATETIME NULL,
    `notes` TEXT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_housekeeping_tasks_created_by_user_id` (`created_by_user_id`),
    INDEX `idx_housekeeping_tasks_assigned_to_user_id` (`assigned_to_user_id`),
    CONSTRAINT `fk_housekeeping_tasks_room_id` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`),
    CONSTRAINT `fk_housekeeping_tasks_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
    CONSTRAINT `fk_housekeeping_tasks_assigned_to_user_id` FOREIGN KEY (`assigned_to_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 11. FACTURACIÓN ELECTRÓNICA, SERIES, COMPROBANTES Y TRANSMISIONES
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invoice_series` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `document_type` ENUM('BOLETA', 'FACTURA', 'NOTA_VENTA') NOT NULL,
    `series` VARCHAR(10) NOT NULL UNIQUE,
    `current_number` INT NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `stay_id` INT NULL,
    `store_sale_id` INT NULL,
    `document_type` ENUM('BOLETA', 'FACTURA', 'NOTA_VENTA') NOT NULL,
    `series` VARCHAR(10) NOT NULL,
    `number` INT NOT NULL,
    `full_number` VARCHAR(20) NOT NULL UNIQUE,
    `customer_document_type` ENUM('DNI', 'RUC', 'CE', 'PASAPORTE') NOT NULL,
    `customer_document_number` VARCHAR(20) NOT NULL,
    `customer_name` VARCHAR(150) NOT NULL,
    `customer_address` TEXT NULL,
    `taxable_amount` DECIMAL(10, 2) NOT NULL,
    `igv_amount` DECIMAL(10, 2) NOT NULL,
    `total_amount` DECIMAL(10, 2) NOT NULL,
    `sunat_status` ENUM('simulated', 'accepted', 'rejected', 'pending') NOT NULL DEFAULT 'pending',
    `sunat_response_code` VARCHAR(20) NULL,
    `pdf_url` TEXT NULL,
    `voucher_url` VARCHAR(255) NULL,
    `xml_url` TEXT NULL,
    `created_by_user_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_invoices_created_by_user_id` (`created_by_user_id`),
    CONSTRAINT `fk_invoices_stay_id` FOREIGN KEY (`stay_id`) REFERENCES `stays` (`id`),
    CONSTRAINT `fk_invoices_store_sale_id` FOREIGN KEY (`store_sale_id`) REFERENCES `store_sales` (`id`),
    CONSTRAINT `fk_invoices_created_by_user_id` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_items` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL,
    `description` VARCHAR(255) NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10, 2) NOT NULL,
    `subtotal` DECIMAL(10, 2) NOT NULL,
    CONSTRAINT `fk_invoice_items_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `invoice_transmissions` (
    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
    `invoice_id` INT NOT NULL UNIQUE,
    `provider` VARCHAR(50) NOT NULL,
    `idempotency_key` CHAR(64) NOT NULL UNIQUE,
    `status` ENUM('pending', 'sent', 'accepted', 'rejected', 'failed') NOT NULL DEFAULT 'pending',
    `provider_code` VARCHAR(100) NULL,
    `provider_message` VARCHAR(500) NULL,
    `attempts` INT NOT NULL DEFAULT 0,
    `last_attempt_at` DATETIME NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CHECK (`attempts` >= 0),
    INDEX `idx_invoice_transmissions_status_attempt` (`status`, `last_attempt_at`),
    CONSTRAINT `fk_invoice_transmissions_invoice_id` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
