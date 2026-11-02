# SPEC-0043: Remasterización Visual del Dashboard (Rack de Habitaciones, Tarjetas de Estado, Drawer y Filtros)

## 1. Objetivo
Transformar completamente la experiencia visual del Dashboard principal (`http://localhost:8080/`), convirtiéndolo en un Rack de Habitaciones interactivo, moderno, elegante y de alto impacto visual (estilo PMS Hotelero Moderno / Dark SaaS Luxury). Se remasterizarán las tarjetas de habitación por estado, la barra de filtros en vivo, los contadores de estado (pills) y el panel contextual lateral (Drawer).

---

## 2. Contexto y Alcance

### Entra en el alcance:
1. **Barra de Control y Filtros del Dashboard (`public/assets/css/pages/dashboard.css`):**
   - Barra superior estilizada con buscador expandible y efecto glassmorphism.
   - Píldoras de estado (`.stat-pill`) con diseño moderno, badges con conteo dinámico, iconos vibrantes y micro-interacciones hover con elevación sutil.
2. **Remasterización de Tarjetas de Habitación (`.room-card`):**
   - Grid auto-fit responsivo con separación visual óptima (gap de 20px).
   - Indicador superior de estado con gradiente luminoso (`linear-gradient`) según estado:
     - 🟢 **Libre:** Borde sutil esmeralda `#10b981`, acento verde menta y botón rápido de check-in estilizado.
     - 🔵 **Ocupada:** Gradiente azul zafiro `#3b82f6`, tarjeta de huésped con chip de documento, timer de estancia y saldo.
     - 🔴 **Vencida / Overdue:** Borde y resplandor pulsante rojo rubí `#ef4444`, etiqueta de tiempo excedido destacada.
     - 🟡 **Limpieza / Avería:** Acentos ámbar `#f59e0b` y púrpura `#8b5cf6` con botones rápidos contextuales.
   - Tipografía de número de habitación destacada (fuente grande, piso en chip discreto).
   - Botones de acción inferior (`.btn-card-action`) con micro-interacciones fluidas.
3. **Remasterización del Drawer Contextual (`public/assets/css/components/drawer.css`):**
   - Backdrop blur oscuro (`rgba(5, 8, 15, 0.75)` con `backdrop-filter: blur(8px)`).
   - Panel deslizable con borde izquierdo iluminado, header refinado y botón de cerrar circular.
   - Tabs internas del Drawer con diseño pill/segmented bar moderno.
   - Tarjetas informativas internas (`.drawer-info-card`, `.drawer-timer-card`, `.drawer-folio-card`) con fondos contrastados y tipografía limpia.

### Queda fuera del alcance:
- Modificación de endpoints backend o lógica de base de datos MySQL.

---

## 3. Reglas de Negocio e Invariantes Visuales

1. **Diferenciación Inmediata de Estados:** Cada habitación debe transmitir su estado (Libre, Ocupada, Vencida, Limpieza, Avería) a primera vista mediante color, badge e indicador superior.
2. **Tiempos en Vivo Legibles:** El cronómetro de estancia y los estados de sobretiempo deben ser altamente visibles y diferenciados.
3. **Consistencia de Espaciado:** El grid y los elementos internos de cada tarjeta deben mantener alineación estricta y alturas proporcionadas.
4. **Interactividad Fluida:** La apertura y cierre del Drawer debe sentirse instantánea y suave en escritorio y móvil.

---

## 4. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Barra superior y píldoras de filtrado remasterizadas
- **Given** que el usuario ingresa al Dashboard (`/`),
- **When** visualiza la barra superior de control,
- **Then** las píldoras de filtrado presentan estilos modernos por estado con conteos legibles y el buscador tiene halo de enfoque azul.

### Criterio 2: Tarjetas de habitación con diseño moderno por estado
- **Given** el rack de habitaciones renderizado,
- **When** se observan habitaciones en diferentes estados (libre, ocupada, vencida, limpieza),
- **Then** cada tarjeta cuenta con barra indicadora superior con gradiente, tipografía de cabecera clara, sección de huésped/balance estructurada y botón de acción estilizado.

### Criterio 3: Drawer contextual deslizable estilizado
- **Given** que el recepcionista hace clic en una habitación,
- **When** se abre el Drawer lateral,
- **Then** el fondo se desenfoca con backdrop blur, el panel desliza suavemente desde la derecha y las secciones de resumen, operación y folio cuentan con tarjetas internas pulidas.

---

## 5. Pruebas y Verificación
- Pruebas en `tests/Feature/DashboardVisualRemasterTest.php` validando:
  1. Clases de estado, gradientes y animaciones en `dashboard.css`.
  2. Reglas visuales del Drawer en `drawer.css`.
  3. Ejecución de `make verify` (PHP lint + PHPStan nivel 9 + PHPUnit) en verde.
