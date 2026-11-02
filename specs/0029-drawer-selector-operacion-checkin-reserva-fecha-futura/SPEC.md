# SPEC-0029: Selector de Operación (Check-In Inmediato vs Reserva Futura) con Fechas y Tarifas Dinámicas en el Drawer del Dashboard

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Dashboard Drawer — Operación de Hospedaje y Reservas Futuras
- **Dependencia:** SPEC-0024, SPEC-0025

---

## 1. Objetivo

Unificar y potenciar la pestaña **Operación** del Drawer en habitaciones libres (`/`) para permitir dos flujos de trabajo claros:
1. **⚡ Check-In Inmediato (Hospedar Ahora)**:
   - Registro inmediato con fecha/hora actual (o manual).
   - Ocupa la habitación en tiempo real (`status = 'occupied'`).
2. **📅 Crear Reserva a Futuro**:
   - Selector de **Fecha y Hora de Llegada Futura** (`datetime-local`).
   - Selección por **Tarifa Estándar** (24h, Noche, Día, Horas) que **calcula automáticamente la fecha y hora de salida prevista** sumándole la duración de la tarifa a la fecha de llegada seleccionada.
   - O selección por **Horario Manual Libre** (ingreso manual de salida).
   - Registro de anticipo / adelanto (opcional) con método de pago.
   - Envía a `POST /api/reservations` con `action = 'create'`, dejando la habitación libre para hoy y agendada para la fecha futura.

---

## 2. Alcance

### Dentro del Alcance:
- **Pill Switcher en el Drawer**: `⚡ Check-In Inmediato` vs `📅 Reserva a Futuro`.
- **Selector de Fecha/Hora de Llegada Futura**:
  - Al cambiar la fecha futura o al seleccionar una tarifa, el sistema calcula automáticamente la **Fecha/Hora de Salida Estimada** (`check_in_futuro + duration_hours`).
- **Envío Dual**:
  - Si es Check-In Inmediato → envía a `POST /api/rooms` con `action: 'occupy'`.
  - Si es Reserva Futura → envía a `POST /api/reservations` con `action: 'create'` y datos de tarifa/anticipo.
- Pruebas automatizadas actualizadas.

### Fuera del Alcance:
- Modificaciones al libro maestro `/reservas` (ya consolidado como auditoría).

---

## 3. Reglas de Negocio e Invariantes

1. **Cálculo Automático de Salida con Tarifas en Reservas Futuras:**
   - $\text{Fecha Salida} = \text{Fecha Llegada Futura} + \text{duration\_hours (Tarifa)}$.
   - Ejemplo: Llegada `2026-08-25 14:00` con Tarifa `Noche (12 horas)` $\rightarrow$ Salida calculada: `2026-08-26 02:00`.
   - Ejemplo: Llegada `2026-08-25 12:00` con Tarifa `24 Horas` $\rightarrow$ Salida calculada: `2026-08-26 12:00`.
2. **Validación de Fechas Futuras:**
   - La fecha de Check-In para una reserva no puede ser en el pasado.
3. **Estado de Habitación:**
   - Una reserva futura **no cambia el estado actual de la habitación a ocupada hoy**, se mantiene `free` lista para ser usada hasta que llegue la fecha o se haga check-in.

---

## 4. Componentes UI en el Drawer

1. **Selector de Modo Operativo (Arriba del formulario en Tab Operación):**
   ```
   [⚡ Check-In Inmediato]  [📅 Reserva a Futuro]
   ```
2. **Campo de Fecha de Ingreso Futuro (visible si está en modo Reserva):**
   ```
   📅 Fecha y Hora de Llegada Prevista: [ input datetime-local ]
   ```
3. **Selector de Tarifa con Vista Previa de Salida:**
   ```
   ⚡ Tarifa: [ Tarifa 24 Horas (S/ 50.00) ]
   ⌛ Salida Calculada: 26/08/2026 14:00
   ```
4. **Botón Dinámico de Envío:**
   - Modo Check-In: `✅ Confirmar Check-In y Registrar`
   - Modo Reserva: `📅 Confirmar y Agendar Reserva Futura`

---

## 5. Criterios de Aceptación (Given / When / Then)

### CA-1: Switcher Operativo en Drawer
- **Given:** Se abre el drawer de una habitación libre.
- **When:** Se navega a la pestaña `Operación`.
- **Then:** Se visualiza el switcher `⚡ Check-In Inmediato` / `📅 Reserva a Futuro`.

### CA-2: Reserva Futura con Cálculo Automático por Tarifa
- **Given:** Modo `Reserva a Futuro` seleccionado con fecha de llegada `2026-09-01 12:00`.
- **When:** El usuario selecciona la tarifa `24 Horas`.
- **Then:** El sistema calcula y muestra la salida prevista para `2026-09-02 12:00` y el precio de la tarifa.

### CA-3: Agendamiento Exitoso de Reserva Futura
- **Given:** Formulario de Reserva Futura completado con huésped, tarifa y anticipo S/ 20.00.
- **When:** El usuario hace clic en `📅 Confirmar y Agendar Reserva Futura`.
- **Then:** Se envía la petición a `/api/reservations`, la reserva queda registrada, se muestra toast de éxito y la habitación permanece `free`.

---

## 6. Archivos a Modificar

| Archivo | Modificación |
|---------|-------------|
| `views/components/room_drawer.php` | Agregar switcher de operación, campo de llegada futura y salida calculada |
| `public/assets/js/components/drawer.js` | Funciones `toggleOperationMode()`, cálculo automático de salida por tarifa + fecha base, envío a `/api/reservations` |
| `tests/Feature/Controllers/ViewControllerTest.php` | Assertions de los nuevos controles en el Dashboard drawer |
