# SPEC-0018: Arquitectura Modular Frontend UI/UX 4.0 — Separación Estricta de CSS y JS por Componentes y Vistas

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Arquitectura de Archivos Estáticos Frontend (`public/assets/css/` & `public/assets/js/`), Modularización y Desacoplamiento de Vistas

---

## 1. Objetivo

Eliminar los archivos monolíticos compartidos (`style.css` y `app.js`) y establecer una **Arquitectura Frontend Modular Desacoplada (UI 4.0)**, organizando los estilos CSS y los scripts JS en archivos independientes según su responsabilidad (base, diseño general, páginas específicas y componentes reutilizables), garantizando que los estilos de una vista no interfieran ni colisionen con las demás.

---

## 2. Alcance

### Dentro del Alcance:
1. **Estructura Modular de Estilos CSS (`public/assets/css/`):**
   ```text
   public/assets/css/
   ├── base.css              # Variables CSS, reset, tipografía, botones, tablas y badges
   ├── layout.css            # Header general, Sidebar navegación lateral y Footer
   ├── components/
   │   ├── modal.css         # Estilos para modales, overlays y alertas flotantes (toasts)
   │   ├── drawer.css        # Estilos para el Slide-over Room Drawer y Pill Group switchers
   │   └── pos.css           # Estilos para tarjetas de tienda POS y carrito
   └── pages/
       ├── login.css         # Estilos exclusivos de la pantalla de Login
       ├── dashboard.css     # Estilos exclusivos del Rack de Habitaciones y buscador
       └── rooms.css         # Estilos exclusivos del módulo de gestión de habitaciones
   ```

2. **Estructura Modular de Controladores JS (`public/assets/js/`):**
   ```text
   public/assets/js/
   ├── core/
   │   ├── api.js            # Cliente HTTP Fetch API, alertas toast y manejo global de errores
   │   └── timers.js         # Lógica de cuenta regresiva de temporizadores de hospedaje
   ├── components/
   │   ├── drawer.js         # Controlador del Slide-over Room Drawer (tabs, forms condicionales)
   │   └── modals.js         # Control de apertura y cierre de modales
   └── pages/
       ├── login.js          # Lógica exclusiva de envío de formulario de Login
       ├── dashboard.js      # Filtrado en tiempo real del Rack y contadores de estado
       └── rooms.js          # Control de pestañas y modales de /habitaciones
   ```

3. **Carga Modular en Vistas y Layouts (`views/layouts/` & `views/pages/`):**
   - Inclusión limpia en los headers HTML (`header.php` / `login.php`) cargando solo el CSS y JS necesario según la vista activa.

---

## 3. Reglas de Negocio

1. **Nombres de Clases Aislados (BEM / Scoped Namespaces):** Cada vista utiliza prefijos de clase específicos para evitar colisiones de estilos entre páginas.
2. **Cero Dependencias Circulares:** Ningún módulo JS depende de variables globales no inicializadas.

---

## 4. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Desacoplamiento Completo de CSS y JS
- **Given:** El sistema de archivos `public/assets/`.
- **When:** Se navega a cualquier vista del sistema (`/login`, `/`, `/habitaciones`).
- **Then:** Solo se cargan los módulos CSS y JS correspondientes a dicha página, logrando cero interferencia visual o de scripts.

### Criterio 2: Mantenibilidad y Limpieza en Vistas
- **Given:** Las plantillas PHP en `views/`.
- **When:** Se examina el código fuente servido al navegador.
- **Then:** Las etiquetas `<link rel="stylesheet">` y `<script>` están organizadas por componentes sin archivos monolíticos.

### Criterio 3: Verificación Completa `make verify`
- **Given:** La nueva estructura modular de archivos estáticos.
- **When:** Se ejecuta `make verify`.
- **Then:** Pruebas automatizadas PHPUnit, linter y PHPStan Nivel 9 pasan en verde al 100%.
