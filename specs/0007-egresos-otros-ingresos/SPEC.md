# SPEC-0007: Egresos de Caja Chica y Otros Ingresos

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Egresos de Caja Chica, Comprobantes de Gastos y Otros Ingresos No Operativos

---

## 1. Objetivo

Establecer la estructura de datos, servicios y contratos para el registro categorizado de egresos de caja chica (mantenimiento, compras menores, viáticos, pagos directos a proveedores) y de otros ingresos no derivados de hospedaje (cochera independiente, lavandería no vinculada a habitación, eventos), garantizando la imputación inmediata al turno de caja activo.

---

## 2. Alcance

### Dentro del Alcance:
- Registro de Egresos (`expenses`) categorizados (`maintenance`, `supplies`, `utilities`, `petty_cash`, `other_expense`).
- Registro de Proveedor / Beneficiario y comprobante de referencia opcional (factura/boleta/recibo).
- Registro de Otros Ingresos (`other_incomes`) categorizados (`parking`, `laundry_external`, `events`, `penalty_damage`, `recycling`, `other_income`).
- Imputación automática al turno de caja activo en efectivo (`CashboxService`).
- Historial filtrado de ingresos y egresos con totales acumulados.

### Fuera del Alcance (Módulos posteriores):
- Contabilidad de costos avanzada y asientos contables (Módulo 10).

---

## 3. Reglas de Negocio

1. **Justificación Obligatoria:**
   - Todo egreso de dinero exige una descripción/motivo descriptivo (`concept`).
   - El monto debe ser estrictamente mayor a 0 (`amount > 0`).

2. **Atribución de Efectivo:**
   - Si un egreso se paga en efectivo (`payment_method = 'cash'`), se requiere un turno de caja abierto en estado `open`. Se registra un movimiento de tipo `expense` que reduce el saldo esperado al cierre.
   - Si un ingreso no operativo es en efectivo, incrementa el saldo esperado de la caja activa.

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Egresos de Caja Chica / Gastos
CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cashbox_shift_id INT NOT NULL,
    category ENUM('maintenance', 'supplies', 'utilities', 'petty_cash', 'other_expense') NOT NULL DEFAULT 'petty_cash',
    concept VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    supplier_name VARCHAR(150) NULL,
    receipt_number VARCHAR(50) NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Otros Ingresos No Operativos
CREATE TABLE other_incomes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cashbox_shift_id INT NOT NULL,
    category ENUM('parking', 'laundry_external', 'events', 'penalty_damage', 'recycling', 'other_income') NOT NULL DEFAULT 'other_income',
    concept VARCHAR(255) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL DEFAULT 'cash',
    notes TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `GET /api/expenses` — Listado de egresos registrados.
- `POST /api/expenses` — Registra un egreso de caja.
- `GET /api/other-incomes` — Listado de otros ingresos.
- `POST /api/other-incomes` — Registra un ingreso no operativo.

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Registro de Egreso y Reducción de Saldo Esperado
- **Given:** Turno de caja abierto con saldo esperado de `S/ 100.00`.
- **When:** Se registra un egreso de `S/ 15.00` por compra de foco para recepción.
- **Then:** Se inserta el egreso, el movimiento de caja reduce el saldo esperado a `S/ 85.00`.

### Criterio 2: Registro de Otro Ingreso (Ej. Cochera Independiente)
- **Given:** Turno de caja abierto con `S/ 85.00`.
- **When:** Se registra un ingreso de `S/ 10.00` por alquiler de cochera por 2 horas.
- **Then:** Se registra en `other_incomes` e incrementa el saldo esperado de la caja a `S/ 95.00`.

---

## 7. Casos Límite y Errores

1. **Monto Nulo o Negativo:**
   - Retorna `HTTP 422 Unprocessable Entity`.
2. **Registro de Egreso en Efectivo Sin Caja Abierta:**
   - Retorna `HTTP 409 Conflict`.

---

## 8. Transacciones PDO

- Toda inserción de egreso u otro ingreso con método `cash` se ejecuta dentro de una transacción PDO atómica con el movimiento de caja.
