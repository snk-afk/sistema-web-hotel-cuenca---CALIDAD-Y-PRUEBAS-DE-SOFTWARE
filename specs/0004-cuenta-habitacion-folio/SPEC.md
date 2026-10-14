# SPEC-0004: Cuenta de Habitación (Folio) y Transferencias

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Cuenta de Habitación (Folio), Cargos, Abonos y Transferencias

---

## 1. Objetivo

Establecer la estructura de datos, servicios y contratos para la gestión acumulativa de la Cuenta de Habitación (**Folio**), el registro de cargos (+Alojamiento, +Extensiones, +Consumos, +Servicios), el registro de abonos (-Adelantos, -Pagos Parciales), el cálculo del saldo pendiente, la transferencia de habitación conservando intacto el Folio y las anulaciones mediante partidas de reverso (garantizando inmutabilidad financiera sin borrado de registros).

---

## 2. Alcance

### Dentro del Alcance:
- Creación de Folio por estancia activa.
- Registro de Cargos al Folio (`accommodation`, `extension`, `store_item`, `service`, `penalty`).
- Registro de Abonos al Folio (`advance`, `partial_payment`, `final_payment`).
- Cálculo en tiempo real del Total Cargos, Total Abonos y Deuda Pendiente ($\text{Deuda} = \sum \text{Cargos} - \sum \text{Abonos}$).
- Transferencia de estancia de Habitación Origen a Habitación Destino manteniendo el Folio, saldos e historial intactos.
- Cambio de estado de la habitación de origen a `cleaning` o `maintenance` tras la transferencia.
- Registro inmutable de reversos/anulaciones de cargos.

### Fuera del Alcance (Módulos posteriores):
- Apertura/cierre de caja por turno (Módulo 5).
- Descuento de stock en almacén por consumos (Módulo 6).

---

## 3. Reglas de Negocio

1. **Atribución de Folio a Estancia Activa:**
   - Todo Folio pertenece a una única estancia activa (`stays.id`).
   - Al hospedar o hacer check-in, se genera automáticamente el Folio de la estancia y se registra el cargo inicial de alojamiento (`applied_price`).

2. **Invariabilidad y Deuda Pendiente:**
   - La deuda pendiente se calcula dinámicamente:
     $$\text{pending\_balance} = \sum \text{folio\_charges.amount} - \sum \text{folio\_payments.amount}$$
   - Los abonados/pagos parciales reducen la deuda pendiente pero no modifican el monto original cargado.

3. **Inmutabilidad Financiera (Prohibición de Borrado):**
   - Bajo ninguna circunstancia se eliminan filas de `folio_charges` o `folio_payments`.
   - Una anulación o ajuste crea una transacción compensatoria de reverso (`is_reversal = 1` o monto negativo) registrando usuario, timestamp y motivo justificado.

4. **Transferencia de Habitación (Preservación Integral):**
   - Transferir una estancia a una nueva habitación traslada intacto el `stays.id` y su `folio_id` asociado.
   - Requiere ingresar motivo obligatorio (ej: "Aire acondicionado averiado").
   - La habitación de origen pasa a estado `cleaning` o `maintenance`.
   - La habitación destino pasa a estado `occupied`.
   - Se inserta un registro inmutable en `room_transfers`.

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Folios de Cuenta de Habitación
CREATE TABLE folios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stay_id INT NOT NULL UNIQUE,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (stay_id) REFERENCES stays(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cargos a la Cuenta (+Alojamiento, +Extensiones, +Consumos, +Servicios)
CREATE TABLE folio_charges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio_id INT NOT NULL,
    charge_type ENUM('accommodation', 'extension', 'store_item', 'service', 'penalty', 'adjustment') NOT NULL,
    description VARCHAR(255) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_amount DECIMAL(10, 2) NOT NULL,
    is_reversal TINYINT(1) NOT NULL DEFAULT 0,
    reversal_reason VARCHAR(255) NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (folio_id) REFERENCES folios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Abonos a la Cuenta (-Adelantos, -Pagos Parciales, -Pago Final)
CREATE TABLE folio_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    folio_id INT NOT NULL,
    payment_type ENUM('advance', 'partial', 'final') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (folio_id) REFERENCES folios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de Transferencias de Habitación
CREATE TABLE room_transfers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stay_id INT NOT NULL,
    from_room_id INT NOT NULL,
    to_room_id INT NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (stay_id) REFERENCES stays(id),
    FOREIGN KEY (from_room_id) REFERENCES rooms(id),
    FOREIGN KEY (to_room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `GET /api/folios/{stay_id}` — Retorna la cuenta completa del Folio (cargos, abonos, deuda pendiente).
- `POST /api/folios/{stay_id}/charge` — Agrega un cargo al Folio.
- `POST /api/folios/{stay_id}/payment` — Registra un abono/pago al Folio.
- `POST /api/folios/{stay_id}/reverse-charge/{charge_id}` — Genera una anulación/reverso de cargo.
- `POST /api/rooms/{id}/transfer` — Ejecuta la transferencia de habitación de la estancia activa.

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Registro de Cargo y Cálculo de Deuda Pendiente
- **Given:** Una estancia activa con alojamiento inicial de `S/ 50.00` y adelanto de `S/ 20.00`.
- **When:** Se agrega un consumo de tienda por `S/ 7.00`.
- **Then:** Total Cargos pasa a `S/ 57.00`, Total Abonos es `S/ 20.00` y Deuda Pendiente es `S/ 37.00`.

### Criterio 2: Transferencia de Habitación Preservando Folio
- **Given:** Habitación `203` en estado `occupied` con Folio que tiene `S/ 37.00` de deuda pendiente.
- **When:** El recepcionista transfiere la estancia a la habitación `305` ingresando la razón `"Falla en aire acondicionado"`.
- **Then:** Habitación `203` pasa a `cleaning`, Habitación `305` pasa a `occupied`, el Folio y la deuda de `S/ 37.00` permanecen intactos asociados a la nueva habitación, y se crea el registro en `room_transfers`.

### Criterio 3: Anulación de Cargo mediante Partida Compensatoria
- **Given:** Un cargo erróneo de lavandería por `S/ 10.00`.
- **When:** El recepcionista revierte el cargo ingresando `"Cobro duplicado por error"`.
- **Then:** No se borra la fila original; se inserta un cargo de reverso con `total_amount = -10.00`, `is_reversal = 1` y la deuda se recalcula correctamente.

---

## 7. Casos Límite y Errores

1. **Habitación Destino No Libre en Transferencia:**
   - Si se intenta transferir a una habitación ocupada o en mantenimiento, la API retorna `HTTP 409 Conflict`.
2. **Reverso sin Motivo:**
   - Si se intenta revertir un cargo sin enviar `reversal_reason`, retorna `HTTP 422 Unprocessable Entity`.
3. **Intento de Modificar Folio Cerrado:**
   - Si el Folio está en estado `closed`, la API bloquea nuevas operaciones retornando `HTTP 409 Conflict`.

---

## 8. Transacciones PDO

- Las transferencias de habitación (`POST /api/rooms/{id}/transfer`) se ejecutan en una transacción PDO atómica:
  ```php
  $db->beginTransaction();
  // 1. Actualizar stays.room_id a to_room_id
  // 2. Actualizar from_room status a 'cleaning'
  // 3. Actualizar to_room status a 'occupied'
  // 4. Crear registro en room_transfers
  $db->commit();
  ```
