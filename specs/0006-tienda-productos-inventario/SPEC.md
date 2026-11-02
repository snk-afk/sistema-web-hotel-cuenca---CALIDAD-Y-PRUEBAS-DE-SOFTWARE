# SPEC-0006: Tienda POS, Productos, Inventario y Carga a la Habitación

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Catálogo de Productos, Punto de Venta (POS), Descuento de Stock y Carga de Consumos al Folio

---

## 1. Objetivo

Establecer la estructura de datos, servicios y contratos para la gestión del catálogo de productos de tienda/minibar, control estricto de inventario (Kardex), venta directa al contado en punto de venta (POS) y modalidad de Carga Directa a la Cuenta de la Habitación (Folio), garantizando la deducción atómica de stock en ambas modalidades.

---

## 2. Alcance

### Dentro del Alcance:
- Registro y edición de productos (`name`, `sku`, `sale_price`, `cost_price`, `stock`, `min_stock`, `is_active`).
- Punto de Venta (POS) para Venta Directa al Contado (`store_sales`, `store_sale_items`).
- Deducción inmediata y atómica del inventario (`products.stock`).
- Registro automático del ingreso en la caja activa del recepcionista si la venta es en efectivo.
- Modalidad **Cargar a la Habitación**: añade un cargo de tipo `store_item` al Folio de la estancia activa y descuenta el stock inmediatamente sin registrar ingreso de caja en ese instante.
- Historial inmutable de Movimientos de Inventario / Kardex (`inventory_movements`).
- Alertas de Stock Bajo (`stock <= min_stock`).

### Fuera del Alcance (Módulos posteriores):
- Facturación Electrónica SUNAT inmediata de la venta (Módulo 10).
- Órdenes de Compra y Proveedores (Módulo 7).

---

## 3. Reglas de Negocio

1. **Control Estricto de Stock:**
   - No se pueden vender ni cargar a la habitación productos si la cantidad solicitada supera el stock disponible (`quantity > stock`). Se lanza `DomainException` ("Stock insuficiente").

2. **Atribución Financiera por Modalidad:**
   - **Venta Directa POS:** Si se paga en efectivo, genera un movimiento de ingreso en el turno de caja activo (`CashboxService`) y descuenta stock.
   - **Carga a la Habitación (Folio):** Agrega un cargo de tipo `store_item` al Folio activo de la habitación y descuenta stock. No genera movimiento de caja inmediato (se cobrará cuando el huésped abone a su Folio).

3. **Inmutabilidad de Kardex:**
   - Cada venta, carga a habitación o ajuste genera un registro en `inventory_movements` indicando la cantidad, el stock resultante, la razón y el usuario actuante.

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Catálogo de Productos de Tienda / Minibar
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sku VARCHAR(50) NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    sale_price DECIMAL(10, 2) NOT NULL,
    cost_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    min_stock INT NOT NULL DEFAULT 5,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ventas Directas al Contado en Tienda POS
CREATE TABLE store_sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cashbox_shift_id INT NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Detalle de Venta de Tienda POS
CREATE TABLE store_sale_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    store_sale_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (store_sale_id) REFERENCES store_sales(id),
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movimientos de Inventario (Kardex)
CREATE TABLE inventory_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    movement_type ENUM('purchase', 'sale', 'charge_to_room', 'adjustment_in', 'adjustment_out', 'waste') NOT NULL,
    quantity INT NOT NULL,
    resulting_stock INT NOT NULL,
    concept VARCHAR(255) NOT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `GET /api/products` — Listado de productos activos y alerta de stock bajo.
- `POST /api/products` — Registra un nuevo producto en el catálogo.
- `POST /api/store/sales` — Realiza una venta directa al contado en tienda POS (descuenta stock e ingresa dinero a caja).
- `POST /api/store/charge-to-room` — Carga productos consumidos al Folio de una habitación (descuenta stock y registra cargo en Folio).

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Venta Directa en POS y Descuento de Stock
- **Given:** Producto `"Coca-Cola 500ml"` con `stock = 10` y precio `S/ 4.00`.
- **When:** Se vende 2 unidades en efectivo en el POS.
- **Then:** El stock disponible se reduce a `8`, se crea la venta por `S/ 8.00`, se genera un movimiento de ingreso en el turno de caja activo por `S/ 8.00` y se registra en Kardex.

### Criterio 2: Carga a la Habitación (Folio)
- **Given:** Habitación `201` con estancia activa y producto `"Agua Mineral 1L"` con `stock = 5` y precio `S/ 3.00`.
- **When:** El recepcionista carga 1 agua mineral a la habitación `201`.
- **Then:** El stock se reduce a `4`, se agrega un cargo `store_item` por `S/ 3.00` al Folio de la habitación `201` y no se altera el saldo en efectivo de la caja en ese instante.

### Criterio 3: Validación de Stock Insuficiente
- **Given:** Producto con `stock = 1`.
- **When:** Se intenta vender o cargar 2 unidades.
- **Then:** El sistema rechaza la operación lanzando `DomainException` sin modificar la base de datos.

---

## 7. Casos Límite y Errores

1. **Sin Stock Disponible:**
   - `quantity > stock` retorna `HTTP 409 Conflict`.
2. **Sin Turno de Caja Abierto en Venta Directa:**
   - Vender al contado en efectivo sin caja abierta retorna `HTTP 409 Conflict`.
3. **Intentar Cargar a Habitación No Ocupada:**
   - Retorna `HTTP 409 Conflict`.

---

## 8. Transacciones PDO

- Tanto las ventas directas como las cargas a habitación se ejecutan en transacciones atómicas PDO:
  ```php
  $db->beginTransaction();
  // 1. Verificar stock suficiente de cada producto
  // 2. Descontar stock (UPDATE products SET stock = stock - ?)
  // 3. Crear store_sale / folio_charge
  // 4. Registrar movimientos en inventory_movements (Kardex)
  // 5. Registrar ingreso en caja activa si aplica
  $db->commit();
  ```
