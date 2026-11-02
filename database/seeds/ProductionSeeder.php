<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Support\Config;
use PDO;
use RuntimeException;

final class ProductionSeeder
{
    private PDO $db;
    private bool $include_bootstrap_user;

    public function __construct(PDO $db, bool $include_bootstrap_user = true)
    {
        $this->db = $db;
        $this->include_bootstrap_user = $include_bootstrap_user;
    }

    public function run(): void
    {
        $this->seedRolesAndPermissions();
        $this->seedDefaultUsers();
        $this->seedRoomCategoriesAndTariffs();
        $this->seedInvoiceSeries();
    }

    private function seedRolesAndPermissions(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'Administrador General'],
            ['name' => 'receptionist', 'description' => 'Recepcionista de Hotel'],
            ['name' => 'housekeeper', 'description' => 'Personal de Limpieza / Mucama'],
            ['name' => 'accountant', 'description' => 'Contador / Auditor'],
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO roles (name, description) VALUES (:name, :description)'
        );

        foreach ($roles as $role) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM roles WHERE name = :name');
            $check->execute(['name' => $role['name']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();
            if ((int) $count === 0) {
                $stmt->execute($role);
            }
        }

        $permissions = [
            ['key_name' => 'rooms.view', 'description' => 'Ver mapa de habitaciones'],
            ['key_name' => 'rooms.manage', 'description' => 'Gestionar habitaciones, categorías y tarifas'],
            ['key_name' => 'rooms.occupy', 'description' => 'Hospedar y hacer check-in'],
            ['key_name' => 'rooms.transfer', 'description' => 'Transferir habitaciones'],
            ['key_name' => 'guests.manage', 'description' => 'Gestionar huéspedes'],
            ['key_name' => 'reservations.manage', 'description' => 'Gestionar reservas'],
            ['key_name' => 'reservations.refund', 'description' => 'Autorizar y ejecutar devoluciones de reservas'],
            ['key_name' => 'cashbox.manage', 'description' => 'Abrir y cerrar turnos de caja'],
            ['key_name' => 'cashbox.movement', 'description' => 'Registrar movimientos de caja'],
            ['key_name' => 'store.manage', 'description' => 'Gestionar productos e inventario'],
            ['key_name' => 'store.sell', 'description' => 'Vender productos POS'],
            ['key_name' => 'expenses.manage', 'description' => 'Registrar egresos'],
            ['key_name' => 'incomes.manage', 'description' => 'Registrar otros ingresos'],
            ['key_name' => 'housekeeping.manage', 'description' => 'Gestionar tareas de limpieza'],
            ['key_name' => 'users.manage', 'description' => 'Administrar usuarios y roles'],
            ['key_name' => 'invoices.issue', 'description' => 'Emitir comprobantes SUNAT'],
            ['key_name' => 'reports.view', 'description' => 'Ver reportes ejecutivos'],
            ['key_name' => 'folios.charge', 'description' => 'Agregar cargos a folio'],
            ['key_name' => 'folios.reverse', 'description' => 'Revertir cargos'],
        ];

        $perm_stmt = $this->db->prepare(
            'INSERT INTO permissions (key_name, description) VALUES (:key_name, :description)'
        );

        foreach ($permissions as $perm) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM permissions WHERE key_name = :key_name');
            $check->execute(['key_name' => $perm['key_name']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();
            if ((int) $count === 0) {
                $perm_stmt->execute($perm);
            }
        }

        // Assign permissions to roles (role_permissions matrix)
        $this->seedRolePermissions();
    }

    /**
     * Populate role_permissions with the canonical permission set.
     * Admin gets all permissions; other roles get their functional subset.
     */
    private function seedRolePermissions(): void
    {
        $role_permissions = [
            'admin' => null, // null = all permissions
            'receptionist' => [
                'rooms.view', 'rooms.manage', 'rooms.occupy', 'rooms.transfer',
                'guests.manage', 'reservations.manage',
                'cashbox.manage', 'cashbox.movement',
                'store.sell', 'housekeeping.manage',
                'invoices.issue', 'reports.view',
                'folios.charge', 'folios.reverse',
            ],
            'housekeeper' => [
                'rooms.view', 'housekeeping.manage',
            ],
            'accountant' => [
                'rooms.view', 'cashbox.manage', 'cashbox.movement',
                'expenses.manage', 'incomes.manage',
                'reservations.refund',
                'invoices.issue', 'reports.view', 'users.manage',
            ],
        ];

        $check_stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM role_permissions rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.name = :role_name AND p.key_name = :perm_name'
        );

        $insert_stmt = $this->db->prepare(
            'INSERT INTO role_permissions (role_id, permission_id)
             SELECT r.id, p.id FROM roles r, permissions p
             WHERE r.name = :role_name AND p.key_name = :perm_name'
        );

        /** @var array<int, string>|false $all_perms */
        $all_perms = $this->db->query('SELECT key_name FROM permissions')->fetchAll(PDO::FETCH_COLUMN);
        if ($all_perms === false) {
            $all_perms = [];
        }

        foreach ($role_permissions as $role_name => $perms) {
            $perm_list = $perms ?? $all_perms;
            foreach ($perm_list as $perm_name) {
                $check_stmt->execute(['role_name' => $role_name, 'perm_name' => $perm_name]);
                /** @var int|string $count */
                $count = $check_stmt->fetchColumn();
                if ((int) $count === 0) {
                    $insert_stmt->execute(['role_name' => $role_name, 'perm_name' => $perm_name]);
                }
            }
        }
    }

    private function seedDefaultUsers(): void
    {
        if (!$this->include_bootstrap_user) {
            return;
        }

        $users = [$this->productionBootstrapUser()];

        $stmt = $this->db->prepare(
            'INSERT INTO users (role_id, username, email, password_hash, full_name, is_active)
             VALUES ((SELECT id FROM roles WHERE name = :role), :username, :email, :password_hash, :full_name, 1)'
        );

        foreach ($users as $u) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
            $check->execute(['username' => $u['username']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();

            if ((int) $count === 0) {
                $stmt->execute([
                    'role' => $u['role'],
                    'username' => $u['username'],
                    'email' => $u['email'],
                    'password_hash' => password_hash($u['pass'], PASSWORD_DEFAULT),
                    'full_name' => $u['name'],
                ]);
            }
        }
    }

    /**
     * @return array{username: string, role: string, email: string, pass: string, name: string}
     */
    private function productionBootstrapUser(): array
    {
        $username = trim(Config::string('BOOTSTRAP_ADMIN_USERNAME'));
        $email = trim(Config::string('BOOTSTRAP_ADMIN_EMAIL'));
        $password = Config::string('BOOTSTRAP_ADMIN_PASSWORD');
        $name = trim(Config::string('BOOTSTRAP_ADMIN_NAME'));

        if ($username === '' || $email === '' || $name === '' || strlen($password) < 12) {
            throw new RuntimeException(
                'Faltan credenciales de bootstrap seguras para el administrador de producción.'
            );
        }

        return [
            'username' => $username,
            'role' => 'admin',
            'email' => $email,
            'pass' => $password,
            'name' => $name,
        ];
    }

    private function seedRoomCategoriesAndTariffs(): void
    {
        $categories = [
            ['name' => 'Simple', 'description' => 'Habitación individual básica'],
            ['name' => 'Matrimonial', 'description' => 'Habitación doble con cama matrimonial'],
            ['name' => 'Jacuzzi', 'description' => 'Suite de lujo con tina de hidromasaje'],
        ];

        $cat_stmt = $this->db->prepare('INSERT INTO room_categories (name, description) VALUES (:name, :description)');
        foreach ($categories as $cat) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM room_categories WHERE name = :name');
            $check->execute(['name' => $cat['name']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();
            if ((int) $count === 0) {
                $cat_stmt->execute($cat);
            }
        }

        $tariffs = [
            ['cat' => 'Simple', 'name' => '12 Horas', 'hours' => 12, 'price' => 35.00],
            ['cat' => 'Simple', 'name' => '24 Horas', 'hours' => 24, 'price' => 60.00],
            ['cat' => 'Matrimonial', 'name' => '12 Horas', 'hours' => 12, 'price' => 45.00],
            ['cat' => 'Matrimonial', 'name' => '24 Horas', 'hours' => 24, 'price' => 80.00],
            ['cat' => 'Jacuzzi', 'name' => '12 Horas', 'hours' => 12, 'price' => 80.00],
            ['cat' => 'Jacuzzi', 'name' => '24 Horas', 'hours' => 24, 'price' => 140.00],
        ];

        $tariff_stmt = $this->db->prepare(
            'INSERT INTO room_tariffs (room_category_id, name, duration_hours, price)
             VALUES ((SELECT id FROM room_categories WHERE name = :cat), :name, :hours, :price)'
        );

        foreach ($tariffs as $t) {
            $check = $this->db->prepare(
                'SELECT COUNT(*) FROM room_tariffs
                 WHERE room_category_id = (SELECT id FROM room_categories WHERE name = :cat) AND name = :name'
            );
            $check->execute(['cat' => $t['cat'], 'name' => $t['name']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();
            if ((int) $count === 0) {
                $tariff_stmt->execute($t);
            }
        }
    }

    private function seedInvoiceSeries(): void
    {
        $series_list = [
            ['type' => 'BOLETA', 'series' => 'B001', 'number' => 0],
            ['type' => 'FACTURA', 'series' => 'F001', 'number' => 0],
            ['type' => 'NOTA_VENTA', 'series' => 'NV01', 'number' => 0],
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO invoice_series (document_type, series, current_number, is_active)
             VALUES (:type, :series, :number, 1)'
        );

        foreach ($series_list as $s) {
            $check = $this->db->prepare('SELECT COUNT(*) FROM invoice_series WHERE series = :series');
            $check->execute(['series' => $s['series']]);
            /** @var int|string $count */
            $count = $check->fetchColumn();
            if ((int) $count === 0) {
                $stmt->execute($s);
            }
        }
    }
}
