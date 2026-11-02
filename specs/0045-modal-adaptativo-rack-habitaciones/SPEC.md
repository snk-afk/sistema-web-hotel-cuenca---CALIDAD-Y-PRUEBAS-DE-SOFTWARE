# SPEC-0045: Modal Adaptativo Panorámico para Gestión y Operaciones de Habitación (Reemplazo del Drawer)

## 1. Objetivo
Transformar el panel lateral estrecho (Drawer) en un **Modal Adaptativo Panorámico de Alta Gama** (`.ns-room-modal`), diseñado para aprovechar al máximo pantallas grandes mediante un layout estructurado en 2 columnas (panel de resumen/estado en vivo + panel de operaciones/formularios), mientras que en pantallas medianas y móviles se adapta fluidamente a 1 columna vertical con pestañas ergonómicas y scrolling suave.

---

## 2. Contexto y Alcance

### Entra en el alcance:
1. **Nuevo Componente Modal Adaptativo (`views/components/room_modal.php`):**
   - Reemplaza `views/components/room_drawer.php`.
   - Layout de 2 columnas en pantallas de escritorio (>= 1024px):
     - **Columna Izquierda (380px):** Información de la habitación, estado en vivo, chip de categoría, badge, cronómetro en vivo y balance de cuenta / folio.
     - **Columna Derecha (flexible):** Pestañas de operación (Check-In Express, Reserva Futura, Checkout SUNAT, Tienda POS, Gestión/Transferencia/Limpieza).
   - Layout responsivo en móvil (< 1024px): 1 sola columna con tabs superiores y altura adaptable (max-height 92vh con scroll interno).
2. **Estilos Exclusivos y Namespace BEM (`public/assets/css/components/room-modal.css`):**
   - Backdrop blur profundo (`backdrop-filter: blur(12px)` con `rgba(5, 8, 15, 0.85)`).
   - Animación fluida de escala y fade (`scale(0.96) → scale(1)`).
   - Sistema de clases `.ns-room-modal__*` sin interferir con modales genéricos ni dependencias externas.
   - Max-width de hasta `1100px` en pantallas grandes para máxima visibilidad sin descuadres.
3. **Integración con JavaScript (`public/assets/js/components/drawer.js` / `room_modal.js`):**
   - Adaptación de las funciones de apertura/cierre (`openRoomDrawer` o `openRoomModal`) manteniendo retrocompatibilidad total con las tarjetas del Dashboard (`dashboard.php`).
   - Mantenimiento intacto de la lógica de negocio (Check-in, Checkout, liquidaciones SUNAT, reservas, extensiones y cobro de tienda POS).
4. **Actualización de Vistas:**
   - En `views/pages/dashboard.php`: Inclusión de `views/components/room_modal.php`.
   - En `views/layouts/header.php`: Carga de `/assets/css/components/room-modal.css`.

### Queda fuera del alcance:
- Alteración de endpoints API REST backend ni migraciones de base de datos.

---

## 3. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Modal Panorámico Adaptativo en Pantallas Grandes (>= 1024px)
- **Given** que el usuario hace clic en una tarjeta de habitación desde un monitor o PC,
- **When** se abre el modal de habitación,
- **Then** se despliega una ventana centrada amplia (hasta 1100px) dividida en 2 paneles armónicos: resumen y estado a la izquierda, y formulario de operación activa a la derecha, sin scrolls innecesarios ni descuadres.

### Criterio 2: Adaptabilidad en Móviles y Tablets (< 1024px)
- **Given** un dispositivo móvil o pantalla compacta,
- **When** se abre el modal de habitación,
- **Then** el modal se reacomoda verticalmente ocupando hasta el 94vw / 92vh con pestañas superiores táctiles y scroll suave.

### Criterio 3: Operatividad completa y `make verify`
- **Given** las acciones operativas del hotel (Check-In inmediato, agendamiento de reservas, liquidación de checkout con comprobante, transferencias y consumos POS),
- **When** se ejecutan desde el nuevo modal adaptativo,
- **Then** todas las funciones responden al 100% y la suite de pruebas (`make verify`) pasa con 0 errores.

---

## 4. Pruebas y Verificación
- Pruebas en `tests/Feature/RoomModalAdaptiveTest.php` validando:
  1. Presencia del contenedor `.ns-room-modal` y su estructura multi-columna en CSS.
  2. Inclusión en `views/pages/dashboard.php` y `views/layouts/header.php`.
  3. Ejecución de `make verify` en verde.
