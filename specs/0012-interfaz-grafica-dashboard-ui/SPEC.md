# SPEC-0012: Cliente de Interfaz Gráfica Web Completa (UI/UX HTML5, CSS3, Vanilla JS)

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Interfaz Gráfica Completa para el Sistema de Gestión Hotelera CS-Hotel (Vistas, Páginas, Componentes y Layouts)

---

## 1. Objetivo

Implementar el cliente visual gráfico completo (frontend sin frameworks, escrito en HTML5 semántico, CSS3 moderno responsivo y JavaScript vainilla interactivo) que cubra el **100% de la funcionalidad** expuesta por las APIs y servicios backend desarrollados en los SPECs 0001 al 0011.

---

## 2. Alcance

### Dentro del Alcance:
- **Estructura Modular de Vistas (`views/`):**
  - `views/layouts/`: Plantillas maestras reutilizables (`header.php`, `sidebar.php`, `footer.php`).
  - `views/components/`: Modales reutilizables (`modal_checkin.php`, `modal_checkout.php`, `modal_transfer.php`, `modal_extension.php`, `toast_notifications.php`).
  - `views/pages/`: Vistas de páginas completas por módulo:
    1. **`dashboard.php`:** Mapa interactivo de habitaciones codificado por colores con temporizador regresivo en vivo y acciones rápidas.
    2. **`rooms.php`:** Gestión visual de habitaciones, categorías y tarifas configurables.
    3. **`guests.php`:** Directorio maestro de huéspedes con consulta automática en tiempo real de DNI/RUC (JSONPE).
    4. **`reservations.php`:** Gestión de reservas futuras y conversión directa a Check-in.
    5. **`folios.php`:** Visor detallado de cuenta por habitación, historial de cargos, abonos, pagos parciales y reversos.
    6. **`store.php`:** Punto de Venta (POS) gráfico de tienda con catálogo de productos, control de stock mínimo y opción de cobro en efectivo o carga a habitación.
    7. **`cashbox.php`:** Gestión visual de turno de caja (apertura, egresos, ingresos varios, movimientos y arqueo de cierre con cálculo de diferencia).
    8. **`expenses.php`:** Registro categorizado de gastos de caja chica y otros ingresos no operativos.
    9. **`housekeeping.php`:** Tablero visual de tareas de limpieza (mucamas) y reportes de averías/mantenimiento con liberación automática de habitaciones.
    10. **`users.php`:** Administración de usuarios, roles, permisos granulares (RBAC) y visor inmutable de la bitácora de auditoría (`audit_logs`).
    11. **`invoices.php`:** Emisión de Boletas, Facturas (validando RUC 11 dígitos) y Notas de Venta con visor y descarga directa de comprobantes PDF/XML.
    12. **`reports.php`:** Cuadro de mando ejecutivo con KPIs ($\text{Ocupación \%}$, $\text{ADR}$, $\text{RevPAR}$) y botón de exportación CSV a Excel.
- **Rendimiento y Experiencia de Usuario:**
  - Estilos CSS consolidados en `public/assets/css/style.css` (diseño elegante, oscuro/claro, sombras suaves, Flexbox/Grid).
  - Comportamiento dinámico en `public/assets/js/app.js` (comunicación asíncrona mediante `fetch()`, manejo de estados, temporizadores y actualización en vivo sin recargar la página).

---

## 3. Arquitectura de Directorios (`views/` y `public/assets/`)

```text
public/
└── assets/
    ├── css/
    │   └── style.css          # Estilos CSS3 completos (Variables CSS, Grid, Modales, Colores)
    └── js/
        └── app.js             # Lógica cliente JS (AJAX Fetch, Modales, Timer en tiempo real)
views/
├── layouts/
│   ├── header.php             # Cabecera con datos del hotel, turno de caja y usuario activo
│   ├── sidebar.php            # Menú lateral con enlaces a las 12 páginas principales
│   └── footer.php             # Inclusión de scripts JS y cierre de estructura HTML
├── components/
│   ├── modal_checkin.php      # Formulario modal de Check-in y búsqueda de DNI/RUC
│   ├── modal_checkout.php     # Modal de cobro final y facturación
│   ├── modal_transfer.php     # Modal de cambio de habitación
│   ├── modal_extension.php    # Modal de adición de horas
│   └── toast.php              # Notificaciones flotantes de éxito/error
└── pages/
    ├── dashboard.php          # Mapa visual de habitaciones por colores
    ├── rooms.php              # Gestión de habitaciones y tarifas
    ├── guests.php             # Directorio de huéspedes y consulta RENIEC/SUNAT
    ├── reservations.php       # Reservas y Check-in programado
    ├── folios.php             # Estado de cuenta por habitación
    ├── store.php              # POS de Tienda visual
    ├── cashbox.php            # Apertura/Cierre y movimientos de caja
    ├── expenses.php           # Egresos y otros ingresos
    ├── housekeeping.php       # Limpieza y mantenimientos
    ├── users.php              # Usuarios, RBAC y auditoría
    ├── invoices.php           # Facturación electrónica y PDFs
    └── reports.php            # Métricas RevPAR/ADR y exportación CSV
```

---

## 4. Endpoints de Navegación HTTP (Rutas de Vistas)

- `GET /` $\rightarrow$ Mapa de habitaciones (Dashboard)
- `GET /habitaciones` $\rightarrow$ Administración de habitaciones y tarifas
- `GET /huespedes` $\rightarrow$ Maestro de huéspedes y consulta DNI/RUC
- `GET /reservas` $\rightarrow$ Lista de reservas
- `GET /folios` $\rightarrow$ Estado de cuenta de estancias
- `GET /tienda` $\rightarrow$ Punto de Venta POS
- `GET /caja` $\rightarrow$ Apertura, movimientos y cierre de caja
- `GET /egresos` $\rightarrow$ Egresos de caja chica y otros ingresos
- `GET /limpieza` $\rightarrow$ Tareas de mucamas y mantenimientos
- `GET /usuarios` $\rightarrow$ Usuarios, roles, permisos y auditoría
- `GET /comprobantes` $\rightarrow$ Emisión y lista de comprobantes
- `GET /reportes` $\rightarrow$ Métricas ejecutivas y exportación CSV

---

## 5. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Navegación Visual Fluida y Cobertura Completa
- **Given:** Un usuario navega a cualquiera de las 12 rutas web (`/`, `/habitaciones`, `/tienda`, `/caja`, etc.).
- **When:** Se renderiza la página correspondiente utilizando el layout unificado (`header.php`, `sidebar.php`, `footer.php`).
- **Then:** Visualiza la interfaz completa responsiva, con el menú lateral resaltado en la opción activa y la capacidad de interactuar visualmente con toda la funcionalidad del sistema.

### Criterio 2: Operaciones Interactivas sin Recargas Frecuentes
- **Given:** El recepcionista está en el Mapa de Habitaciones (`dashboard.php`).
- **When:** Realiza un Check-in en una habitación verde o un Checkout en una roja.
- **Then:** El cliente JS envía la petición vía `fetch()`, muestra la notificación flotante (toast) y actualiza el color y estado de la tarjeta en pantalla inmediatamente.

---

## 6. Transacciones y Seguridad de Vistas

- Escape automático XSS con `htmlspecialchars($val, ENT_QUOTES | ENT_HTML5, 'UTF-8')`.
- Sanitización estricta de todos los parámetros recibidos.
