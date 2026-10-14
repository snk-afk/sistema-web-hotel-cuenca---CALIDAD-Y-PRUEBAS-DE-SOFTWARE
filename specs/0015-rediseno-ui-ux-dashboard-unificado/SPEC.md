# SPEC-0015: Rediseño Profesional UI/UX 2.0 — Panel Unificado SPA de Operación Hotelera

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Interfaz Gráfica de Usuario (UI/UX), Panel de Control Unificado, Drawer Flotante de Acciones Rápidas y Flujo Operativo SPA

---

## 1. Objetivo

Transformar radicalmente la interfaz gráfica del cliente web (`views/` y `public/assets/`), eliminando la navegación fragmentada entre múltiples páginas y reemplazándola por un **Panel Operativo Unificado (SPA / Dashboard de Control)** centrado en el Rack de Habitaciones, con **Drawer Lateral Deslizante / Modales Dinámicos de Acción Rápida** que permiten realizar Check-in, Checkout Express, Venta POS, Cargos a Folio, Extensión de Horas, Cambio de Habitación y Solicitud de Limpieza desde una sola pantalla sin recargas de página.

---

## 2. Alcance

### Dentro del Alcance:
1. **Rediseño Estético UI/UX Profesional (CSS3 Moderno):**
   - Paleta de colores Premium de Sistemas ERP/PMS Hoteleros (Dark Mode / Slate & Indigo/Emerald theme).
   - Tipografía limpia, badges de estado translúcidos, sombras suaves, microinteracciones y botones de acción rápida.
   - Componentes responsivos con diseño tipo Dashboard de Alta Densidad.

2. **Panel de Control Unificado (Experiencia SPA sin Fricción):**
   - **Rack Interactivo de Habitaciones:** Tarjetas dinámicas organizadas por pisos/categorías mostrando estado en tiempo real (Libre, Ocupado, Limpieza, Mantenimiento, Reservado), temporizador de estancia con barra de progreso visual y huésped alojado.
   - **Drawer Lateral Deslizante (Room Quick Drawer):** Al hacer clic en cualquier habitación, se despliega instantáneamente una barra lateral contextual con las acciones operativas inmediatas:
     - 🏨 **Check-In Express:** Formulario con autocompletado DNI/RUC.
     - 🚪 **Checkout & Facturación Express:** Vista previa del saldo del folio, cobro y emisión de Boleta/Factura SUNAT en 1 clic.
     - 🛒 **Cargar Consumo / Venta Tienda POS:** Agregar bebidas/snacks directamente a la habitación desde el drawer sin salir del mapa.
     - 🔄 **Transferir Habitación:** Cambio directo a habitación libre.
     - ⏰ **Extender Estancia:** Adición rápida de horas extra.
     - 🧹 **Solicitar / Liberar Limpieza:** Cambiar estado a limpia o averiada.
   - **Barra Superior de Accesos Rápidos:**
     - Buscador global instantáneo (por Número de Habitación, DNI, RUC o Nombre de Huésped).
     - Widget de Estado de Turno de Caja Chica en vivo (Monto actual, Botón de Apertura/Arqueo rápido).
     - Accesos a módulos avanzados (Finanzas, Reportes, Usuarios) mediante pestañas sin perder el contexto operativo.

3. **Optimización de JavaScript (`public/assets/js/app.js`):**
   - Manejo fluido del DOM mediante AJAX / Fetch API.
   - Actualización dinámica de datos y toasts de confirmación sin recargar la página.

---

## 3. Reglas de Negocio

1. **Cero Recargas Innecesarias:**
   - La recepción del hotel debe poder operar el 90% de sus tareas diarias (check-in, cargos, checkout, cobro) desde el panel unificado sin cambiar de página.
2. **Claridad Visual e Indicadores de Alerta:**
   - Habitaciones excedidas en tiempo deben parpadear sutilmente en rojo/amarillo indicando cobro de horas extra.

---

## 4. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Operación Centrada en el Rack (Drawer Único)
- **Given:** El usuario en la pantalla principal del Dashboard.
- **When:** Hace clic en cualquier tarjeta de habitación.
- **Then:** Se despliega el Drawer Lateral con toda la información del huésped, cuenta de estancia y botones de acción rápida en menos de 100ms sin recargar la página.

### Criterio 2: Carga de Consumos POS desde el Drawer
- **Given:** Una habitación en estado `occupied`.
- **When:** El recepcionista selecciona un producto de la tienda dentro del drawer y presiona "Cargar a Habitación".
- **Then:** El producto se agrega al folio de la habitación y el saldo se actualiza visualmente en tiempo real.

### Criterio 3: Verificación Completa `make verify`
- **Given:** Los nuevos componentes UI, estilos CSS3 y lógica JS.
- **When:** Se ejecuta `make verify`.
- **Then:** Lint, PHPStan Nivel 9 y suite de pruebas en verde al 100%.
