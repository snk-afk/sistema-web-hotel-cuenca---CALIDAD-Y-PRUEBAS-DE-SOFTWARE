# SPEC-0013: Sistema de Migraciones y Seeders Separados (Producción vs Desarrollo) en PHP

- **Fecha:** 2026-08-21
- **Estado:** En Implementación
- **Módulo:** Migraciones y Datos Semilla (Seeders) en Clases PHP con Registro de Lotes (`migrations`) y Comandos CLI para Producción y Desarrollo

---

## 1. Objetivo

Establecer un sistema nativo de migraciones y semillas en clases PHP puro (`database/migrations/*.php` y `database/seeds/*.php`), con separación explícita de entornos:
1. **Producción (`ProductionSeeder.php`):** Inicialización de maestras esenciales (Roles, Permisos RBAC, Usuario Admin principal, Series de Comprobante SUNAT `B001`, `F001`, `NV01`, Categorías de Habitación y Tarifas base).
2. **Desarrollo (`DevelopmentSeeder.php`):** Poblamiento completo de datos ficticios en **absolutamente todas las tablas del sistema** (Huéspedes, Reservas, Estancias Activas, Folios/Cargos/Abonos, Turnos de Caja Chica, Movimientos, POS Tienda/Kardex, Egresos/Otros Ingresos, Tareas de Limpieza, Auditoría y Comprobantes de prueba).

---

## 2. Alcance

### Dentro del Alcance:
- Tabla de Control de Migraciones (`migrations`):
  - `id` (INT AUTO_INCREMENT PRIMARY KEY)
  - `migration` (VARCHAR 255 UNIQUE NOT NULL)
  - `batch` (INT NOT NULL)
  - `executed_at` (TIMESTAMP DEFAULT CURRENT_TIMESTAMP)
- Conversión de los 9 archivos SQL a Clases PHP con métodos `up(PDO $db): void` y `down(PDO $db): void`:
  1. `2026_08_21_000001_create_rooms_tariffs_stays_tables.php`
  2. `2026_08_21_000002_create_guests_reservations_tables.php`
  3. `2026_08_21_000003_create_folios_tables.php`
  4. `2026_08_21_000004_create_cashbox_tables.php`
  5. `2026_08_21_000005_create_store_tables.php`
  6. `2026_08_21_000006_create_expenses_other_incomes_tables.php`
  7. `2026_08_21_000007_create_housekeeping_tables.php`
  8. `2026_08_21_000008_create_users_roles_audit_tables.php`
  9. `2026_08_21_000009_create_invoices_tables.php`
- Runner de Migraciones CLI (`database/migrate.php`).
- Runner de Datos Semilla CLI (`database/seed.php` con soporte `--env=production` o `--env=development` / default `dev`):
  - **`ProductionSeeder.php`:**
    - Roles (`admin`, `receptionist`, `housekeeper`, `accountant`).
    - Permisos RBAC y mapeo a roles.
    - Usuario Admin por defecto (`admin` / `Admin123!`).
    - Series de comprobante (`B001`, `F001`, `NV01`).
    - Categorías de Habitación (`Simple`, `Matrimonial`, `Jacuzzi`) y Tarifas.
  - **`DevelopmentSeeder.php`:**
    - Ejecuta `ProductionSeeder`.
    - Puebla **100% de las tablas restantes**: Huéspedes de prueba (`guests`), Reservas (`reservations`), Estancias Activas (`stays`), Folios (`folios`, `folio_charges`, `folio_payments`), Turno de Caja Chica (`cashbox_shifts`, `cash_movements`), Productos POS e Inventario (`products`, `store_sales`, `store_sale_items`, `inventory_movements`), Egresos y Otros Ingresos (`expenses`, `other_incomes`), Limpieza (`housekeeping_tasks`), Bitácora (`audit_logs`) y Comprobantes (`invoices`, `invoice_items`).
- Integración en `Makefile`:
  - `make db-migrate`
  - `make db-seed-prod`
  - `make db-seed-dev` (o `make db-seed`)

---

## 3. Reglas de Negocio

1. **Idempotencia en Seeders:**
   - La siembra de datos semillas utiliza inserciones seguras (`INSERT IGNORE` / comprobaciones `COUNT = 0`) para evitar duplicaciones al re-ejecutar.
2. **Separación de Entornos:**
   - En producción **jamás** se ejecutan datos ficticios de clientes o ventas falsas. Solo maestras necesarias.

---

## 4. Estructura de Directorios

```text
database/
├── migrations/
│   ├── 2026_08_21_000001_create_rooms_tariffs_stays_tables.php
│   ├── ...
│   └── 2026_08_21_000009_create_invoices_tables.php
├── seeds/
│   ├── ProductionSeeder.php
│   ├── DevelopmentSeeder.php
│   └── DatabaseSeeder.php
├── migrate.php                # CLI Runner de Migraciones
└── seed.php                   # CLI Runner de Seeders (--env=prod|dev)
```

---

## 5. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Seeder de Producción
- **Given:** Una base de datos migrada vacía.
- **When:** Se ejecuta `php database/seed.php --env=production` (o `make db-seed-prod`).
- **Then:** Se pueblan únicamente las tablas maestras (Roles, Permisos, Usuario Admin, Series y Categorías/Tarificadores). No se crean estancias ni ventas falsas.

### Criterio 2: Seeder de Desarrollo (100% Cobertura de Tablas)
- **Given:** Una base de datos migrada vacía.
- **When:** Se ejecuta `php database/seed.php --env=development` (o `make db-seed-dev`).
- **Then:** Se pueblan **todas las 20+ tablas del sistema** con datos de prueba realistas para probar inmediatamente el Dashboard y la API.
