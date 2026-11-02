# SPEC-0052: Cancelaciones, no-show y devoluciones de reservas

- Fecha: 2026-09-07
- Estado: Aprobado por el usuario
- Módulo: Reservas, penalidades, devoluciones, caja y auditoría
- Dependencias: SPEC-0046, SPEC-0049 y SPEC-0051
- Prioridad: Bloqueante para operar reservas con dinero real

## 1. Objetivo

Implementar el ciclo financiero completo de cancelación y no-show de una reserva:
calcular la política de penalidad, conservar el pago original, crear una obligación
de devolución cuando corresponda, ejecutar el reembolso por el método original y
reflejarlo en el turno de caja que realmente entrega el dinero.

## 2. Contexto y alcance

### 2.1 Incluido

1. Cancelar reservas pending o confirmed con motivo obligatorio.
2. Aplicar devolución total o parcial según la anticipación del check-in.
3. Declarar no_show después de la ventana de gracia de dos horas.
4. Registrar penalidad, monto devolvible y estado de liquidación.
5. Aprobar y ejecutar devoluciones con permiso específico y turno abierto.
6. Vincular cada devolución al adelanto original y al movimiento inverso.
7. Impedir dobles devoluciones y carreras concurrentes.
8. Auditar solicitud, aprobación, ejecución y errores transaccionales.
9. Mantener los movimientos originales inmutables.

### 2.2 Fuera del alcance

- Devoluciones y anulaciones de ventas POS.
- Devoluciones de pagos de folios posteriores al check-in.
- Reportes históricos, cierre contable y conciliación de fin de día.
- Reglas tributarias, notas de crédito y reintentos SUNAT.
- Cambios visuales no necesarios para ejecutar el flujo.

## 3. Reglas de negocio

1. Solo se pueden cancelar reservas pending o confirmed. Una reserva checked_in,
   cancelled o no_show no puede volver a liquidarse.
2. El motivo de cancelación o no-show es obligatorio y se almacena en auditoría.
3. Si la hora actual es al menos 24 horas anterior al check-in reservado, el porcentaje
   devolvible es 100%.
4. Si faltan menos de 24 horas y el check-in aún no inicia, el porcentaje devolvible
   es 50% y la penalidad 50%.
5. Cuando llega o supera el check-in reservado, no se permite una cancelación normal
   durante la gracia; solo se puede declarar no_show al cumplirse dos horas.
6. no_show aplica penalidad del 100% y no crea devolución.
7. La política se calcula en el servidor usando el reloj y zona horaria configurados;
   el cliente no puede enviar porcentajes, fechas de corte ni montos calculados.
8. El monto devuelto por cada adelanto es round(amount * percentage, 2,
   PHP_ROUND_HALF_UP). La suma de devoluciones es la liquidación oficial.
9. Una reserva con devolución pendiente queda cancelled pero su liquidación queda
   pending_refund; el adelanto original permanece intacto hasta la ejecución.
10. Una devolución exige permiso reservations.refund, motivo de aprobación y un turno
    de caja abierto para el usuario ejecutor.
11. La devolución conserva el método original (cash, yape, plin, card, transfer) y
    descuenta el método en el turno ejecutor.
12. No se puede ejecutar una devolución ya ejecutada, parcial o total, ni procesar dos
    liquidaciones para la misma reserva.
13. La solicitud de cancelación, la aprobación/ejecución y sus movimientos son
    operaciones atómicas. Si falla una escritura o auditoría, todo hace rollback.
14. Los movimientos de ingreso originales nunca se editan ni eliminan; la devolución
    se registra como un movimiento expense inverso.
15. El permiso reservations.refund se agrega al catálogo de permisos y se asigna a
    admin y accountant; receptionist no puede ejecutar devoluciones.

## 4. Modelo de datos

### 4.1 Estado de reserva

La migración MySQL agrega no_show al ENUM reservations.status. La rama SQLite
mantiene el texto compatible con este estado.

### 4.2 reservation_cancellations

Campos obligatorios:

