<?php

declare(strict_types=1);

namespace Database\Seeds;

use PDO;
use RuntimeException;

final class DevelopmentSeeder
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function run(): void
    {
        // 1. Run Production Seeder for master baseline
        $prod_seeder = new ProductionSeeder($this->db, include_bootstrap_user: false);
        $prod_seeder->run();

        // 2. Development-only identities. These credentials never belong to the production seeder.
        $this->seedDevelopmentUsers();

        // 3. Populate Development-specific dummy test data into ALL tables
        $this->seedRooms();
        $this->seedGuests();
        $this->seedProductsAndInventory();
        $this->seedCashboxShiftsAndMovements();
        $this->seedStaysAndFolios();
        $this->seedExpensesAndOtherIncomes();
        $this->seedHousekeepingTasks();
        $this->seedInvoices();
    }

    private function seedDevelopmentUsers(): void
    {
        $users = [
            ['username' => 'admin', 'role' => 'admin', 'email' => 'admin@hotel.com', 'pass' => 'Admin123!', 'name' => 'Administrador Principal'],
            ['username' => 'receptionist', 'role' => 'receptionist', 'email' => 'recepcion@hotel.com', 'pass' => 'Recep123!', 'name' => 'Recepción Turno Mañana'],
            ['username' => 'housekeeper', 'role' => 'housekeeper', 'email' => 'limpieza@hotel.com', 'pass' => 'Clean123!', 'name' => 'Encargado Limpieza'],
            ['username' => 'accountant', 'role' => 'accountant', 'email' => 'contabilidad@hotel.com', 'pass' => 'Count123!', 'name' => 'Contador Principal'],
        ];

        $statement = $this->db->prepare(
            'INSERT INTO users (role_id, username, email, password_hash, full_name, is_active)
             VALUES ((SELECT id FROM roles WHERE name = :role), :username, :email, :password_hash, :full_name, 1)'
        );

        foreach ($users as $user) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
            $check->execute(['username' => $user['username']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();

            if ((int) $count === 0) {
                $statement->execute([
                    'role' => $user['role'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'password_hash' => password_hash($user['pass'], PASSWORD_DEFAULT),
                    'full_name' => $user['name'],
                ]);
            }
        }
    }

    private function seedRooms(): void
    {
        $rooms = [
            ['number' => '101', 'cat' => 'Simple', 'floor' => '1', 'status' => 'free'],
            ['number' => '102', 'cat' => 'Simple', 'floor' => '1', 'status' => 'occupied'],
            ['number' => '103', 'cat' => 'Matrimonial', 'floor' => '1', 'status' => 'cleaning'],
            ['number' => '201', 'cat' => 'Matrimonial', 'floor' => '2', 'status' => 'maintenance'],
            ['number' => '202', 'cat' => 'Jacuzzi', 'floor' => '2', 'status' => 'free'],
            ['number' => '203', 'cat' => 'Jacuzzi', 'floor' => '2', 'status' => 'reserved'],
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO rooms (room_category_id, number, floor, status)
             VALUES ((SELECT id FROM room_categories WHERE name = :cat), :number, :floor, :status)'
        );

        foreach ($rooms as $r) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM rooms WHERE number = :number');
            $check->execute(['number' => $r['number']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();
            if ((int) $count === 0) {
                $stmt->execute($r);
            }
        }
    }

    private function seedGuests(): void
    {
        $guests = [
            ['doc_type' => 'DNI', 'doc_num' => '72819283', 'name' => 'Carlos Mendoza Flores', 'phone' => '987654321', 'email' => 'carlos@gmail.com'],
            ['doc_type' => 'DNI', 'doc_num' => '45123987', 'name' => 'María Torres Vega', 'phone' => '912345678', 'email' => 'maria@hotmail.com'],
            ['doc_type' => 'RUC', 'doc_num' => '20601234567', 'name' => 'Corporación Turística SAC', 'phone' => '014238899', 'email' => 'facturacion@turismo.pe'],
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO guests (document_type, document_number, name, phone, email)
             VALUES (:doc_type, :doc_num, :name, :phone, :email)'
        );

        foreach ($guests as $g) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM guests WHERE document_number = :doc_num');
            $check->execute(['doc_num' => $g['doc_num']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();
            if ((int) $count === 0) {
                $stmt->execute($g);
            }
        }
    }

    private function seedProductsAndInventory(): void
    {
        $products = [
            ['sku' => 'PROD-001', 'name' => 'Agua Mineral San Luis 500ml', 'cost' => 1.00, 'sale' => 2.50, 'stock' => 50],
            ['sku' => 'PROD-002', 'name' => 'Gaseosa Coca-Cola 500ml', 'cost' => 2.00, 'sale' => 4.00, 'stock' => 40],
            ['sku' => 'PROD-003', 'name' => 'Papas Lays Clásicas 100g', 'cost' => 2.50, 'sale' => 5.00, 'stock' => 30],
            ['sku' => 'PROD-004', 'name' => 'Kit Dental Personal', 'cost' => 1.50, 'sale' => 3.50, 'stock' => 20],
            ['sku' => 'PROD-005', 'name' => 'Jabón Corporal 50g', 'cost' => 0.80, 'sale' => 2.00, 'stock' => 60],
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO products (sku, name, cost_price, sale_price, stock, min_stock, is_active)
             VALUES (:sku, :name, :cost, :sale, :stock, 5, 1)'
        );

        foreach ($products as $p) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM products WHERE sku = :sku');
            $check->execute(['sku' => $p['sku']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();
            if ((int) $count === 0) {
                $stmt->execute($p);
            }
        }
    }

    private function seedCashboxShiftsAndMovements(): void
    {
        $check = $this->db->query('SELECT COUNT(*) FROM cashbox_shifts');
        /** @var int|string $count */
        $count = $check !== false ? $check->fetchColumn() : 0;

        if ((int) $count === 0) {
            $opened_at = date('Y-m-d H:i:s', strtotime('-4 hours'));
            $admin_user_id = $this->findIdByValue('users', 'username', 'admin');

            $stmt = $this->db->prepare("
                INSERT INTO cashbox_shifts (user_id, opened_at, initial_cash, expected_cash, status, balance_status)
                VALUES (:user_id, :opened_at, 100.00, 245.00, 'open', 'pending')
            ");
            $stmt->execute(['user_id' => $admin_user_id, 'opened_at' => $opened_at]);
            $shift_id = (int) $this->db->lastInsertId();

            $movement_stmt = $this->db->prepare(
                'INSERT INTO cash_movements '
                . '(cashbox_shift_id, type, category, concept, amount, payment_method, created_by_user_id) '
                . 'VALUES (:cashbox_shift_id, :type, :category, :concept, :amount, :payment_method, :created_by_user_id)'
            );
            foreach ([
                ['type' => 'income', 'category' => 'accommodation', 'concept' => 'Pago Alojamiento Hab 102', 'amount' => 45.00],
                ['type' => 'income', 'category' => 'store', 'concept' => 'Venta Agua y Snack POS', 'amount' => 10.00],
                ['type' => 'expense', 'category' => 'petty_cash', 'concept' => 'Compra de lejía e insumos', 'amount' => 15.00],
            ] as $movement) {
                $movement_stmt->execute([
                    'cashbox_shift_id' => $shift_id,
                    'type' => $movement['type'],
                    'category' => $movement['category'],
                    'concept' => $movement['concept'],
                    'amount' => $movement['amount'],
                    'payment_method' => 'cash',
                    'created_by_user_id' => $admin_user_id,
                ]);
            }
        }
    }

    private function seedStaysAndFolios(): void
    {
        $check = $this->db->query('SELECT COUNT(*) FROM stays');
        /** @var int|string $count */
        $count = $check !== false ? $check->fetchColumn() : 0;

        if ((int) $count === 0) {
            $now = date('Y-m-d H:i:s');
            $expected = date('Y-m-d H:i:s', strtotime('+12 hours'));
            $room_id = $this->findIdByValue('rooms', 'number', '102');
            $guest_id = $this->findIdByValue('guests', 'document_number', '72819283');
            $tariff_id = $this->findTariffId('Matrimonial', '12 Horas');
            $admin_user_id = $this->findIdByValue('users', 'username', 'admin');

            $stmt = $this->db->prepare("
                INSERT INTO stays (room_id, room_tariff_id, guest_id, check_in_at, expected_check_out_at, base_price, applied_price, status, created_by_user_id)
                VALUES (:room_id, :room_tariff_id, :guest_id, :now, :expected, 45.00, 45.00, 'active', :created_by_user_id)
            ");
            $stmt->execute([
                'room_id' => $room_id,
                'room_tariff_id' => $tariff_id,
                'guest_id' => $guest_id,
                'now' => $now,
                'expected' => $expected,
                'created_by_user_id' => $admin_user_id,
            ]);
            $stay_id = (int) $this->db->lastInsertId();

            $folio_stmt = $this->db->prepare('INSERT INTO folios (stay_id, status) VALUES (:stay_id, :status)');
            $folio_stmt->execute(['stay_id' => $stay_id, 'status' => 'open']);
            $folio_id = (int) $this->db->lastInsertId();

            $charge_stmt = $this->db->prepare(
                'INSERT INTO folio_charges '
                . '(folio_id, charge_type, description, quantity, unit_price, total_amount, created_by_user_id) '
                . 'VALUES (:folio_id, :charge_type, :description, :quantity, :unit_price, :total_amount, :created_by_user_id)'
            );
            $charge_stmt->execute([
                'folio_id' => $folio_id,
                'charge_type' => 'accommodation',
                'description' => 'Tarifa 12 Horas Matrimonial',
                'quantity' => 1,
                'unit_price' => 45.00,
                'total_amount' => 45.00,
                'created_by_user_id' => $admin_user_id,
            ]);
            $charge_stmt->execute([
                'folio_id' => $folio_id,
                'charge_type' => 'store_item',
                'description' => 'Agua Mineral 500ml',
                'quantity' => 2,
                'unit_price' => 2.50,
                'total_amount' => 5.00,
                'created_by_user_id' => $admin_user_id,
            ]);

            $payment_stmt = $this->db->prepare(
                'INSERT INTO folio_payments (folio_id, payment_type, amount, payment_method, created_by_user_id) '
                . 'VALUES (:folio_id, :payment_type, :amount, :payment_method, :created_by_user_id)'
            );
            $payment_stmt->execute([
                'folio_id' => $folio_id,
                'payment_type' => 'advance',
                'amount' => 20.00,
                'payment_method' => 'cash',
                'created_by_user_id' => $admin_user_id,
            ]);
        }
    }

    private function seedExpensesAndOtherIncomes(): void
    {
        $check = $this->db->query('SELECT COUNT(*) FROM expenses');
        /** @var int|string $count */
        $count = $check !== false ? $check->fetchColumn() : 0;

        if ((int) $count === 0) {
            $shift_id = $this->findFirstId('cashbox_shifts');
            $admin_user_id = $this->findIdByValue('users', 'username', 'admin');

            $expense_stmt = $this->db->prepare(
                'INSERT INTO expenses '
                . '(cashbox_shift_id, category, concept, amount, payment_method, supplier_name, created_by_user_id) '
                . 'VALUES (:cashbox_shift_id, :category, :concept, :amount, :payment_method, :supplier_name, :created_by_user_id)'
            );
            $expense_stmt->execute([
                'cashbox_shift_id' => $shift_id,
                'category' => 'supplies',
                'concept' => 'Compra de lejía y desinfectante',
                'amount' => 15.00,
                'payment_method' => 'cash',
                'supplier_name' => 'Distribuidora Lima',
                'created_by_user_id' => $admin_user_id,
            ]);

            $income_stmt = $this->db->prepare(
                'INSERT INTO other_incomes '
                . '(cashbox_shift_id, category, concept, amount, payment_method, created_by_user_id) '
                . 'VALUES (:cashbox_shift_id, :category, :concept, :amount, :payment_method, :created_by_user_id)'
            );
            $income_stmt->execute([
                'cashbox_shift_id' => $shift_id,
                'category' => 'parking',
                'concept' => 'Alquiler Cochera Vehículo Externo',
                'amount' => 10.00,
                'payment_method' => 'cash',
                'created_by_user_id' => $admin_user_id,
            ]);
        }
    }

    private function seedHousekeepingTasks(): void
    {
        $check = $this->db->query('SELECT COUNT(*) FROM housekeeping_tasks');
        /** @var int|string $count */
        $count = $check !== false ? $check->fetchColumn() : 0;

        if ((int) $count === 0) {
            $admin_user_id = $this->findIdByValue('users', 'username', 'admin');
            $task_stmt = $this->db->prepare(
                'INSERT INTO housekeeping_tasks (room_id, task_type, status, priority, created_by_user_id) '
                . 'VALUES (:room_id, :task_type, :status, :priority, :created_by_user_id)'
            );
            $task_stmt->execute([
                'room_id' => $this->findIdByValue('rooms', 'number', '103'),
                'task_type' => 'checkout_cleaning',
                'status' => 'pending',
                'priority' => 'normal',
                'created_by_user_id' => $admin_user_id,
            ]);
            $task_stmt->execute([
                'room_id' => $this->findIdByValue('rooms', 'number', '201'),
                'task_type' => 'maintenance_repair',
                'status' => 'in_progress',
                'priority' => 'high',
                'created_by_user_id' => $admin_user_id,
            ]);
        }
    }

    private function seedInvoices(): void
    {
        $check = $this->db->query('SELECT COUNT(*) FROM invoices');
        /** @var int|string $count */
        $count = $check !== false ? $check->fetchColumn() : 0;

        if ((int) $count === 0) {
            $admin_user_id = $this->findIdByValue('users', 'username', 'admin');
            $stay_id = $this->findFirstId('stays');
            $invoice_stmt = $this->db->prepare(
                'INSERT INTO invoices '
                . '(stay_id, document_type, series, number, full_number, customer_document_type, '
                . 'customer_document_number, customer_name, taxable_amount, igv_amount, total_amount, '
                . 'sunat_status, created_by_user_id) '
                . 'VALUES (:stay_id, :document_type, :series, :number, :full_number, :customer_document_type, '
                . ':customer_document_number, :customer_name, :taxable_amount, :igv_amount, :total_amount, '
                . ':sunat_status, :created_by_user_id)'
            );
            $invoice_stmt->execute([
                'stay_id' => $stay_id,
                'document_type' => 'BOLETA',
                'series' => 'B001',
                'number' => 1,
                'full_number' => 'B001-00000001',
                'customer_document_type' => 'DNI',
                'customer_document_number' => '72819283',
                'customer_name' => 'Carlos Mendoza Flores',
                'taxable_amount' => 38.14,
                'igv_amount' => 6.86,
                'total_amount' => 45.00,
                'sunat_status' => 'simulated',
                'created_by_user_id' => $admin_user_id,
            ]);
            $invoice_id = (int) $this->db->lastInsertId();

            $item_stmt = $this->db->prepare(
                'INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, subtotal) '
                . 'VALUES (:invoice_id, :description, :quantity, :unit_price, :subtotal)'
            );
            $item_stmt->execute([
                'invoice_id' => $invoice_id,
                'description' => 'Alojamiento Habitación 102 (12 Horas)',
                'quantity' => 1,
                'unit_price' => 45.00,
                'subtotal' => 45.00,
            ]);
        }
    }

    private function findIdByValue(string $table_name, string $column_name, string $value): int
    {
        $allowed_tables = ['users', 'rooms', 'guests'];
        if (!in_array($table_name, $allowed_tables, true)) {
            throw new RuntimeException('Tabla de seed no permitida.');
        }

        $allowed_columns = ['username', 'number', 'document_number'];
        if (!in_array($column_name, $allowed_columns, true)) {
            throw new RuntimeException('Columna de seed no permitida.');
        }

        $statement = $this->db->prepare(
            'SELECT id FROM ' . $table_name . ' WHERE ' . $column_name . ' = :value LIMIT 1'
        );
        $statement->execute(['value' => $value]);
        /** @var int|string|false $id */
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("No se encontró la referencia de seed {$table_name}.{$column_name}.");
        }

        return (int) $id;
    }

    private function findTariffId(string $category_name, string $tariff_name): int
    {
        $statement = $this->db->prepare(
            'SELECT rt.id FROM room_tariffs rt '
            . 'INNER JOIN room_categories rc ON rc.id = rt.room_category_id '
            . 'WHERE rc.name = :category_name AND rt.name = :tariff_name LIMIT 1'
        );
        $statement->execute([
            'category_name' => $category_name,
            'tariff_name' => $tariff_name,
        ]);
        /** @var int|string|false $id */
        $id = $statement->fetchColumn();

        if ($id === false) {
            throw new RuntimeException('No se encontró la tarifa de seed.');
        }

        return (int) $id;
    }

    private function findFirstId(string $table_name): int
    {
        $allowed_tables = ['cashbox_shifts', 'stays'];
        if (!in_array($table_name, $allowed_tables, true)) {
            throw new RuntimeException('Tabla de seed no permitida.');
        }

        $statement = $this->db->query(
            'SELECT id FROM ' . $table_name . ' ORDER BY id ASC LIMIT 1'
        );
        if ($statement === false) {
            throw new RuntimeException('No se pudo consultar una referencia de seed.');
        }

        /** @var int|string|false $id */
        $id = $statement->fetchColumn();
        if ($id === false) {
            throw new RuntimeException("No existen filas en {$table_name} para el seed.");
        }

        return (int) $id;
    }
}
