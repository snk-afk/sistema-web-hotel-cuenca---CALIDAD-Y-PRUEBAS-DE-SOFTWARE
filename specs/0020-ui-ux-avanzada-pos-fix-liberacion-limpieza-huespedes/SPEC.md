# SPEC-0020: Rediseño UI/UX de Nueva Generación, Corrección POS, Liberación Rápida de Limpieza y Asociación de Huéspedes

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** UI/UX del Rack de Habitaciones, POS Store Fix, Liberación de Limpieza 1-Click, Vinculación Automática de Huéspedes

---

## 1. Objetivo

1. **Transformar la UI/UX del Rack de Habitaciones:** Diseñar tarjetas de habitación de nivel premium con jerarquía visual clara, bordes luminosos por estado, chips de categoría, datos de huésped destacados (Avatar + Nombre real + Documento), temporizador con barra de progreso y balance financiero visible en tarjeta.
2. **Acción Rápida de Liberación de Limpieza (1-Click):** Permitir liberar habitaciones en estado `cleaning` directamente desde la card del Rack o desde el Drawer con un solo clic.
3. **Corregir el Módulo de Consumos POS:** Enviar el `room_id` correcto y estructura de items adecuada en `pos.js`, permitiendo sumar consumos a la habitación y reflejarlos en tiempo real en el Folio.
4. **Garantizar Nombre Real de Huésped:** Asegurar que en todo Check-In el huésped quede vinculado en `stays.guest_id` y su nombre completo se exhiba claramente en el Rack y Drawer.

---

## 2. Alcance

### Dentro del Alcance:
1. **Frontend `views/pages/dashboard.php` y `public/assets/css/pages/dashboard.css`:**
   - Rediseño integral de las `.room-card`:
     - Encabezado con número de habitación en tamaño grande y badge de estado translúcido.
     - Chip de categoría estilizado con color distintivo.
     - Para `occupied`: Bloque de huésped (avatar 👤, nombre en negrita, DNI/RUC), cronómetro en tiempo real con barra de tiempo restante y píldora de saldo pendiente.
     - Para `cleaning`: Tarjeta con acento amarillo/ámbar y botón directo de acción rápida **"✨ Liberar Habitación"**.
     - Para `free`: Tarjeta limpia con botón **"⚡ Iniciar Check-In"**.
     - Para `maintenance`: Botón para gestionar o reactivar.

2. **Frontend `public/assets/js/components/pos.js` y `public/assets/css/components/pos.css`:**
   - Corregir llamada a `POST /api/store/charge-to-room` enviando `room_id: currentDrawerRoomId` y `items: [{ product_id, quantity }]`.
   - Actualizar inmediatamente el Folio del Drawer y el Rack tras agregar el producto.

3. **Frontend `public/assets/js/components/drawer.js` y `dashboard.js`:**
   - Función `quickReleaseCleaningRoom(roomId, event)` para liberar la habitación desde el Rack sin fricción.
   - En Check-In, garantizar que el nombre y documento ingresados se registren en la BD y se asocie el `guest_id` resultante a la estancia.

4. **Backend `src/Controllers/RoomController.php` y `src/Services/RoomService.php`:**
   - Si durante `occupyRoom` no se envía `guest_id` pero sí `guest_name` o `guest_doc`, auto-crear/buscar el huésped en el servicio para blindar la asociación.

5. **Pruebas Automatizadas Unit y Feature:**
   - Pruebas de liberación rápida de limpieza (`updateRoomStatus` a `free`).
   - Pruebas de cargo de consumos POS a la habitación.
   - Pruebas de asociación de huésped con nombre real.

---

## 3. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Liberación Rápida de Limpieza
- **Given:** Una habitación con estado `cleaning`.
- **When:** El recepcionista hace clic en "✨ Liberar Habitación" en la tarjeta.
- **Then:** La habitación pasa inmediatamente al estado `free` y la interfaz se actualiza sin recargar la página.

### Criterio 2: Cargo de Consumos POS a la Habitación
- **Given:** Una habitación ocupada abierta en el Drawer.
- **When:** Se hace clic en "➕ Cargar" en un producto de la tienda.
- **Then:** El producto se descuenta del stock, se agrega como cargo al Folio de la habitación y el saldo se actualiza al instante.

### Criterio 3: Visualización de Nombre Real del Huésped
- **Given:** Se realiza el Check-In con nombre "Alejandro Toledo" y DNI "70809010".
- **When:** Se visualiza el Rack y se abre el Drawer.
- **Then:** La tarjeta y el Drawer muestran "Alejandro Toledo" y "DNI 70809010", sin textos genéricos.

### Criterio 4: Verificación Integral `make verify`
- **Given:** Todos los cambios y pruebas implementados.
- **When:** Se ejecuta `make verify`.
- **Then:** Lint, PHPStan nivel 9 y PHPUnit pasan al 100% en verde.
