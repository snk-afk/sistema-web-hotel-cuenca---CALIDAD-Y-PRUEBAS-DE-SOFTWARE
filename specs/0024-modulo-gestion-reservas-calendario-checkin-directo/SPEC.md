# SPEC-0024: Módulo Integral de Reservas de Habitaciones, KPIs, Modales Reactivos y Conversión a Check-In

- **Fecha:** 2026-08-21
- **Estado:** Completado / Implementado
- **Módulo:** Gestión de Reservas (`/reservas`): Catálogo reactivo, control de estados, registro con anticipo y conversión directa a Check-in

---

## 1. Objetivo

Transformar la vista `/reservas` en un módulo completo, moderno y reactivo para la administración del ciclo de vida de las reservas hoteleras:
1. **Panel de Control y KPIs de Reservas:** Visualización instantánea de reservas activas, check-ins proyectados para hoy, monto total de anticipos recaudados y ocupación futura comprometida.
2. **Catálogo Reactivo con Filtros y Búsqueda:** Tabla detallada de reservas con filtros por estado (`Todas`, `Pendientes`, `Confirmadas`, `Check-in completado`, `Canceladas`), búsqueda en vivo por huésped, documento, habitación o fechas.
3. **Modal 1 (Nueva Reserva Reactiva):** Formulario dinámico con autocompletado y búsqueda de huéspedes en tiempo real (integrado con el padrón maestro de huéspedes), selección de habitación, categoría y tarifas dinámicas, cálculo automático de fecha/hora de salida estimada, control de anticipo monetario y notas.
4. **Modal 2 (Detalle y Acciones de Reserva):** Ficha de la reserva con acciones de 1-clic:
   - **Check-In Inmediato:** Transición atómica de la reserva a estancia activa (`Stay`), asignación de habitación a `Ocupada`, registro en `stay_guests`, aplicación del anticipo y apertura del Folio.
   - **Cancelar Reserva:** Liberación inmediata de disponibilidad.
5. **Validación de Disponibilidad y No-Solapamiento:** Control estricto para evitar reservas sobrelapadas en la misma habitación para el mismo rango temporal.

---

## 2. Alcance y Arquitectura

### 2.1 Backend (`App\Controllers`, `App\Services`, `App\Repositories`):
- **Repositorio de Reservas (`ReservationRepository`):**
  - Métodos: `findAll()`, `findById()`, `findAllActive()`, `hasOverlappingReservation()`, `create()`, `updateStatus()`, `update()`.
  - Método `getReservationsCatalog(?string $search, ?string $status)` para consulta completa con datos de habitación, huésped y tarifa.
- **Servicio de Reservas (`ReservationService`):**
  - `getReservationsCatalog(?string $search = null, ?string $status = null)`: Lista completa enriquecida con métricas de anticipos y estados.
  - `getReservationMetrics()`: Conteo de reservas activas, pendientes, confirmadas, check-ins de hoy y total de anticipos.
  - `createReservation(...)`: Validación de reglas de negocio, cálculo de checkout estimado y verificación de no-solapamiento.
  - `convertReservationToStay(int $reservation_id, int $user_id)`: Transacción PDO atómica para crear la estancia (`Stay`), vincular huéspedes (`stay_guests`), actualizar estado de la habitación a `occupied` y marcar la reserva como `checked_in`.
  - `cancelReservation(int $reservation_id)`: Cancelación de reserva.
- **Controladores y Rutas (`ReservationController`, `ViewController`, `routes/web.php`):**
  - `GET /reservas` (Renderizado de la vista con catálogo y métricas iniciales).
  - `GET /api/reservations` (Catálogo JSON con filtros de estado y búsqueda).
  - `GET /api/reservations/{id}` (Detalle de una reserva específica).
  - `POST /api/reservations` (Creación de reservas con soporte JSON y Form-Data).
  - `POST /api/reservations/{id}/checkin` (Ejecución de Check-in directo desde reserva).
  - `POST /api/reservations/{id}/cancel` (Cancelación de reserva).

