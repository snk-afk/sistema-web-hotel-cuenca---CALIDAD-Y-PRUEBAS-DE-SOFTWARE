# SPEC-0019: Sincronización Dinámica Total, Adelantos/Pagos en Check-In, Datos Reales en Drawer y Blindaje E2E

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Sincronización Dinámica de Tarifas/Categorías, Adelantos en Check-in, Mapeo Real de Datos de Huésped y Folio, Blindaje de Flujos E2E

---

## 1. Objetivo

1. Eliminar todos los datos falsos/hardcodeados en el Rack y en el Drawer (Categoría `---`, Documento `45892301`, Tiempos ficticios `00h 00m 00s`, `"Sin Registrar"`).
2. Incorporar en el backend y frontend el registro de **Adelanto / Pago Inicial** durante el Check-In (`initial_payment_amount`, `payment_method`), reflejando automáticamente el saldo pendiente en el Folio.
3. Mapear de forma completa y real la entidad del Huésped (`guest_name`, `guest_doc`, `category_name`, `check_in_at`, `expected_check_out_at`) desde `RoomService::getDashboardMap()`.
4. Implementar la sincronización dinámica de tarifas (`GET /api/tariffs`) según la categoría real de la habitación seleccionada.
5. Mejorar el cliente `apiFetch` y el manejo de errores para mostrar siempre el mensaje de error exacto retornado por el servidor en lugar de mensajes genéricos.
6. Probar todos los flujos con una suite de pruebas E2E automatizada exhaustiva.

---

## 2. Alcance

### Dentro del Alcance:
1. **Backend `RoomService` & `FolioService`:**
   - En `RoomService::getDashboardMap()`, hacer join o cargar los datos reales del huésped (`name`, `document_type`, `document_number`), categoría (`name`), fechas reales (`check_in_at`, `expected_check_out_at`) y balance financiero del folio (`total_charges`, `total_payments`, `pending_balance`).
   - En `RoomService::occupyRoom()`, aceptar parámetros opcionales para adelanto/pago inicial: `initial_payment_amount` (float), `payment_method` (string: cash, yape, card), `payment_reference` (?string). Si `initial_payment_amount > 0`, registrar automáticamente el pago en el Folio mediante `FolioService::addPayment()`.

2. **API de Tarifas y Categorías:**
   - Método `findAll()` en `RoomTariffRepository`.
   - Endpoint `GET /api/tariffs` que devuelve todas las tarifas agrupadas con su categoría y precio.

3. **Frontend `dashboard.php`, `drawer.php` y `drawer.js`:**
   - En `dashboard.php`, inyectar en `.room-card` los atributos `data-category-name`, `data-category-id`, `data-guest-name`, `data-guest-doc`, `data-checkin-at`, `data-checkout-at`, `data-seconds-remaining`.
   - En `room_drawer.php`, agregar campo condicional:
     - 💳 **Adelanto / Pago Inicial (S/):** `<input type="number" id="drawer-checkin-initial-payment">` con selector de método de pago (Efectivo, Yape/Plin, Tarjeta).
     - 📊 **Resumen de Saldo en Vivo:** Muestra "Total Hospedaje", "Adelanto Pagado" y "Saldo Pendiente por Cobrar".
   - En `drawer.js`, al hacer clic en cualquier habitación, poblar dinámicamente con los datos reales del servidor (sin datos inventados).
   - En `api.js`, capturar y mostrar el mensaje real devuelto por la API en respuestas 4xx/5xx (`data.message`).

4. **Suite de Pruebas Automatizadas E2E (`tests/Feature/Flows/ReceptionFlowsE2ETest.php`):**
   - Check-in con pago completo vs con adelanto parcial vs sin adelanto (100% deuda).
   - Verificación de balance de folio en tiempo real.
   - Pruebas con todas las categorías (Simple, Matrimonial, Jacuzzi).

---

## 3. Reglas de Negocio

1. **Adelanto Opcional:** El recepcionista puede registrar Check-In con S/ 0.00 de adelanto (paga al salir), pago parcial (adelanto), o pago total (S/ 0.00 de deuda pendiente).
2. **Cero Datos Hardcodeados:** Todo lo que se muestre en pantalla (nombres, documentos, categorías, tiempos, cargos, abonos) proviene 100% de la base de datos.
3. **Manejo Transparente de Errores:** Si la API rechaza una petición (ej. validación), el usuario ve la razón exacta reportada por el servidor.

---

## 4. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Visualización Real de Datos en Rack y Drawer
- **Given:** Una habitación ocupada con un huésped registrado.
- **When:** Se abre el Drawer de la habitación.
- **Then:** Muestra el nombre real del huésped, su DNI/RUC, la categoría correcta, las fechas exactas y el temporizador en tiempo real.

### Criterio 2: Check-In con Adelanto de Pago
- **Given:** Una habitación libre con tarifa de S/ 60.00.
- **When:** Se realiza el Check-in ingresando un adelanto de S/ 30.00 en efectivo.
- **Then:** El folio registra un cargo de S/ 60.00 y un abono de S/ 30.00, quedando un saldo pendiente de S/ 30.00.

### Criterio 3: Tarifas Dinámicas por Categoría
- **Given:** Cualquier habitación seleccionada.
- **When:** Se abre el formulario de Check-in.
- **Then:** El selector muestra únicamente las tarifas válidas de la categoría de la habitación.

### Criterio 4: Verificación Completa `make verify`
- **Given:** Todos los cambios implementados y la suite E2E.
- **When:** Se ejecuta `make verify`.
- **Then:** 100% pruebas en verde y PHPStan nivel 9 sin errores.
