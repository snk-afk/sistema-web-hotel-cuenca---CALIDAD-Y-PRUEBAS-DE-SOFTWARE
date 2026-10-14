# SPEC-0026: Interfaz de Estados de Cuenta / Folios (`/folios`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Interfaz Frontend de Folios, Cargos, Abonos y Saldos
- **Dependencia:** SPEC-0004 (Backend de Folios — Implementado)

---

## 1. Objetivo

Construir la **interfaz completa** del módulo de Estados de Cuenta / Folios (`/folios`) para que el recepcionista y el administrador puedan:

1. **Consultar todos los folios** del hotel: activos (habitaciones ocupadas) y cerrados (estancias finalizadas).
2. **Ver el detalle completo** de cada folio: cargos (+alojamiento, +extensiones, +consumos, +servicios), abonos (-adelantos, -pagos parciales, -pago final), reversos y el **saldo pendiente en tiempo real**.
3. **Buscar y filtrar** folios por estado, habitación, huésped o rango de fechas.
4. **Deep-link** directo a un folio desde la URL (`/folios?stay_id={id}`).

> **Nota:** La lógica de backend (Models, Repositories, Services, Controllers, API) ya está 100% implementada en SPEC-0004. Este spec cubre únicamente la **capa de presentación** (`views/pages/folios.php`, `public/assets/js/pages/folios.js`, `public/assets/css/pages/folios.css`).

---

## 2. Alcance

### Dentro del Alcance:
- Vista `views/pages/folios.php` completa con métricas, tabla de folios y modal de detalle.
- JavaScript `public/assets/js/pages/folios.js` para carga dinámica vía API, filtrado, búsqueda y modal de detalle.
- CSS `public/assets/css/pages/folios.css` con dark theme coherente con el sistema de diseño existente.
- Actualización de `ViewController::folios()` para pasar datos necesarios al renderizar.
- Pruebas Feature para verificar que la vista renderiza correctamente.

### Fuera del Alcance:
- Modificaciones al backend de Folios (ya implementado y testeado).
- Creación manual de cargos/abonos desde esta vista (eso se hace desde el Drawer del Dashboard).

---

## 3. Reglas de Negocio e Invariantes de la Vista

1. **Carga de datos vía API existente:**
   - La vista carga los folios consultando `GET /api/rooms` que ya devuelve `current_stay.charges`, `current_stay.payments`, `current_stay.total_charges`, `current_stay.total_payments`, `current_stay.pending_balance` para cada habitación ocupada.

2. **Métricas globales calculadas en JS:**
   - Total de Folios Abiertos (habitaciones actualmente ocupadas con folio activo).
   - Total Cargos Acumulados (suma de `total_charges` de todos los folios abiertos).
   - Total Abonos Recibidos (suma de `total_payments` de todos los folios abiertos).
   - Deuda Global Pendiente (suma de `pending_balance` de todos los folios abiertos).

3. **Filtrado y búsqueda:**
   - Tabs por estado: `Todos`, `Abiertos`, `Cerrados`.
   - Buscador de texto libre: por número de habitación, nombre de huésped o ID de folio.
   - Los filtros se sincronizan con los parámetros URL (`?status=open`, `?search=201`).

4. **Modal de Detalle de Folio:**
   - Muestra la tabla desglosada de cargos (tipo, descripción, cantidad, precio unitario, total, fecha, reverso).
   - Muestra la tabla desglosada de abonos (tipo, monto, método de pago, referencia, fecha).
   - Muestra el resumen financiero: Total Cargos, Total Abonos, **Saldo Pendiente** (con color rojo si > 0, verde si = 0).

---

## 4. Componentes de la Vista

### 4.1 Header
```
📋 Estados de Cuenta / Folios
Auditoría financiera de cargos, abonos y saldos pendientes por estancia.
[📊 Ir al Mapa de Habitaciones]  (enlace a /)
```

### 4.2 Métricas (4 tarjetas)
| Tarjeta | Icono | Dato |
|---------|-------|------|
| Folios Abiertos | 📂 | Cantidad de folios con status `open` |
| Cargos Acumulados | 💳 | S/ total_charges de folios abiertos |
| Abonos Recibidos | 💰 | S/ total_payments de folios abiertos |
| Deuda Pendiente | ⚠️ | S/ pending_balance global |

