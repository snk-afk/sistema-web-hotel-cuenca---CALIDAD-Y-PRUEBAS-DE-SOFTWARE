# SPEC-0005: Caja por Trabajador, Turnos y Movimientos de Caja

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Apertura/Cierre de Turnos de Caja, Atribución Financiera por Trabajador y Arqueo

---

## 1. Objetivo

Establecer la estructura de datos, servicios y contratos para la gestión de turnos de caja por trabajador (`cashbox_shifts`), atribución directa de todo ingreso/egreso de efectivo al turno activo, apertura de turno con base inicial, registro de egresos/ingresos varios, y cierre de turno con cálculo automático de arqueo, detectando faltantes o sobrantes con trazabilidad inmutable.

---

## 2. Alcance

### Dentro del Alcance:
- Apertura de Turno de Caja (`open`) especificando base de dinero inicial (`initial_cash`).
- Restricción de un solo turno activo simultáneo por usuario recepcionista.
- Atribución obligatoria de todo ingreso de efectivo (cobro de estancia, abono de reserva, venta directa) al turno de caja activo.
- Registro de Movimientos Manuales de Caja (`income` y `expense`) con concepto, monto, categoría y usuario responsable.
- Cierre de Turno de Caja (`closed`) mediante declaración de efectivo físico real (`real_cash_declared`).
- Cálculo en tiempo real de:
  $$\text{expected\_cash} = \text{initial\_cash} + \sum \text{incomes\_cash} - \sum \text{expenses\_cash}$$
  $$\text{difference} = \text{real\_cash\_declared} - \text{expected\_cash}$$
- Bloqueo de cobros o cobro de ventas si el recepcionista no tiene una caja abierta.

### Fuera del Alcance (Módulos posteriores):
- Emisión de Comprobantes SUNAT (Módulo 10).
- Compras a Proveedores e Inventario Avanzado (Módulo 6).

---

## 3. Reglas de Negocio

1. **Unico Turno Activo por Recepcionista:**
   - Un trabajador no puede abrir una nueva caja si ya cuenta con un turno en estado `open`.

2. **Bloqueo Operativo Sin Caja Abierta:**
   - Todo cobro en efectivo (`occupyRoom`, `addPayment`, `createReservation` con adelanto en efectivo) exige una caja activa en estado `open`. Si no existe, se rechaza la acción lanzando `DomainException` ("Debe abrir caja antes de realizar transacciones en efectivo").

3. **Cálculo Transparente de Arqueo y Descuadres:**
   - El sistema calcula el saldo esperado (`expected_cash`).
   - Al cerrar caja, el usuario declara lo contado físicamente (`real_cash_declared`).
   - La diferencia se guarda de forma inmutable (`difference = real_cash_declared - expected_cash`).
   - Si `difference < 0`, se registra estado de arqueo `shortage` (Faltante).
   - Si `difference > 0`, se registra estado de arqueo `surplus` (Sobrante).
   - Si `difference == 0`, se registra `balanced` (Cuadrado).

4. **Inmutabilidad de Movimientos:**
   - Los movimientos de caja (`cash_movements`) son inmodificables. Cualquier corrección requiere registrar un movimiento inverso justificado.

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Turnos de Caja por Trabajador
CREATE TABLE cashbox_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    opened_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    initial_cash DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    expected_cash DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    real_cash_declared DECIMAL(10, 2) NULL,
    difference DECIMAL(10, 2) NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    balance_status ENUM('pending', 'balanced', 'shortage', 'surplus') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Movimientos Individuales de Caja
CREATE TABLE cash_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cashbox_shift_id INT NOT NULL,
    type ENUM('income', 'expense') NOT NULL,
    category ENUM('accommodation', 'reservation_advance', 'store_sale', 'petty_expense', 'manual_income', 'manual_expense') NOT NULL,
    concept VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    reference_id INT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `GET /api/cashbox/current` — Obtiene la caja activa del usuario autenticado (con resumen de saldo esperado en tiempo real).
- `POST /api/cashbox/open` — Abre un nuevo turno de caja especificando `initial_cash`.
- `POST /api/cashbox/close` — Cierra el turno de caja indicando `real_cash_declared`.
- `POST /api/cashbox/movement` — Registra un movimiento manual (egreso de caja chica o ingreso no operativo).

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Apertura de Turno y Bloqueo de Duplicados
- **Given:** Un recepcionista sin turno de caja activo.
- **When:** Ejecuta `POST /api/cashbox/open` con base inicial `S/ 100.00`.
- **Then:** Se crea el turno en estado `open`, `initial_cash = 100.00`, `expected_cash = 100.00`. Si intenta abrir otra caja sin cerrar esta, el sistema retorna `HTTP 409 Conflict`.

### Criterio 2: Atribución Automática y Arqueo al Cierre
- **Given:** Turno activo con base de `S/ 100.00`. Se cobra un hospedaje de `S/ 50.00` en efectivo y se registra un egreso de caja chica de `S/ 10.00`.
- **When:** El usuario cierra caja declarando `S/ 135.00` en efectivo físico.
- **Then:** El saldo esperado es `S/ 140.00` ($100 + 50 - 10$). La diferencia se calcula en `-S/ 5.00`, la caja se marca como `closed` y `balance_status = shortage`.

### Criterio 3: Bloqueo de Cobro en Efectivo Sin Caja Abierta
- **Given:** Un usuario sin caja abierta en estado `open`.
- **When:** Intenta realizar un hospedaje o registrar un cobro en efectivo.
- **Then:** El sistema rechaza la operación con `HTTP 409 Conflict` indicando que debe abrir turno de caja primero.

---

## 7. Casos Límite y Errores

1. **Cierre de Caja Ya Cerrada:**
   - Si se intenta cerrar una caja en estado `closed`, retorna `HTTP 409 Conflict`.
2. **Monto Inicial Negativo:**
   - Si se envía `initial_cash < 0`, retorna `HTTP 422 Unprocessable Entity`.
3. **Monto Declarado Nulo o Negativo:**
   - Al cerrar caja, `real_cash_declared` debe ser mayor o igual a 0.

---

## 8. Transacciones PDO

- El Cierre de Caja (`POST /api/cashbox/close`) se ejecuta de forma atómica:
  ```php
  $db->beginTransaction();
  // 1. Recalcular suma exacta de cash_movements en la BD
  // 2. Calcular expected_cash y difference
  // 3. Actualizar cashbox_shifts a status = 'closed'
  $db->commit();
  ```
