# SPEC-0008: Limpieza (Housekeeping), Mantenimiento y Gestión de Tareas

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Tareas de Limpieza, Liberación de Habitaciones y Mantenimiento Preventivo/Correctivo

---

## 1. Objetivo

Establecer la estructura de datos, servicios y contratos para la asignación y seguimiento de tareas de limpieza (housekeeping) y mantenimiento, automatizando la transición de habitaciones de `cleaning` $\rightarrow$ `free` al completar la limpieza de checkout, y gestionando averías con bloqueo temporal en estado `maintenance`.

---

## 2. Alcance

### Dentro del Alcance:
- Generación automática o manual de Tareas de Limpieza (`housekeeping_tasks`) para habitaciones en estado `cleaning`.
- Asignación de tareas a personal de limpieza/cuarteros o personal de mantenimiento (`assigned_to_user_id`).
- Ciclo de vida de tareas: `pending` $\rightarrow$ `in_progress` $\rightarrow$ `completed` (o `cancelled`).
- Cambio automático del estado de la habitación de `cleaning` a `free` al marcar como completada la tarea de limpieza de checkout.
- Reporte de averías/mantenimiento pasando la habitación a `maintenance` y generando la tarea de reparación.
- Historial inmutable con tiempos de respuesta (inicio, término y notas de inspección).

### Fuera del Alcance (Módulos posteriores):
- Control de roles y permisos RBAC detallados (Módulo 9).

---

## 3. Reglas de Negocio

1. **Liberación de Habitación tras Limpieza:**
   - Una habitación en estado `cleaning` **solo** puede volver a estar `free` si se completa satisfactoriamente su tarea de limpieza asociada.

2. **Bloqueo por Mantenimiento:**
   - Una habitación en estado `maintenance` no puede ser hospedada ni reservada.
   - Al completar la tarea de mantenimiento, la habitación pasa a `cleaning` o `free` según se especifique en la finalización.

3. **Asignación y Trazabilidad:**
   - Cada tarea registra el usuario que la creó, el usuario asignado, fecha/hora de inicio (`started_at`) y fecha/hora de término (`completed_at`).

---

## 4. Modelo de Datos (SQL DDL)

```sql
-- Tareas de Limpieza y Mantenimiento
CREATE TABLE housekeeping_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id INT NOT NULL,
    task_type ENUM('checkout_cleaning', 'deep_cleaning', 'maintenance_repair', 'routine_inspection') NOT NULL DEFAULT 'checkout_cleaning',
    status ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    assigned_to_user_id INT NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    notes TEXT NULL,
    created_by_user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `GET /api/housekeeping/tasks` — Listado de tareas activas/pendientes filtradas por estado o tipo.
- `POST /api/housekeeping/tasks` — Crea manualmente una tarea de limpieza o reporte de mantenimiento.
- `POST /api/housekeeping/tasks/{id}/start` — Inicia una tarea (`status = in_progress`).
- `POST /api/housekeeping/tasks/{id}/complete` — Completa una tarea (`status = completed`) y libera la habitación.

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Liberación Automática de Habitación Limpia
- **Given:** Habitación `102` en estado `cleaning` con tarea de limpieza en estado `pending`.
- **When:** La mucama ejecuta `POST /api/housekeeping/tasks/{id}/complete`.
- **Then:** La tarea pasa a `completed`, registra `completed_at` y la habitación `102` pasa automáticamente a estado `free`.

### Criterio 2: Reporte de Avería y Bloqueo en Mantenimiento
- **Given:** Habitación `204` en estado `free`.
- **When:** Se reporta fuga de agua creando tarea `maintenance_repair` con prioridad `urgent`.
- **Then:** La habitación `204` pasa a estado `maintenance` y la tarea queda en `pending` asignada al técnico.

---

## 7. Casos Límite y Errores

1. **Completar Tarea Ya Completada:**
   - Retorna `HTTP 409 Conflict`.
2. **Intentar Hospedar Habitación en Mantenimiento:**
   - La API de habitaciones ya exige estado `free`, rebotando con `HTTP 409 Conflict`.

---

## 8. Transacciones PDO

- La finalización de tarea con liberación de habitación (`POST /api/housekeeping/tasks/{id}/complete`) se ejecuta en una transacción PDO atómica:
  ```php
  $db->beginTransaction();
  // 1. Actualizar housekeeping_tasks status = 'completed', completed_at = NOW()
  // 2. Actualizar rooms status = 'free' (o 'cleaning' si aplica)
  $db->commit();
  ```