### 4.3 Tabla de Folios
| Columna | Contenido |
|---------|-----------|
| Folio # | ID del folio |
| Habitación | Número de habitación + categoría |
| Huésped | Nombre del huésped titular |
| Estado | Badge `Abierto` / `Cerrado` |
| Total Cargos | S/ XX.XX |
| Total Abonos | S/ XX.XX |
| Saldo Pendiente | S/ XX.XX (rojo si > 0, verde si = 0) |
| Acciones | Botón `📋 Ver Detalle` que abre el modal |

### 4.4 Modal de Detalle del Folio
- Título: `📋 Folio #ID — Hab. {number} — {guest_name}`
- Sección 1: **Resumen financiero** (3 indicadores grandes: Cargos, Abonos, Saldo).
- Sección 2: **Tabla de Cargos** (tipo, descripción, cantidad, precio unitario, total, fecha, badge reverso si aplica).
- Sección 3: **Tabla de Abonos** (tipo, monto, método, referencia, fecha).

---

## 5. Endpoints Consumidos (ya existentes)

| Método | Ruta | Uso en la Vista |
|--------|------|-----------------|
| `GET` | `/api/rooms` | Obtener habitaciones ocupadas con sus folios, cargos y abonos |

---

## 6. Criterios de Aceptación (Given / When / Then)

### CA-1: La vista renderiza correctamente
- **Given:** El usuario navega a `/folios`.
- **When:** La página carga.
- **Then:** Se muestra el header `Estados de Cuenta / Folios`, las 4 tarjetas de métricas, la barra de búsqueda y la tabla de folios.

### CA-2: Folios activos se muestran con datos reales
- **Given:** Existe una habitación ocupada con folio abierto, cargos por S/ 50.00 y abonos por S/ 20.00.
- **When:** La vista carga los datos vía `/api/rooms`.
- **Then:** El folio aparece en la tabla con Cargos=50.00, Abonos=20.00, Saldo Pendiente=30.00 en rojo.

### CA-3: Filtrado por estado vía URL
- **Given:** El usuario navega a `/folios?status=open`.
- **When:** La página carga.
- **Then:** Solo se muestran los folios con estado `open` y el tab correspondiente está activo.

### CA-4: Búsqueda por habitación o huésped
- **Given:** Existe un folio para la habitación 201 con huésped "Carlos Pérez".
- **When:** El usuario escribe "201" en el buscador.
- **Then:** Solo se muestra el folio de la habitación 201.

### CA-5: Modal de detalle muestra desglose completo
- **Given:** Un folio con 2 cargos (alojamiento S/ 50 + consumo S/ 7) y 1 abono (adelanto S/ 20).
- **When:** El usuario hace clic en `📋 Ver Detalle`.
- **Then:** El modal muestra ambas tablas (cargos y abonos) con el resumen Cargos=57.00, Abonos=20.00, Saldo=37.00.

### CA-6: Deep-linking por stay_id
- **Given:** Un folio asociado a la estancia con ID 5.
- **When:** El usuario navega a `/folios?stay_id=5`.
- **Then:** El modal de detalle de ese folio se abre automáticamente.

---

## 7. Casos Límite

1. **Sin folios activos:** La tabla muestra un mensaje "No hay folios registrados actualmente."
2. **Folio sin cargos:** El modal de detalle muestra la tabla vacía con mensaje "Sin cargos registrados."
3. **Folio con saldo = 0:** El badge de saldo se muestra en verde con "S/ 0.00 Pagado."

---

## 8. Seguridad

- Todos los datos provienen de la API existente; no hay formularios de escritura en esta vista.
- Se usa `htmlspecialchars()` para escapar todo dato dinámico renderizado en PHP.
- Los parámetros URL se sanitizan antes de usarlos en selectores DOM.

---

## 9. Archivos a Crear/Modificar

| Archivo | Acción |
|---------|--------|
| `views/pages/folios.php` | **Reescribir** — Vista completa con métricas, tabla y modal |
| `public/assets/js/pages/folios.js` | **Crear** — Lógica de carga API, filtrado, búsqueda, modal |
| `public/assets/css/pages/folios.css` | **Crear** — Estilos dark theme coherentes |
| `src/Controllers/ViewController.php` | **Modificar** — Método `folios()` para pasar datos adicionales |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Test para verificar renderizado de `/folios` |
