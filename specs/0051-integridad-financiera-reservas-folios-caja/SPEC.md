# SPEC-0051: Integridad financiera de reservas, folios y caja

- **Fecha:** 2026-09-07
- **Estado:** Aprobado — implementado y verificado
- **Módulo:** Reservas, check-in, folios, pagos, caja por turno y arqueo multimedio
- **Dependencias:** SPEC-0003, SPEC-0005, SPEC-0006, SPEC-0024, SPEC-0036 y SPEC-0049
- **Prioridad:** Bloqueante para operación con dinero real

## 1. Objetivo

Garantizar que ningún adelanto, pago o cargo de alojamiento se pierda al pasar por el
ciclo hotelero reserva → check-in → estancia → folio → checkout → cierre de caja.
Cada ingreso real debe conservar su monto, método, usuario, turno, origen y aplicación
al folio. El sistema debe impedir sobrepagos, pagos sin caja abierta y cierres que solo
cuadren efectivo ignorando los medios digitales.

## 2. Alcance

### 2.1 Incluido

1. Registrar adelantos de reserva como movimientos financieros reales, vinculados al
   turno de caja activo y al método de pago.
2. Convertir una reserva a estancia creando atómicamente el folio y el cargo de
   alojamiento, aplicando el adelanto sin duplicar el ingreso en caja.
3. Crear folio y cargo de alojamiento también para el check-in directo.
4. Registrar pagos de folio con turno, método y origen trazables.
5. Permitir pagos divididos en varias partes dentro de una misma operación atómica.
6. Rechazar sobrepagos, montos cero/negativos y pagos sin turno abierto.
7. Cuadrar al cierre efectivo, Yape, Plin, tarjeta y transferencia por separado.
8. Impedir movimientos nuevos en turnos cerrados y proteger el cálculo de cierre ante
   concurrencia.
9. Mantener reversos inmutables y referencias explícitas al origen del dinero.

### 2.2 Fuera del alcance de este SPEC

- Política de penalidad, devolución y no-show de reservas; se implementará en un SPEC
  posterior usando este libro financiero.
- Cancelación y devolución de ventas POS.
- Reportes históricos, ADR, RevPAR y cierre contable de fin de día.
- Emisión, contingencia y reintento de comprobantes SUNAT.
- Compras, mermas y costo de inventario.
- Cambios visuales no necesarios para enviar los nuevos datos de pago.

Estos puntos no se consideran resueltos por este SPEC y permanecerán pendientes de
especificación individual.

## 3. Reglas de negocio e invariantes

1. Un adelanto mayor que cero exige al menos una parte de pago válida y una caja
   abierta para el usuario actuante.
2. La suma de las partes de pago debe ser mayor que cero y cada parte debe tener uno de
   estos métodos: `cash`, `yape`, `plin`, `card` o `transfer`.
3. Toda parte de pago se vincula exactamente a un `cashbox_shift_id`, incluso si es
   digital. Un método digital no se convierte en efectivo.
4. Un check-in de reserva crea en la misma transacción: reserva `checked_in`, estancia
   `active`, folio `open`, cargo de alojamiento y aplicación de todos los adelantos.
5. La aplicación del adelanto al folio no crea un segundo movimiento de caja. El
   ingreso físico/digital solo se reconoce una vez.
6. Un check-in directo crea una estancia, folio y cargo de alojamiento antes de aceptar
   un pago inicial.
7. Un pago no puede superar el saldo pendiente del folio. Si una operación contiene
   varias partes, su suma tampoco puede superarlo.
8. Un checkout con saldo pendiente exige partes de pago cuya suma sea exactamente el
   saldo pendiente. Un checkout con saldo cero no crea un pago ficticio.
9. Todo pago y cargo confirmado conserva usuario, timestamp, método, turno y entidad
   de origen. No se borran movimientos financieros.
10. El efectivo esperado es fondo inicial más ingresos de efectivo menos egresos de
    efectivo. Cada método digital tiene su propio esperado, declarado y diferencia.
11. Un turno cerrado es de solo lectura. Las correcciones posteriores se registran
    como movimientos inversos autorizados y auditados, nunca editando el movimiento
    original.
12. El cierre bloquea la fila del turno y verifica que siga abierta antes de guardar;
    ningún pago concurrente puede entrar después de calcular el arqueo.
13. Si falla cualquier paso de una operación financiera, se revierte toda la operación
    incluyendo cargo, pago, movimiento de caja y auditoría.
14. El saldo del folio se calcula desde cargos y pagos aplicados; los movimientos de
    caja no se suman nuevamente como ingresos operativos.

## 4. Modelo de datos

### 4.1 Tabla `reservation_payments`

Se agrega una tabla para registrar adelantos antes de que exista una estancia:

