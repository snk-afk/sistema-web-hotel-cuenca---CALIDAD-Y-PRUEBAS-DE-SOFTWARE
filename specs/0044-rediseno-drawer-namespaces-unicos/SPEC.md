# SPEC-0044: Rediseño Estético del Drawer con Clases y Namespaces Exclusivos (`ns-drawer`)

## 1. Objetivo
Rediseñar por completo el componente deslizable lateral (Drawer de Habitación) para que tenga una apariencia de lujo, ultra moderna y profesional, eliminando cualquier reutilización de clases genéricas externas (`.card`, `.form-control`, `.btn-block`, etc.) e introduciendo una arquitectura de estilos con namespace propio e independiente: `.ns-drawer`, `.ns-drawer__*`.

---

## 2. Contexto y Alcance

### Entra en el alcance:
1. **Namespace BEM Exclusivo (`public/assets/css/components/drawer.css`):**
   - `.ns-drawer__overlay` (backdrop blur oscuro y fluido).
   - `.ns-drawer__panel` (panel lateral con gradiente de fondo, elevación y borde iluminado).
   - `.ns-drawer__header`, `.ns-drawer__title`, `.ns-drawer__badge`, `.ns-drawer__close`.
   - `.ns-drawer__nav`, `.ns-drawer__tab` (barra segmented con animaciones activas).
   - `.ns-drawer__body`, `.ns-drawer__card`, `.ns-drawer__info-row`, `.ns-drawer__timer-box`.
   - `.ns-drawer__form-group`, `.ns-drawer__input`, `.ns-drawer__select`, `.ns-drawer__btn`.
   - `.ns-drawer__pill-group`, `.ns-drawer__pill-option`.
2. **Actualización de la Vista (`views/components/room_drawer.php`):**
   - Migración de las etiquetas HTML para utilizar las clases exclusivas `.ns-drawer__*`.
   - Preservación exacta de todos los `id="..."`, `name="..."` y llamadas `onclick/onsubmit` requeridas por `public/assets/js/components/drawer.js`.
3. **Actualización de `public/assets/js/components/drawer.js`:**
   - Asegurar que la apertura, cierre, conteo de cronómetro y alternancia de pestañas interactúen perfectamente con las nuevas clases de estado `.ns-drawer__*`.

### Queda fuera del alcance:
- Alteración de endpoints del backend o lógica de BD.

---

## 3. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Namespace BEM 100% exclusivo
- **Given** el archivo `public/assets/css/components/drawer.css`,
- **When** se examinan sus selectores,
- **Then** todas las reglas pertenecen al namespace `.ns-drawer__*` sin depender de clases genéricas externas.

### Criterio 2: Visual moderno y espaciado de alta gama
- **Given** que el recepcionista abre el Drawer de cualquier habitación en el Rack,
- **When** navega por las pestañas (Resumen, Operación, POS Consumos, Gestión),
- **Then** la cabecera, tabs, formularios de check-in/checkout y tarjetas de folio muestran un diseño armónico, sin solapamientos, con tipografía clara y botones de acción llamativos.

### Criterio 3: Integración JS y Pruebas
- **Given** la suite de pruebas automatizadas,
- **When** se ejecuta `make verify`,
- **Then** el 100% de tests unitarios, de integración, feature y CSS pasan en verde.

---

## 4. Pruebas y Verificación
- Pruebas en `tests/Feature/RoomDrawerRedesignTest.php` validando:
  1. Presencia y exclusividad del namespace `.ns-drawer` en `drawer.css` y `room_drawer.php`.
  2. Apertura y funcionamiento del Drawer.
  3. Ejecución de `make verify` en verde.