- id INT AUTO_INCREMENT PRIMARY KEY.
- reservation_id INT NOT NULL UNIQUE.
- event_type ENUM(cancelled, no_show) NOT NULL.
- policy_code ENUM(full_refund, partial_refund, no_refund) NOT NULL.
- refund_percentage DECIMAL(5,2) NOT NULL.
- gross_paid_amount DECIMAL(10,2) NOT NULL.
- refund_amount DECIMAL(10,2) NOT NULL.
- penalty_amount DECIMAL(10,2) NOT NULL.
- status ENUM(pending_refund, completed, no_refund) NOT NULL.
- reason VARCHAR(255) NOT NULL.
- requested_by_user_id INT NOT NULL.
- approved_by_user_id INT NULL.
- approved_at DATETIME NULL.
- created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP.
- updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP.

Debe tener FKs RESTRICT a reservations, users solicitante y users aprobador,
índice por estado y fecha, y checks para montos no negativos.

### 4.3 reservation_refunds

Campos obligatorios:

- id INT AUTO_INCREMENT PRIMARY KEY.
- cancellation_id INT NOT NULL.
- reservation_payment_id INT NOT NULL UNIQUE.
- cashbox_shift_id INT NOT NULL.
- amount DECIMAL(10,2) NOT NULL.
- payment_method ENUM(cash, yape, plin, card, transfer) NOT NULL.
- reference_number VARCHAR(100) NULL.
- executed_by_user_id INT NOT NULL.
- reason VARCHAR(255) NOT NULL.
- created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP.

Debe tener FKs RESTRICT a la liquidación, adelanto original, turno y usuario
ejecutor; índice por cancellation_id y check amount > 0.

### 4.4 Referencias de caja

cash_movements.reference_type acepta además reservation_refund. Cada devolución
crea exactamente un movimiento con type=expense, category=reservation_refund,
reference_id igual al id de reservation_refunds, reference_type=reservation_refund
y payment_method igual al adelanto original.

## 5. Endpoints y rutas

### 5.1 Solicitar cancelación

POST /api/reservations con action=cancel, reservation_id y reason.

Con devolución pendiente responde 202 con cancellation_id, status, refund_amount y
penalty_amount. Sin dinero a devolver responde 200 con status=no_refund.

### 5.2 Declarar no-show

POST /api/reservations con action=no_show, reservation_id y reason.

Responde 200 con status=no_refund y event_type=no_show, o 409 si aún no
transcurrieron las dos horas de gracia.

### 5.3 Aprobar y ejecutar devolución

POST /api/reservations/refunds/approve con cancellation_id, reason y
reference_number opcional.

El usuario se obtiene de la sesión. Responde 200 con la liquidación, devoluciones y
movimientos creados. Responde 403 sin reservations.refund, 409 sin turno,
liquidación inválida o ya procesada, y 422 con datos inválidos.

Las acciones de auditoría serán REQUEST_RESERVATION_CANCELLATION,
DECLARE_RESERVATION_NO_SHOW y EXECUTE_RESERVATION_REFUND. La autorización se
determina por el permiso persistido del usuario, nunca por un rol enviado en la
petición.

## 6. Criterios de aceptación

### CA-01 — Cancelación con devolución total

- Given: reserva confirmada con uno o más adelantos y check-in a 24 horas o más.
- When: un usuario solicita cancelación.
- Then: la reserva queda cancelled, la liquidación queda pending_refund, y el
  monto devolvible es 100% de cada adelanto.

### CA-02 — Cancelación con devolución parcial

- Given: reserva confirmada con adelanto de S/100 y check-in en menos de 24 horas.
- When: se solicita cancelación.
- Then: la liquidación registra devolución S/50 y penalidad S/50 sin mover caja.

### CA-03 — No-show

- Given: reserva confirmada cuyo check-in reservado terminó hace más de dos horas.
- When: se declara no-show.
- Then: la reserva queda no_show, la liquidación queda no_refund, y no se crea
  devolución ni movimiento de caja.

### CA-04 — Ventana de gracia

- Given: check-in reservado ya iniciado pero aún no transcurrieron dos horas.
- When: se intenta cancelar o declarar no-show.
- Then: se rechaza sin cambiar reserva, liquidación, adelantos o caja.

