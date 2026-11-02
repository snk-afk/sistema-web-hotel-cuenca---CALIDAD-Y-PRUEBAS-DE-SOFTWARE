# SPEC-0025: Deep-Linking e Interconexión de Vistas con el Libro Maestro e Historial de Reservas

- **Fecha:** 2026-08-21
- **Estado:** Completado / Implementado
- **Módulo:** Interconexión de Módulos & Deep-Linking (`/`, `/huespedes`, `/habitaciones`, `/reservas`)

---

## 1. Objetivo

Consolidar la arquitectura operativa y de auditoría del hotel:
1. **Centro de Mando Operativo en Dashboard (`/`):** El rack y el Drawer contextual concentran toda la operativa diaria: Check-In Express con selector de huéspedes registrados, detección automática de reservas programadas y ejecución de Check-In en 1-clic.
2. **Libro Maestro, Auditoría e Historial (`/reservas`):** Módulo centralizado para rastreo, consulta histórica y auditoría de todas las reservas pasadas y futuras con filtros por estado (`pending`, `confirmed`, `checked_in`, `cancelled`) y buscador global.
3. **Interconexión Directa por URL:**
   - **Maestro de Huéspedes (`/huespedes`) $\rightarrow$ `/reservas?search={doc}`:** Consulta instantánea del historial de reservas del huésped.
   - **Gestión de Habitaciones (`/habitaciones`) $\rightarrow$ `/reservas?search={room}`:** Consulta del historial de reservas asociadas a la habitación.
   - **Apertura Directa de Detalle por URL (`/reservas?id={id}`):** Acceso directo a la ficha y auditoría de cualquier reserva.

---

## 2. Alcance y Arquitectura

### 2.1 Vistas y Componentes Frontend:
- **`views/components/room_drawer.php` & `public/assets/js/components/drawer.js`:**
  - Selector de huéspedes registrados (`#drawer-guest-select`) en el formulario de Check-In Express.
  - Detección y tarjeta informativa de reservas próximas para habitaciones libres con botón de Check-In directo (`executeDrawerReservationCheckin`).
- **`views/pages/reservations.php` & `public/assets/js/pages/reservations.js`:**
  - Libro Maestro e Historial con métricas globales, tabs por estado y búsqueda reactiva.
  - Ficha modal de auditoría y detalle (`modal-view-reservation`) con sincronización URL (`?id=...`).
- **`views/pages/guests.php` & `public/assets/js/pages/guests.js`:**
  - Botón *"📅 Reservas"* en tabla y ficha para consultar el historial de reservas por documento del huésped.
- **`views/pages/rooms.php`:**
  - Botón *"📅 Reservas"* en tabla para consultar el historial de reservas por número de habitación.

### 2.2 Backend & Controladores:
- **`App\Controllers\ViewController`:** Soporte completo de renderizado garantizando que las vistas tengan acceso a las listas maestras de habitaciones, tarifas, categorías y huéspedes.
- **`App\Services\ReservationService` & `App\Repositories\ReservationRepository`:**
  - Métodos existentes de catálogo y métricas reutilizados y optimizados para consultas por parámetros.

---

## 3. Reglas de Negocio e Invariantes

1. **Prioridad de Parámetros URL:**
   - Si la URL incluye `action=new`, se abre el modal de creación de reserva. Si además incluye `room_id` y/o `guest_id`, estos campos se inicializan automáticamente y se recalculan las tarifas y datos de contacto.
   - Si la URL incluye `id={id}` (o `res_id={id}`), se abre automáticamente el modal de detalle de dicha reserva.
2. **Sincronización no intrusiva:**
   - La actualización de la URL al escribir en el buscador o cambiar pestañas debe usar `history.replaceState` para no saturar el historial de navegación hacia atrás innecesariamente.
   - Al usar el botón "Atrás" del navegador (`popstate`), la vista debe reflejar el estado de filtros correspondiente sin recargar la página.
3. **Manejo de IDs no encontrados:**
   - Si se especifica un `id` de reserva inexistente en la URL, el sistema debe mostrar una alerta amigable (toast) y permanecer en el catálogo general sin romper la vista.

---

## 4. Parámetros de URL Soportados

| Parámetro | Tipo | Módulo Destino | Comportamiento |
|---|---|---|---|
| `action=new` | `string` | `/reservas` | Abre automáticamente el modal "➕ Nueva Reserva". |
| `room_id=10` | `int` | `/reservas` | Preselecciona la habitación ID 10 y activa sus tarifas. |
| `guest_id=5` | `int` | `/reservas` | Preselecciona el huésped titular ID 5 y sus datos de contacto. |
| `id=12` | `int` | `/reservas` | Abre automáticamente la ficha/modal de la reserva #12. |
| `status=confirmed` | `string` | `/reservas` | Activa la pestaña correspondiente (`all`, `pending`, `confirmed`, `checked_in`, `cancelled`). |
| `search=Perez` | `string` | `/reservas` | Filtra en tiempo real las filas de la tabla por el término indicado. |

---

## 5. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Deep Link de Creación desde Maestro de Huéspedes
- **Given:** Un huésped registrado con ID 2 ("Carlos Mendoza", DNI 45678912).
- **When:** El usuario hace clic en el botón "📅 Reservar" de su ficha o navega a `/reservas?action=new&guest_id=2`.
- **Then:** La página `/reservas` carga, abre el modal de Nueva Reserva y los campos de DNI, Nombre y Selector quedan preconfigurados con Carlos Mendoza.

### Criterio 2: Deep Link de Creación desde Dashboard / Rack de Habitaciones
- **Given:** La habitación 201 (ID 3) en estado Libre.
- **When:** El usuario selecciona "📅 Reservar a Futuro" o navega a `/reservas?action=new&room_id=3`.
- **Then:** Se abre el modal de Nueva Reserva con la Habitación 201 seleccionada y la tarifa base calculada.

### Criterio 3: Apertura Directa de Ficha de Reserva por URL
- **Given:** Una reserva confirmada con ID 14.
- **When:** El usuario accede a `/reservas?id=14`.
- **Then:** El modal de detalle de la reserva #14 se abre inmediatamente en pantalla con todas sus opciones de visualización, check-in y cancelación.

### Criterio 4: Persistencia y Sincronización de Filtros en la URL
- **Given:** El catálogo de reservas en `/reservas`.
- **When:** El recepcionista cambia a la pestaña "⏳ Pendientes" y escribe "Andrea".
- **Then:** La URL se actualiza a `/reservas?status=pending&search=Andrea` y al recargar el navegador se conservan la pestaña activa y los resultados filtrados.

### Criterio 5: Verificación Integral `make verify`
- **Given:** La implementación de los cambios en controladores, vistas, scripts JS y tests unitarios/feature.
- **When:** Se ejecuta `make verify`.
- **Then:** PHP lint, PHPStan nivel 9 y la suite completa de PHPUnit pasan al 100% en verde.
