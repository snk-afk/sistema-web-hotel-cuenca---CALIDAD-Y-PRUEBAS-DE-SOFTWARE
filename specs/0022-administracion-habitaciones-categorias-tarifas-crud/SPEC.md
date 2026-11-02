# SPEC-0022: Módulo Integral de Administración de Habitaciones, Categorías y Tarifas (CRUD Completo y UI/UX Avanzada)

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Gestión de Inventario Hotelero (`/habitaciones`): CRUD de Habitaciones, Categorías y Tarifas

---

## 1. Objetivo

Transformar la vista `/habitaciones` en un módulo de administración hotelera de primer nivel, eliminando datos estáticos y dotándolo de funcionalidad integral y reactiva:
1. **CRUD Real y Completo de Habitaciones:** Creación, edición, cambio de estado operativo (`Libre`, `Limpieza`, `Avería/Mantenimiento`) con validaciones de unicidad de número y pertenencia de categoría.
2. **CRUD Real de Categorías:** Listado dinámico desde la BD, creación y edición de categorías (`Simple`, `Matrimonial`, `Suite Jacuzzi`, etc.) con conteo de habitaciones asociadas.
3. **CRUD Real de Tarifas y Precios:** Listado dinámico, creación y actualización de tarifas vinculadas a su categoría (ej. 12h, 24h, amanecida) con precios base y control de estado activo/inactivo.
4. **UI/UX Moderna y Reactiva:** Pestañas fluidas, buscadores instantáneos, modales interactivos con selectores poblados dinámicamente y notificaciones toast con manejo de errores del servidor.

---

## 2. Alcance y Arquitectura

### 2.1 Backend (`App\Controllers`, `App\Services`, `App\Repositories`):
- **Controlador de Habitaciones (`RoomController`):**
  - Enriquecer `viewRooms()` para cargar `$rooms`, `$categories` y `$tariffs` reales desde la BD.
  - Implementar acciones `create` y `update` en `POST /api/rooms`.
- **Controlador de Categorías (`CategoryController` / `routes/web.php`):**
  - Endpoint `GET /api/categories` para consultar catálogo completo.
  - Endpoint `POST /api/categories` para crear y actualizar categorías.
- **Controlador de Tarifas (`RoomController::tariffs` / `POST /api/tariffs`):**
  - Endpoint `POST /api/tariffs` para crear y actualizar tarifas.
  - Soporte de activación/desactivación de tarifas.

### 2.2 Frontend (`views/pages/rooms.php`, `public/assets/js/pages/rooms.js`, `public/assets/css/pages/rooms.css`):
- **Pestaña 1 (Inventario de Habitaciones):**
  - Filtro buscador por número, categoría, piso y estado.
  - Tabla con badges de color y botones operativos (`✏️ Editar`, `🛠️ Mantenimiento / ✅ Habilitar`).
  - Modal dinámico de Creación/Edición con validación de inputs y feedback inmediato.
- **Pestaña 2 (Categorías de Habitación):**
  - Visualización de categorías reales registradas en el sistema.
  - Modal de Creación/Edición de Categoría.
- **Pestaña 3 (Tarifas y Precios):**
  - Visualización de tarifas agrupadas o filtrables por categoría con duración y precio en Soles (S/).
  - Modal de Creación/Edición de Tarifa con selector de categoría.

---

## 3. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Creación y Edición de Habitación
- **Given:** El administrador ingresa a `/habitaciones` y abre el modal "➕ Nueva Habitación".
- **When:** Ingresa número `301`, piso `3`, selecciona categoría `Matrimonial` y envía el formulario.
- **Then:** La habitación se guarda en la base de datos, aparece en la tabla y en el Rack de recepción con su categoría correspondiente.

### Criterio 2: Creación de Nueva Categoría
- **Given:** El administrador está en la pestaña "🏷️ Categorías".
- **When:** Crea la categoría `Suite Presidencial` con descripción `Suite de lujo piso superior`.
- **Then:** La categoría se guarda en la BD y aparece disponible de inmediato en los selectores de habitaciones y tarifas.

### Criterio 3: Creación y Vinculación de Tarifa
- **Given:** Existe la categoría `Suite Presidencial`.
- **When:** Se registra una tarifa `Amanecida Especial`, duración `8` horas y precio `S/ 120.00`.
- **Then:** La tarifa se guarda y queda disponible para Check-in cuando se hospeda una habitación de esa categoría.

### Criterio 4: Verificación Integral `make verify`
- **Given:** Todo el código implementado con pruebas unitarias y de integración.
- **When:** Se ejecuta `make verify`.
- **Then:** Lint, PHPStan nivel 9 y PHPUnit pasan al 100% en verde.