### CA-05 — Aprobación autorizada

- Given: una liquidación pending_refund y un usuario con reservations.refund y
  turno abierto.
- When: aprueba y ejecuta.
- Then: se crea una devolución por adelanto, un movimiento inverso por método,
  auditoría y la liquidación queda completed.

### CA-06 — Usuario no autorizado

- Given: una liquidación pendiente y usuario sin reservations.refund.
- When: intenta ejecutar la devolución.
- Then: responde 403 y no persiste ningún cambio.

### CA-07 — Método y turno de devolución

- Given: adelantos distribuidos entre Yape y tarjeta.
- When: se ejecuta la devolución.
- Then: cada devolución conserva su método original, usa el turno ejecutor y
  actualiza únicamente el esperado de ese método.

### CA-08 — Doble ejecución

- Given: una liquidación ya completed o un adelanto ya devuelto.
- When: se intenta ejecutar nuevamente.
- Then: responde conflicto y no crea pagos, movimientos ni auditorías adicionales.

### CA-09 — Atomicidad

- Given: falla una devolución, movimiento o auditoría.
- When: se ejecuta la aprobación.
- Then: no quedan devoluciones, movimientos parciales, cambio de estado ni
  auditoría incompleta.

### CA-10 — Concurrencia

- Given: dos solicitudes intentan cancelar o devolver la misma reserva.
- When: se procesan simultáneamente.
- Then: solo una liquidación/devolución es válida y la otra recibe conflicto.

## 7. Casos límite y errores

- Reserva sin adelantos.
- Adelantos divididos con importes que redondean a centavos distintos.
- Adelanto ya aplicado a un folio.
- Turno ejecutor cerrado o inexistente.
- Método original no permitido.
- Fecha de check-in inválida o zona horaria inconsistente.
- Reserva ya checked_in, cancelled o no_show.
- Falta de motivo, permiso, auditoría o clave foránea.
- Intentos de alterar porcentajes, montos o usuario desde el body.

## 8. Seguridad y permisos

- El usuario actuante siempre proviene de la sesión.
- POST /api/reservations exige autenticación, RBAC y CSRF.
- La aprobación exige el permiso reservations.refund; no se autoriza por nombre de
  rol enviado por el cliente.
- Los usuarios normales no pueden editar ni borrar pagos, movimientos o liquidaciones.
- No se registran contraseñas, tokens ni datos sensibles en referencias.
- Los mensajes HTTP no exponen SQL ni trazas.

## 9. Transacciones y auditoría

### Solicitud de cancelación/no-show

beginTransaction → bloquear reserva y adelantos → calcular política → insertar
liquidación → actualizar estado → auditoría → commit.

### Aprobación/ejecución

beginTransaction → bloquear liquidación y adelantos → verificar turno abierto y
estado pendiente → insertar devoluciones → insertar movimientos inversos → actualizar
caja y liquidación → auditoría → commit.

Todo catch hace rollback si la conexión está dentro de una transacción y relanza la
excepción.

## 10. Plan de pruebas

Cada criterio tendrá pruebas unitarias de servicios, integración de repositorios contra
cs_hotel_test y pruebas feature de los endpoints. Se agregará compatibilidad SQLite
para el migrador, sin reemplazar las pruebas de integridad MySQL.

## 11. Dependencias y pendientes

- Agregar una migración versionada para el estado no_show, las tablas de liquidación
  y devolución, las restricciones, índices y el tipo reservation_refund de caja.
- Actualizar los seeders para reservations.refund y su asignación a admin/accountant.
- Mantener compatibilidad con SQLite del migrador y ejecutar las pruebas financieras
  contra MySQL/MariaDB de test cuando estén disponibles.
- No se puede declarar este SPEC implementado hasta que todos los CA tengan pruebas,
  `make verify` esté en verde y exista autorización explícita para el commit.

Este SPEC no resuelve devoluciones POS, pagos de folio posteriores al check-in,
reportes, cierre contable, SUNAT ni inventario. Se especificarán por separado una vez
cerrado este SPEC.
