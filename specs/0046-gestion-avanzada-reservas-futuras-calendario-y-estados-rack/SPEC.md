# SPEC-0046: Gestión Avanzada de Reservas Futuras, Calendario de Disponibilidad y Validación de Solapamientos en Rack

## 1. Objetivo
Dotar al Rack de Habitaciones de visibilidad completa sobre reservas futuras en habitaciones libres, integrar un visualizador/calendario de reservas por habitación, asegurar la separación visual y de comportamiento entre averías (`maintenance`) y reservas (`reserved`), y validar que ningún Check-In colisione con una reserva futura programada.

---

## 2. Contexto y Alcance

### Entra en el alcance:
1. **Diferenciación Visual y de Estados en Rack (`views/pages/dashboard.php`, `public/assets/css/pages/dashboard.css`):**
   - Habitación en estado `maintenance`: Badge rojo `⚠️ AVERÍA` / Tarjeta con borde rojo y acción `⚙️ Administrar Avería`.
   - Habitación en estado `reserved`: Badge púrpura/ámbar `📅 RESERVADA` con datos del huésped y acción `🏨 Ocupar Reserva`.
   - Habitación en estado `free` con reservas futuras: Tarjeta libre con tag/chip `📅 Reserva Programada (Huésped - Hora)` y botón `📅 Ver Calendario / Agenda`.
2. **Modal / Panel de Agenda de Reservas por Habitación:**
   - Permite consultar la lista cronológica de reservas activas para la habitación seleccionada.
3. **Validación de Solapamiento en Check-In (`src/Services/RoomService.php` y Frontend):**
   - Al realizar un Check-In en una habitación libre con reserva futura próxima, se valida que `expected_check_out_at <= next_reservation.check_in_date`.
   - Si colisiona, se emite error explicativo impidiendo el sobre-agendamiento.
4. **Pruebas Automatizadas Unit, Feature y E2E Playwright.**

### Queda fuera del alcance:
- Motor externo de Channel Manager (Booking/Airbnb).

---

## 3. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Visualización de Reservas Futuras en Tarjeta Libre
- **Given** una habitación en estado `free` que tiene una reserva agendada para más tarde o mañana,
- **When** el recepcionista visualiza el rack,
- **Then** la tarjeta muestra el chip de reserva futura con nombre del huésped y hora de llegada, junto al botón de ver agenda.

### Criterio 2: Validación Anti-Solapamiento en Check-in
- **Given** una habitación con reserva futura hoy a las 20:00,
- **When** se intenta hacer check-in por una tarifa de 12 horas a las 14:00 (salida 02:00 del día siguiente),
- **Then** el sistema bloquea la operación con excepción de dominio indicando que la salida calculada cruza con la reserva de las 20:00.

### Criterio 3: Claridad Absoluta entre Avería y Reservada
- **Given** una habitación con `status = 'maintenance'`,
- **When** se renderiza en el rack y se abre su modal,
- **Then** muestra claramente "Avería / En Mantenimiento" y nunca "Reservada".
