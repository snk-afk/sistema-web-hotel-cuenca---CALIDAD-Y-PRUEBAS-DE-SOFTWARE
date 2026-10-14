# SPEC-0016: Interfaz Gráfica Interactiva Completa para Gestión de Habitaciones, Categorías, Tarifas y Check-In Manual Personalizado (`/habitaciones`)

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Módulo Cliente de Habitaciones, Categorías, Tarifas y Check-In Manual con Fechas/Horas/Precios Libres (`views/pages/rooms.php` & `RoomService`)

---

## 1. Objetivo

Transformar la página `/habitaciones` y la API de hospedaje en un **módulo interactivo completo de administración e infraestructura hotelera**, que incluya la capacidad de realizar **Check-In con Hora de Entrada, Hora de Salida y Precio Total 100% Personalizado y Manual** (sin depender únicamente de la duración fija de las tarifas), además de permitir a administradores y recepcionistas crear, editar y cambiar el estado de las habitaciones, administrar categorías y configurar tablas de precios.

---

## 2. Alcance

### Dentro del Alcance:
1. **Ampliación de la API de Hospedaje (`RoomService::occupyRoom` y `RoomController`):**
   - Parámetro opcional `custom_check_in_time` (`YYYY-MM-DD HH:MM:SS`): Permite establecer una fecha y hora de entrada manual en el pasado o presente.
   - Parámetro opcional `custom_check_out_time` (`YYYY-MM-DD HH:MM:SS`): Permite fijar libremente la fecha y hora exacta de salida deseada por el cliente.
   - Parámetro `applied_price` (S/): Permite asignar un precio manual libre acordado con el cliente, con registro del motivo.

2. **Vista de Gestión e Interfaz Gráfica (`views/pages/rooms.php`):**
   - **Pestaña 1: Habitaciones de Hotel:** Lista y tabla interactiva con búsqueda por piso/número, indicador de estado y botones de acción:
     - ➕ Botón **"Nueva Habitación"** (abre modal `modal-create-room`).
     - ✏️ Botón **"Editar Habitación"** (abre modal `modal-edit-room`).
     - 🛠️ Botón **"Poner en Mantenimiento / Avería"** (cambia estado a `maintenance` con motivo).
     - 🔑 Botón **"Check-In Manual / Personalizado"**: Modal con selectores `datetime-local` de Entrada, Salida y Precio libre.
   - **Pestaña 2: Categorías de Habitaciones:**
     - Tabla de categorías activas con descripción y conteo de habitaciones.
     - ➕ Botón **"Nueva Categoría"** (abre modal `modal-create-category`).
   - **Pestaña 3: Tarifas por Categoría:**
     - Tabla de precios por horas (ej: 12h S/ 35.00, 24h S/ 60.00).
     - ➕ Botón **"Nueva Tarifa"** (abre modal `modal-create-tariff`).

3. **Formulario de Check-In Manual Personalizado (`views/components/room_drawer.php` y modales):**
   - Selector `<input type="datetime-local" id="checkin-custom-in">` para Fecha/Hora de Entrada manual.
   - Selector `<input type="datetime-local" id="checkin-custom-out">` para Fecha/Hora de Salida manual.
   - Selector `<input type="number" id="checkin-custom-price">` para Precio Manual Libre en Soles (S/).

4. **Endpoints de Soporte API en `routes/web.php`:**
   - `POST /api/rooms/create` — Registro de nueva habitación.
   - `POST /api/rooms/update` — Modificación de piso, número o categoría.
   - `GET /api/categories` & `POST /api/categories` — Consulta y creación de categorías.
   - `GET /api/tariffs` & `POST /api/tariffs` — Consulta y creación de tarifas.

---

## 3. Reglas de Negocio

1. **Prioridad de Horas/Fechas Personalizadas:** Si se especifica `custom_check_out_time` manual, el sistema calcula la estancia con base en dicha hora exacta de salida.
2. **Validación de Número Único:** No se permite crear dos habitaciones con el mismo número.

---

## 4. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Check-In con Entrada, Salida y Precio Personalizado Manual
- **Given:** El modal o drawer de Check-in en una habitación libre.
- **When:** El recepcionista ingresa Fecha/Hora de Entrada manual, Fecha/Hora de Salida libre y un Precio personalizado (ej. S/ 95.00).
- **Then:** La estancia se registra en la BD con las horas exactas indicadas y el folio carga el monto manual ingresado.

### Criterio 2: Crear Nueva Habitación desde la UI
- **Given:** El usuario en la vista `/habitaciones`.
- **When:** Completa el formulario de "Nueva Habitación" (N° 501, Piso 5, Categoría Jacuzzi) y presiona Guardar.
- **Then:** La habitación se registra en la BD mediante la API y aparece inmediatamente en la tabla del cliente.

### Criterio 3: Verificación Completa `make verify`
- **Given:** Las modificaciones en `RoomService`, `RoomController`, vistas y JavaScript.
- **When:** Se ejecuta `make verify`.
- **Then:** Sintaxis PHP, PHPStan Nivel 9 y suite PHPUnit pasan en verde al 100%.
