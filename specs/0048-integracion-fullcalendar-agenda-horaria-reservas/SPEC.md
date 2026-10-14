# SPEC-0048: Integración de FullCalendar v6 para Agenda Horaria y Multidía de Reservas por Habitación

## 1. Objetivo
Integrar **FullCalendar v6** (autohospedado localmente) en la pestaña `📅 Agenda de Reservas` del modal adaptativo de habitación y en el sistema de gestión, permitiendo la visualización y agendamiento interactivo en vistas de **Mes (dayGridMonth)**, **Semana por Horas (timeGridWeek)** y **Día por Horas (timeGridDay)** con soporte de franjas horarias exactas (horas y minutos), reservas multidía y bloques visuales de ocupación.

---

## 2. Alcance y Contexto
- **Incluye:**
  1. Alojamiento local de los assets bundle de **FullCalendar v6** en `public/assets/vendor/fullcalendar/index.global.min.js`.
  2. Inicialización del calendario dinámico en `#drawer-fullcalendar-container` cuando se abre la pestaña `📅 Agenda de Reservas`.
  3. Soporte de vistas conmutables:
     - `dayGridMonth`: Panorama general de ocupación del mes.
     - `timeGridWeek`: Vista semanal con franjas horarias (00:00 a 24:00) para ver bloques de horas libres y ocupadas.
     - `timeGridDay`: Vista detallada del día con precisión de intervalos.
  4. Mapeo de eventos en tiempo real:
     - Reservas Confirmadas: Bloques violetas (`#8b5cf6`).
     - Reservas Pendientes: Bloques ámbar (`#f59e0b`).
     - Hospedaje Activo (Stay): Bloque azul (`#3b82f6`).
  5. Interacción de Selección (`dateClick` / `select`): Al hacer clic en un rango horario libre, conmuta automáticamente al formulario de reserva precargando la fecha y hora seleccionadas.
  6. Idioma en español (`es`) con formato de 24 horas (`HH:mm`).
- **No incluye:** Modificaciones a tablas de base de datos existentes (utiliza `reservations` y `stays`).

---

## 3. Criterios de Aceptación (Gherkin)

### Escenario 1: Renderizado de FullCalendar con vistas mensual y horaria
- **GIVEN** que el usuario abre el modal de una habitación y hace clic en `📅 Agenda de Reservas`.
- **WHEN** se activa la pestaña.
- **THEN** se inicializa FullCalendar mostrando la cuadrícula con botones de conmutación: `Mes`, `Semana`, `Día`, `Hoy`, `Anterior` y `Siguiente`.

### Escenario 2: Visualización de bloques horarios exactos
- **GIVEN** que una habitación tiene una reserva de 14:00 a 18:00 hoy.
- **WHEN** se cambia a la vista `Semana` o `Día`.
- **THEN** se dibuja un bloque vertical coloreado exactamente entre las 14:00 y las 18:00 con el nombre del huésped y estado.
- **AND** el resto de horas (00:00-14:00 y 18:00-24:00) aparecen claramente libres para nuevas estancias o reservas.

### Escenario 3: Agendamiento interactivo por clic en horario
- **GIVEN** que el recepcionista hace clic en el horario libre de las 18:30 de un día en la vista horaria.
- **WHEN** hace clic en el slot.
- **THEN** el sistema abre el formulario de registro con la fecha/hora `YYYY-MM-DD 18:30` precargada en la modalidad de reserva futura.

---

## 4. Seguridad y Rendimiento
- Dependencia local en `public/assets/vendor/fullcalendar/` para operación 100% offline (sin llamadas a CDN externos).
- Renderizado reactivo sin fugas de memoria (destrucción e instanciación limpia de instancias de calendario al cambiar de habitación).
