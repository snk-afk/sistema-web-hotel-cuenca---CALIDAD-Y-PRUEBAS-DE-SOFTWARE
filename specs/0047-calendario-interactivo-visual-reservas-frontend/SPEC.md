# SPEC-0047: Calendario Interactivo Visual de Reservas y Disponibilidad por Habitación (Frontend Avanzado)

## 1. Objetivo
Dotar a la vista de habitación y al modal adaptativo de un **Calendario Interactivo Visual de Alta Gama (Mes / Semana / Rango)** con renderizado de cuadrícula, días con colores según ocupación/reserva, visualización de bloques de tiempo y capacidad de seleccionar fechas directamente para crear o consultar reservas.

---

## 2. Alcance y Contexto
- **Incluye:**
  1. Componente UI de **Calendario Mensual/Semanal Dinámico** integrado en la pestaña `📅 Agenda de Reservas` del modal adaptativo y vista de habitación.
  2. Identificación visual de días/horas:
     - 🟢 Verde: Días 100% libres.
     - 🟡 Ámbar: Días con reservas pendientes/futuras parciales.
     - 🟣 Violeta: Días con reservas confirmadas o check-in programado.
     - 🔵 Azul: Días con ocupación activa.
  3. Navegación fluida de meses (Anterior / Siguiente / Hoy).
  4. Tooltip o panel interactivo al hacer clic en un día para ver el desglose de horarios y reservas de ese día.
  5. Botón directo de "Reservar en este día" desde el calendario.
- **No incluye:** Modificaciones a tablas de base de datos existentes (utiliza el modelo y queries existentes en `ReservationRepository`).

---

## 3. Criterios de Aceptación (Gherkin)

### Escenario 1: Renderizado del Calendario Mensual Interactivo
- **GIVEN** que el recepcionista abre el modal de una habitación y hace clic en `📅 Agenda de Reservas`.
- **WHEN** se carga la pestaña.
- **THEN** se renderiza la cuadrícula del mes actual con los días de la semana (Lun - Dom), los días del mes y el selector de mes anterior/siguiente.

### Escenario 2: Marcado visual de disponibilidad y reservas
- **GIVEN** que la habitación tiene una reserva confirmada para el día 25 de 14:00 a 20:00.
- **WHEN** se observa el día 25 en el calendario.
- **THEN** el día aparece resaltado con el badge y color de reserva.
- **AND** al hacer clic en el día 25, se despliegan los detalles de la reserva (Huésped, Rango de Horas, Adelanto).

### Escenario 3: Interacción para agendar nueva reserva
- **GIVEN** que el recepcionista hace clic en un día libre del calendario.
- **WHEN** presiona "Agendar Reserva en esta fecha".
- **THEN** se conmuta a la pestaña de registro con la fecha automáticamente precargada en el formulario de reserva futura.

---

## 4. Seguridad y Rendimiento
- Renderizado 100% en el cliente en JS nativo (Vanilla JS) sin librerías pesadas externas, manteniendo la velocidad de carga instantánea.
- Escapado estricto `htmlspecialchars` / `JSON_HEX_*` en la inyección de datos.