### 2.2 Frontend (`views/pages/reservations.php`, `public/assets/js/pages/reservations.js`, `public/assets/css/pages/reservations.css`):
- **Tarjetas de KPIs:** Reservas activas, Próximos check-ins, Anticipos recaudados (S/) y Tasa de confirmación.
- **Pestañas y Filtros de Estado:** Badges interactivos para alternar entre Todos, Pendientes, Confirmadas, Checked-in y Canceladas.
- **Buscador en Tiempo Real:** Filtro rápido por nombre del huésped, documento, habitación o ID de reserva.
- **Modal 1 (Nueva Reserva):** Formulario con selección dinámica de habitación, tarifas calculadas automáticamente, selector/buscador de huésped y campo de anticipo.
- **Modal 2 (Detalle y Ejecución de Check-In):** Ficha de confirmación con botón destacado "🏨 Ejecutar Check-In" y "❌ Cancelar Reserva".

---

## 3. Reglas de Negocio e Invariantes

1. **Determinación Automática de Estado:**
   - Si una reserva se registra con anticipo mayor a `0.00`, su estado inicial es `confirmed`.
   - Si una reserva se registra con anticipo igual a `0.00`, su estado inicial es `pending`.
2. **Invariante de Disponibilidad (No-Solapamiento):**
   - No se permite crear una reserva si ya existe otra reserva activa (`pending` o `confirmed`) para la misma habitación cuyas fechas/horas se solapen.
3. **Conversión Atómica a Estancia:**
   - El check-in de una reserva requiere que la habitación esté en estado `free` (`Libre`).
   - Al ejecutar el check-in, dentro de una misma transacción PDO:
     1. La reserva pasa a estado `checked_in`.
     2. La habitación pasa a estado `occupied`.
     3. Se crea el registro `Stay` con la tarifa correspondiente.
     4. Se vincula al huésped titular en `stay_guests`.
4. **Protección de Reservas Completadas:**
   - Una reserva en estado `checked_in` o `cancelled` no puede ser vuelta a cancelar ni procesada por segunda vez.

---

## 4. Endpoints y Contratos de API

| Método | Endpoint | Descripción | Body / Parámetros | Respuesta Exitosa |
|---|---|---|---|---|
| `GET` | `/reservas` | Vista principal HTML | - | HTML `200 OK` |
| `GET` | `/api/reservations` | Listado JSON | `?search=...&status=...` | `{status: "success", data: [...], metrics: {...}}` |
| `GET` | `/api/reservations/{id}` | Detalle de reserva | - | `{status: "success", data: {...}}` |
| `POST` | `/api/reservations` | Crear nueva reserva | `{room_id, guest_id, tariff_id, check_in_at, advance_payment, notes}` | `{status: "success", data: {...}}` `201 Created` |
| `POST` | `/api/reservations/{id}/checkin` | Check-in directo | `{user_id}` | `{status: "success", data: {stay: ...}}` `200 OK` |
| `POST` | `/api/reservations/{id}/cancel` | Cancelar reserva | - | `{status: "success", message: "..."}` `200 OK` |

---

## 5. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Creación de Reserva con Anticipo y Estado Confirmado
- **Given:** Una habitación libre y un huésped existente.
- **When:** Se registra una reserva indicando fecha de entrada y un anticipo de S/ 30.00.
- **Then:** La reserva se almacena con estado `confirmed`, se calcula el checkout estimado según la tarifa y aparece en la lista de reservas.

### Criterio 2: Prevención de Reservas Solapadas
- **Given:** Una reserva confirmada para la Habitación 101 de 14:00 a 20:00.
- **When:** Se intenta registrar una nueva reserva para la Habitación 101 de 16:00 a 22:00.
- **Then:** El sistema rechaza la solicitud con error `409 Conflict` indicando que la habitación ya se encuentra reservada en ese horario.

### Criterio 3: Conversión de Reserva a Check-In Directo
- **Given:** Una reserva en estado `pending` o `confirmed` para la Habitación 102 (libre).
- **When:** El usuario hace clic en "Ejecutar Check-In".
- **Then:** La reserva pasa a `checked_in`, la habitación pasa a `occupied`, se genera la estancia (`Stay`) y se asocia al huésped como titular.

### Criterio 4: Cancelación de Reserva
- **Given:** Una reserva activa.
- **When:** El usuario cancela la reserva.
- **Then:** El estado cambia a `cancelled` y la habitación queda disponible para nuevos registros.

### Criterio 5: Verificación Integral `make verify`
- **Given:** Todo el código implementado con pruebas unitarias, de integración y E2E.
- **When:** Se ejecuta `make verify`.
- **Then:** Lint, PHPStan nivel 9 y PHPUnit pasan al 100% en verde sin errores ni advertencias.
