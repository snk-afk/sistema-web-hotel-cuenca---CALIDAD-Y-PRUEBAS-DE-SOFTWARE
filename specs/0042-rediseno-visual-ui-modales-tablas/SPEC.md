# SPEC-0042: Rediseño Visual Moderno del Frontend (UI Dark SaaS, Modales, Tablas e Inputs)

## 1. Objetivo
Transformar radicalmente la apariencia visual de la interfaz web de CS-Hotel para convertirla en un sistema de gestión hotelera moderno, pulido y profesional (estilo Dashboard SaaS Dark Luxury), solucionando los problemas de descuadre, falta de espaciado, tablas toscas, modales poco estéticos e inputs desalineados.

---

## 2. Contexto y Alcance

### Entra en el alcance:
1. **Design System & Variables Globales (`public/assets/css/base.css`):**
   - Paleta de color premium: fondo base `#0b0f19`, superficies de tarjeta `#141c2e`, bordes sutiles `rgba(148, 163, 184, 0.12)`, acentos azul primario `#3b82f6` e índigo `#6366f1`.
   - Sistema de sombras suaves con elevación (`box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4)`).
   - Formularios e inputs homogéneos con altura estándar de 42px, padding balanceado y efecto glow en focus.
   - Badges y estados con bordes redondeados y micro-contrastes legibles.
2. **Rediseño de Modales (`public/assets/css/components/modal.css`):**
   - Modal card con backdrop blur (`backdrop-filter: blur(8px)`), bordes pulidos y animación de entrada suave.
   - Header del modal con tipografía clara y botón de cierre circular estilizado.
   - Estructura limpia de modales: body con scroll controlado y espaciado de 24px, formularios en grid de 2 columnas donde corresponda.
   - Footer de acciones con alineación derecha y botones de jerarquía primaria/secundaria definidos.
3. **Rediseño de Tablas de Datos (`public/assets/css/base.css` / `.data-table`, `.table`):**
   - Encabezados de tabla con fondo sutil `#0f172a`, bordes finos y texto uppercase refinado.
   - Celdas con padding amplio (14px 16px), alineación vertical centrada, texto de alta legibilidad (`#e2e8f0`).
   - Hover de fila con transición suave (`rgba(59, 130, 246, 0.05)`).
   - Columnas numéricas (precios, importes) alineadas a la derecha y badges centrados.
4. **Header y Navegación Lateral (`public/assets/css/layout/header.css`, `nav.css`):**
   - Barra superior con efecto glassmorphism, chip de usuario y badge de estado de caja moderno.
   - Sidebar con items de menú espaciados, bordes redondeados, iconos legibles y estado activo con gradiente sutil.
   - Comportamiento responsive optimizado en móviles con animación fluida del menú lateral.

### Queda fuera del alcance:
- Modificación de endpoints de API backend o alteración de schema de base de datos MySQL.

---

## 3. Reglas de Negocio e Invariantes Visuales

1. **Jerarquía Visual Clara:** Los títulos de sección, tarjetas de métricas, tablas de datos y modales deben mantener un ritmo vertical consistente (espaciados múltiplos de 4px / 8px).
2. **Legibilidad y Contraste:** Todos los textos principales deben tener un ratio de contraste accesible sobre fondos oscuros (texto principal `#f8fafc`, secundario `#94a3b8`, placeholders `#64748b`).
3. **No Overflows Horizontales Descuadrados:** Las tablas deben estar contenidas en wrappers `.table-responsive` que no rompan el layout en pantallas reducidas.
4. **Modales Centrados y Responsivos:** Los modales nunca deben desbordar el alto de la pantalla ni quedar cortados en dispositivos móviles (máx 92vh con scroll interno).

---

## 4. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Modales pulidos y modernos
- **Given** que el usuario abre cualquier modal del sistema (ej. Nueva Reserva, Registrar Habitación, Cierre de Turno, Emitir Comprobante),
- **When** se renderiza la ventana modal,
- **Then** el contenedor tiene fondo de tarjeta oscuro, bordes sutiles, sombra de elevación, botón de cierre circular con hover y espaciado interno equilibrado sin solapamientos.

### Criterio 2: Tablas de datos alineadas y estilizadas
- **Given** que el usuario navega a cualquier módulo con tablas (Habitaciones, Reservas, Folios, Tienda, Caja, Usuarios, Facturación),
- **When** visualiza los listados de datos,
- **Then** las cabeceras están claramente diferenciadas, las filas tienen padding generoso con efecto hover suave, y los importes monetarios están formateados y alineados correctamente.

### Criterio 3: Formularios e inputs uniformes
- **Given** cualquier formulario de entrada en vistas o modales,
- **When** se enfocan los campos de texto o selects,
- **Then** presentan bordes definidos con iluminación sutil (`ring / glow focus`), altura uniforme y etiquetas de formulario legibles.

### Criterio 4: Header, Sidebar y Menú Móvil
- **Given** un usuario navegando en PC o dispositivo móvil,
- **When** interactúa con el menú de navegación,
- **Then** en escritorio se muestra el sidebar estilizado con enlace activo destacado; en móvil (< 768px), el botón hamburguesa abre un menú off-canvas suave sin descuadrar el contenido principal.

---

## 5. Pruebas y Verificación
- Pruebas en `tests/Feature/FrontendVisualDesignTest.php` verificando:
  1. Presencia de las variables de diseño y clases del nuevo sistema visual en `base.css`.
  2. Reglas de diseño de modales modernos en `modal.css` (elevación, border-radius, backdrop).
  3. Reglas de tabla y padding de celdas en `base.css`.
  4. Ejecución completa de `make verify` (PHP lint + PHPStan nivel 9 + PHPUnit) en verde.