```sql
CREATE TABLE reservation_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT NOT NULL,
    cashbox_shift_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method ENUM('cash', 'yape', 'plin', 'card', 'transfer') NOT NULL,
    reference_number VARCHAR(100) NULL,
    notes TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE RESTRICT,
    FOREIGN KEY (cashbox_shift_id) REFERENCES cashbox_shifts(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (amount > 0),
    INDEX idx_reservation_payments_reservation_id (reservation_id),
    INDEX idx_reservation_payments_shift_id (cashbox_shift_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

`reservations.deposit_amount` se conserva como total derivado/compatible, pero deja de
ser la única fuente financiera del adelanto.

### 4.2 Cambios en `folio_payments`

Se agregan:

- `cashbox_shift_id INT NOT NULL` con FK a `cashbox_shifts`.
- `reservation_payment_id INT NULL` con FK a `reservation_payments`.
- índice por `cashbox_shift_id`.
- índice único sobre `reservation_payment_id` cuando no sea `NULL`, para impedir aplicar
  dos veces el mismo adelanto.

El valor `reservation_payment_id` identifica la aplicación contable del adelanto; no
crea un nuevo ingreso de caja.

### 4.3 Cambios en `cash_movements`

Se agregan:

- `reference_type VARCHAR(50) NULL`.
- índice compuesto `(reference_type, reference_id)`.

Los valores iniciales permitidos por contrato son `reservation_payment`,
`folio_payment`, `store_sale`, `expense` y `other_income`. La referencia existente
`reference_id` se conserva para compatibilidad y no se interpreta sin su tipo.

### 4.4 Cambios en `cashbox_shifts`

Se agregan columnas para cada método:

- `expected_yape`, `expected_plin`, `expected_card`, `expected_transfer`.
- `real_yape_declared`, `real_plin_declared`, `real_card_declared`,
  `real_transfer_declared`.
- `difference_yape`, `difference_plin`, `difference_card`, `difference_transfer`.

Las columnas monetarias serán `DECIMAL(10,2) NOT NULL DEFAULT 0.00`. El estado global
del turno será `balanced` solo cuando todas las diferencias estén dentro de la
tolerancia de S/0.01.

## 5. Contratos HTTP

### 5.1 Crear reserva

`POST /api/reservations`

Entrada relevante:

```json
{
  "action": "create",
  "room_id": 1,
  "guest_id": 3,
  "tariff_id": 1,
  "check_in_at": "2026-09-08 14:00:00",
  "payment_parts": [
    {"amount": 20.00, "payment_method": "yape", "reference_number": "YAPE-123"}
  ],
  "notes": ""
}
```

Con adelanto devuelve `201`. Sin caja abierta o con partes inválidas devuelve `409` o
`422` sin persistir reserva, pago ni movimiento. La respuesta conserva
`advance_payment` como suma de partes para compatibilidad.

### 5.2 Check-in de reserva

`POST /api/reservations` con `action=checkin` y `reservation_id`, o la ruta equivalente
cuando el router dinámico sea implementado por su SPEC correspondiente.

Devuelve `200` con la estancia y el folio creado. Si falla la aplicación del adelanto,
la reserva, habitación, estancia, folio, pagos y caja quedan sin cambios.

### 5.3 Check-in directo

`POST /api/rooms` con `action=occupy`.

Si incluye `payment_parts`, la suma no puede superar el cargo de alojamiento y cada
parte se registra en el folio y en el turno de caja correspondiente.

### 5.4 Checkout

`POST /api/rooms` con `action=checkout`.

Entrada para saldo pendiente:

```json
{
  "action": "checkout",
  "payment_parts": [
    {"amount": 30.00, "payment_method": "cash"},
    {"amount": 20.00, "payment_method": "card", "reference_number": "POS-55"}
  ]
}
```

La suma debe coincidir exactamente con el saldo. Devuelve `409` si falta dinero,
`422` si los datos son inválidos y `200` solo cuando el pago, cierre de folio,
finalización de estancia y tarea de limpieza quedan confirmados en una transacción.

### 5.5 Cierre de caja

`POST /api/cashbox/close`

Entrada:

```json
{
  "cash_declared": 250.00,
  "yape_declared": 80.00,
  "plin_declared": 40.00,
  "card_declared": 120.00,
  "transfer_declared": 50.00,
  "notes": ""
}
```

La respuesta incluye esperado, declarado y diferencia por cada método, además del
estado global. Un turno cerrado no acepta nuevos movimientos.

## 6. Criterios de aceptación

### CA-01 — Reserva sin adelanto

- **Given:** una habitación libre, huésped y tarifa válidos.
- **When:** se crea una reserva sin `payment_parts`.
- **Then:** se crea la reserva sin movimiento de caja y con `advance_payment=0.00`.

### CA-02 — Adelanto con método y turno

- **Given:** una caja abierta para el usuario autenticado.
- **When:** se crea una reserva con adelanto en Yape.
- **Then:** existe un `reservation_payment`, un `cash_movement` Yape y ambos apuntan al
  mismo turno y usuario.

### CA-03 — Adelanto sin caja

- **Given:** no existe una caja abierta para el usuario.
- **When:** se intenta registrar un adelanto mayor que cero.
- **Then:** responde `409` y no quedan reserva, pago ni movimiento persistidos.

### CA-04 — Check-in de reserva conserva el dinero

- **Given:** una reserva con adelanto registrado.
- **When:** se ejecuta el check-in.
- **Then:** se crean estancia, folio y cargo de alojamiento; el adelanto se aplica al
  folio una sola vez y el total de movimientos de caja no aumenta por segunda vez.

### CA-05 — Check-in directo crea folio

- **Given:** una habitación libre y una tarifa válida.
- **When:** se realiza check-in directo.
- **Then:** existe un folio abierto con exactamente un cargo de alojamiento por el
  precio aplicado.

### CA-06 — Pago inicial limitado al cargo

- **Given:** un cargo de alojamiento de S/50.00.
- **When:** se intenta pagar S/50.01.
- **Then:** se rechaza la operación y no se inserta pago ni movimiento.

### CA-07 — Checkout con pago mixto

- **Given:** un folio con saldo S/50.00 y caja abierta.
- **When:** se paga S/30.00 en efectivo y S/20.00 con tarjeta.
- **Then:** se crean dos pagos, dos movimientos, el folio queda en S/0.00 y cada método
  incrementa únicamente su esperado.

### CA-08 — Sobrepago

- **Given:** un folio con saldo S/30.00.
- **When:** se intenta pagar S/30.01 o más.
- **Then:** responde `422`/`409` y el saldo permanece intacto.

### CA-09 — Cierre multimedio

- **Given:** movimientos de todos los métodos en un turno abierto.
- **When:** el cajero declara importes por método.
- **Then:** el sistema guarda esperado, declarado y diferencia individual y calcula el
  estado global correctamente.

### CA-10 — Turno cerrado

- **Given:** un turno ya cerrado.
- **When:** se intenta registrar un pago o movimiento usando ese turno.
- **Then:** se rechaza y los valores históricos no cambian.

### CA-11 — Atomicidad

- **Given:** falla la creación de una parte del pago o de la auditoría.
- **When:** se ejecuta reserva, check-in o checkout.
- **Then:** se revierte toda la operación y no quedan registros parciales.

### CA-12 — Concurrencia de cierre

- **Given:** dos solicitudes intentan cerrar o pagar sobre el mismo turno.
- **When:** ambas llegan simultáneamente.
- **Then:** solo una operación válida modifica el turno; la otra recibe conflicto y no
  puede insertar dinero después del cierre.

## 7. Casos límite y errores

- Pago con monto cero, negativo, no numérico o con método desconocido.
- Partes cuya suma no coincide con el saldo.
- Referencia obligatoria para métodos configurados como digitales cuando el negocio la
  exija.
- Reserva o estancia inexistente, cancelada, ya aplicada o ya cerrada.
- Caja inexistente, de otro usuario o cerrada.
- Dos check-ins concurrentes para la misma reserva/habitación.
- Error de clave foránea, auditoría o actualización de estado: rollback completo.
- Importes con más de dos decimales: normalizar o rechazar explícitamente, sin pérdida
  silenciosa de centavos.

## 8. Seguridad y permisos

- El usuario actuante siempre proviene de la sesión, nunca del body.
- Todos los cambios requieren autenticación, RBAC y CSRF según SPEC-0036.
- Los repositorios usan prepared statements y columnas explícitas.
- Los turnos cerrados son inmutables para usuarios normales.
- Cada operación financiera escribe auditoría dentro de la misma transacción.
- No se registran contraseñas, tokens ni datos sensibles en conceptos o referencias.

## 9. Transacciones y orden de implementación

1. Crear migración versionada y actualizar fixtures de integración sin modificar
   migraciones históricas.
2. Agregar modelos/repositorios de pagos de reserva y referencias financieras.
3. Implementar primero pruebas rojas de repositorios y servicios.
4. Corregir check-in directo y check-in desde reserva.
5. Corregir pagos de folio, checkout y movimientos por método.
6. Corregir apertura/cierre de caja con bloqueo de fila y cierre por método.
7. Actualizar controladores y formularios únicamente para el contrato aprobado.
8. Conectar auditoría dentro de cada transacción.
9. Ejecutar lint, PHPStan, suites unit/integration/feature y `make verify`.

Cada operación usa `beginTransaction` → escrituras y auditoría → `commit`; cualquier
excepción ejecuta `rollBack` si la conexión sigue dentro de una transacción y relanza el
error.

## 10. Dependencias y pendientes posteriores

Este SPEC no declara resueltos los hallazgos de reportes, facturación, inventario,
cancelaciones/no-show ni rutas dinámicas. Esos cambios deberán tener sus propios SPEC,
uno a la vez, después de verificar y cerrar este bloque financiero.
