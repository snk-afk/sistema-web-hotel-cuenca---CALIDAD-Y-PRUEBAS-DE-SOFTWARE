# SPEC-0021: Gestión Integral de Tiempo Cumplido, Horas Extra, Tolerancia y Liquidación Inteligente de Checkout

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Control de Tiempos Excedidos (Overdue), Cobro de Horas Extra, Filtros de Alerta y Checkout Inteligente

---

## 1. Objetivo

Implementar un sistema robusto, reactivo y a prueba de fallos para la gestión de habitaciones cuando su tiempo de hospedaje se ha cumplido o excedido, cubriendo todos los escenarios reales de recepción:
1. Identificación y alerta visual inmediata de habitaciones vencidas en el Rack (Filtro `⚠️ Vencidas`).
2. Protocolo de atención ante tiempo cumplido: Extensión rápida de horas extra, cobro de penalidad o checkout.
3. Liquidación inteligente de checkout: Cierre en 1 clic para cuentas con saldo S/ 0.00, o cobro del saldo pendiente con método de pago para cuentas con deuda.
4. Registro atómico en Folio y Caja de todo pago de liquidación final.

---

## 2. Escenarios y Reglas de Negocio

### Escenario 1: Detección y Visualización de Habitaciones Vencidas (Overdue)
- Si `seconds_remaining <= 0` en una habitación ocupada:
  - La tarjeta en el Rack entra en estado visual de alerta: borde rojo con resplandor pulsante, badge `⚠️ VENCIDA` y cronómetro mostrando el tiempo transcurrido en rojo (ej. `⚠️ +00h 24m 10s`).
  - La barra superior del Rack incluye la píldora **`⚠️ Vencidas (X)`** con el conteo actualizado de habitaciones que requieren atención urgente de recepción.

### Escenario 2: Extensión Rápida de Horas Extra / Penalidad desde el Drawer
- En el Drawer de la habitación vencida, se despliegan accesos rápidos de extensión:
  - Botones de 1-clic: `+1 Hora`, `+2 Horas`, `+4 Horas`, `+12 Horas` con cálculo automático de precio según la categoría.
  - Al confirmar, la hora prevista de salida se desplaza en el tiempo, el cargo se añade al Folio y el cronómetro vuelve a estar en verde/azul positivo.

### Escenario 3: Checkout Inteligente según Saldo de la Cuenta
- **Caso A (Saldo Cero - Huésped pagó todo en Check-in):**
  - El botón de checkout se presenta como **"⚡ Checkout Directo (Saldo S/ 0.00)"**.
  - Al hacer clic, cierra el Folio, libera la habitación a `cleaning` y emite el comprobante sin pedir cobro redundante.
- **Caso B (Saldo Pendiente - Huésped debe consumos POS u hospedaje):**
  - El Drawer resalta el monto exacto adeudado en rojo (ej. `Pendiente de Pago: S/ 45.00`).
  - Solicita el método de pago del saldo (`Efectivo`, `Yape`, `Plin`, `Tarjeta`) y tipo de comprobante (`Boleta`, `Factura`, `Nota de Venta`).
  - Registra el pago en el Folio, genera el movimiento de ingreso en la caja y pasa la habitación a `cleaning`.

---

## 3. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Filtro de Habitaciones Vencidas en el Rack
- **Given:** Existen 2 habitaciones con tiempo restante negativo (`seconds_remaining < 0`).
- **When:** El recepcionista hace clic en la píldora `⚠️ Vencidas`.
- **Then:** El Rack muestra únicamente las 2 habitaciones vencidas con su tiempo transcurrido en rojo.

### Criterio 2: Extensión de Estancia en Habitación Vencida
- **Given:** Una habitación 102 con tiempo excedido en 20 minutos.
- **When:** Se selecciona extender por `2 horas` con precio `S/ 15.00`.
- **Then:** El tiempo de salida se actualiza a `+1h 40m` en positivo, se suma un cargo de `S/ 15.00` al folio y el estado visual pasa a activo.

### Criterio 3: Checkout con Cobro de Saldo Pendiente
- **Given:** Una habitación 201 con un cargo de alojamiento de S/ 45.00, un consumo de S/ 5.00 y un adelanto de S/ 20.00 (Saldo pendiente: S/ 30.00).
- **When:** Se procesa el checkout seleccionando método de pago `Yape`.
- **Then:** Se registra el pago de S/ 30.00 en el folio, el saldo queda en S/ 0.00, el folio pasa a `closed`, se crea el movimiento en caja y la habitación pasa a `cleaning`.

### Criterio 4: Verificación Integral `make verify`
- **Given:** Todas las funcionalidades implementadas con pruebas unitarias y e2e.
- **When:** Se ejecuta `make verify`.
- **Then:** Lint, PHPStan nivel 9 y PHPUnit pasan al 100% en verde.
