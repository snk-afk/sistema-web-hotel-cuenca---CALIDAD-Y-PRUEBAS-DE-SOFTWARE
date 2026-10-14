# SPEC-0032: Interfaz Completa de Gestión de Limpieza (Housekeeping) y Mantenimiento (`/limpieza`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Gestión de Limpieza, Averías, Tareas de Mantenimiento y Liberación de Habitaciones
- **Dependencia:** SPEC-0008 (Backend de Housekeeping — Implementado)

---

## 1. Objetivo

Implementar la **interfaz completa y profesional** del módulo de Limpieza y Averías (`/limpieza`) para que el recepcionista, personal de limpieza y personal de mantenimiento puedan:

1. **Monitorear el Estado de Limpieza y Mantenimiento en Tiempo Real**:
   - Tarjetas de métricas: Tareas Pendientes, Tareas en Progreso, Habitaciones en Limpieza (`cleaning`), y Habitaciones Bloqueadas por Mantenimiento (`maintenance`).
2. **Gestionar el Ciclo de Vida de Tareas con 1 Clic**:
   - Pasar tarea de `pending` $\rightarrow$ `in_progress` (Iniciar).
   - Pasar tarea de `in_progress` / `pending` $\rightarrow$ `completed` (Completar y Liberar habitación automáticamente a `free`).
3. **Filtros Dinámicos por Estado y Tipo de Tarea**:
   - Tabs por estado: `Todas`, `Pendientes`, `En Progreso`, `Completadas`.
   - Buscador por número de habitación o notas.
4. **Modal para Crear Nueva Tarea de Limpieza o Avería**:
   - Selección de habitación.
   - Tipo de tarea: Limpieza Post-Checkout, Limpieza Profunda, Reparación de Mantenimiento / Avería, Inspección de Rutina.
   - Prioridad: Normal, Alta, Urgente.
   - Notas / Descripción del problema.

---

## 2. Alcance

### Dentro del Alcance:
- Vista `views/pages/housekeeping.php` rediseñada con métricas, tabs, tabla interactiva con acciones de 1-click y modal de nueva tarea.
- JavaScript `public/assets/js/pages/housekeeping.js` consumiendo `GET /api/housekeeping/tasks`, `POST /api/housekeeping/tasks`, `POST /api/housekeeping/tasks/{id}/start`, `POST /api/housekeeping/tasks/{id}/complete`.
- CSS `public/assets/css/pages/housekeeping.css` con diseño dark theme coherente.
- Pruebas automatizadas actualizadas.

### Fuera del Alcance:
- Registro de costos de mantenimiento en compras mayores (módulo 7/egresos).

---

## 3. Reglas de Negocio e Invariantes

1. **Liberación Automática de Habitación:**
   - Al completar una tarea de `checkout_cleaning` o `deep_cleaning`, la habitación pasa automáticamente de `cleaning` a `free` (libre y lista para hospedar).
   - Al crear una tarea de `maintenance_repair`, la habitación pasa a `maintenance` (bloqueada).
2. **Inmutabilidad y Auditoría:**
   - Se registra fecha/hora de inicio (`started_at`), fecha/hora de finalización (`completed_at`) y usuarios responsables.

---

## 4. Componentes UI de la Vista

### 4.1 Header
```
🧹 Tareas de Limpieza (Housekeeping) y Averías
Gestión de turnos de aseo, reportes de averías y liberación automática de habitaciones.
[➕ Nueva Tarea / Reporte de Avería]  [📊 Ir al Dashboard]
```

### 4.2 Métricas (4 tarjetas)
| Tarjeta | Icono | Descripción |
|---------|-------|-------------|
| Tareas Pendientes | ⏳ | Tareas con status `pending` |
| En Progreso | 🧼 | Tareas con status `in_progress` |
| En Limpieza | 🧹 | Habitaciones en estado `cleaning` |
| En Mantenimiento | 🔧 | Habitaciones en estado `maintenance` |

### 4.3 Tabla de Tareas
| Columna | Contenido |
|---------|-----------|
| # | ID de la tarea |
| Habitación | Número de habitación + categoría |
| Tipo de Tarea | Badge (Limpieza Checkout, Profunda, Avería/Reparación, Inspección) |
| Prioridad | Badge con color (Normal, Alta, Urgente) |
| Estado | Badge (Pendiente, En Curso, Completada) |
| Notas / Reporte | Detalle del problema |
| Acciones | Botón `▶️ Iniciar` y `✅ Completar y Liberar` |

---

## 5. Criterios de Aceptación (Given / When / Then)

### CA-1: Renderizado Completo de `/limpieza`
- **Given:** El usuario navega a `/limpieza`.
- **When:** La página carga.
- **Then:** Se muestran las 4 métricas, los tabs de estado, el buscador y la tabla de tareas con sus botones operativos.

### CA-2: Iniciar Tarea de Limpieza
- **Given:** Una tarea en estado `pending`.
- **When:** El usuario hace clic en `▶️ Iniciar`.
- **Then:** La tarea pasa a `in_progress`, se actualiza la interfaz y se muestra notificación de éxito.

### CA-3: Completar Tarea y Liberar Habitación
- **Given:** Una tarea de limpieza para la habitación 201 (`cleaning`).
- **When:** El usuario hace clic en `✅ Completar y Liberar`.
- **Then:** La tarea se marca como `completed`, la habitación 201 pasa a `free` en el rack y se muestra toast de éxito.

### CA-4: Crear Nueva Tarea de Mantenimiento / Avería
- **Given:** El usuario abre el modal de nueva tarea, selecciona la habitación 305 y el tipo `Reparación de Mantenimiento`.
- **When:** Envía el formulario.
- **Then:** La tarea se crea, la habitación 305 pasa a `maintenance` y la tabla se actualiza.

---

## 6. Archivos a Modificar / Crear

| Archivo | Acción |
|---------|--------|
| `views/pages/housekeeping.php` | **Reescribir** — Vista completa con métricas, tabs, tabla y modal de creación |
| `public/assets/js/pages/housekeeping.js` | **Crear** — Lógica de tareas, inicio, completado y filtros |
| `public/assets/css/pages/housekeeping.css` | **Crear** — Estilos dark theme para housekeeping |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Aserciones para `/limpieza` |
