# SPEC-0030: Interfaz Completa de Gestión de Caja Chica, Turnos y Movimientos (`/caja`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Gestión de Caja Chica, Turnos de Trabajo, Arqueo y Movimientos
- **Dependencia:** SPEC-0005 (Backend de Caja Chica — Implementado)

---

## 1. Objetivo

Diseñar e implementar la **interfaz completa y profesional** del módulo de Caja Chica y Turnos (`/caja`) con un estándar visual equivalente a los módulos ya consolidados (Dashboard, Folios, Tienda), permitiendo al recepcionista y administrador:

1. **Monitorear el Turno Actual en Tiempo Real**:
   - Tarjetas de métricas del turno: Monto de Apertura, Total Ingresos (Efectivo y Digitales), Total Egresos, y **Monto Esperado en Efectivo**.
   - Tabla detallada de movimientos del turno activo (ingresos por check-in, adelantos, ventas POS, egresos menores) con badges de método de pago y tipo.
2. **Apertura y Cierre de Turno con Arqueo Inteligente**:
   - **Apertura de Turno**: Formulario con monto base inicial y notas.
   - **Cierre / Arqueo de Turno**: Modal de liquidación que muestra el esperado, permite ingresar el efectivo real contado, calcula el sobrante/faltante/cuadrado en tiempo real con alertas cromáticas, y confirma el cierre.
3. **Registro Rápido de Movimientos Extraordinarios de Caja**:
   - Modal para registrar ingreso manual (ej: aporte de sencillo) o egreso menor de caja (ej: compra de insumo urgente, taxi, delivery).

---

## 2. Alcance

### Dentro del Alcance:
- Vista `views/pages/cashbox.php` rediseñada con métricas, desglose de turno, tabla de movimientos y modales interactivos.
- JavaScript `public/assets/js/pages/cashbox.js` con:
  - Consumo reactivo de `GET /api/cashbox/current`, `POST /api/cashbox/open`, `POST /api/cashbox/close`, `POST /api/cashbox/movement`.
  - Cálculo en vivo de descuadre en el arqueo de cierre.
- CSS `public/assets/css/pages/cashbox.css` con estilos dark theme coherentes.
- Pruebas automatizadas actualizadas.

### Fuera del Alcance:
- Modificaciones al schema de base de datos (ya está completo y migrado).

---

## 3. Reglas de Negocio e Invariantes

1. **Estado del Turno:**
   - Si no hay turno abierto: la vista muestra el panel de bienvenida / apertura para ingresar el fondo fijo.
   - Si hay turno abierto: la vista muestra el dashboard del turno con métricas, tabla de movimientos y opciones de movimiento rápido y cierre.
2. **Arqueo y Descuadre:**
   - $\text{Diferencia} = \text{Efectivo Declarado} - \text{Efectivo Esperado}$.
   - $\text{Diferencia} = 0 \rightarrow$ ✅ Cuadrado (`balanced`).
   - $\text{Diferencia} < 0 \rightarrow$ ❌ Faltante (`shortage`).
   - $\text{Diferencia} > 0 \rightarrow$ ⚠️ Sobrante (`surplus`).
3. **Inmutabilidad:**
   - Una vez cerrado el turno, queda sellado con su balance auditado.

---

## 4. Componentes UI de la Vista

### 4.1 Header
```
💵 Gestión de Caja Chica y Turnos de Trabajo
Control de flujo de efectivo, arqueo diario y trazabilidad de ingresos/egresos.
[➕ Registrar Movimiento]  [🔒 Cerrar Turno / Arqueo]
```

### 4.2 Métricas del Turno (4 tarjetas)
| Tarjeta | Icono | Descripción |
|---------|-------|-------------|
| Fondo Inicial | 🔓 | S/ initial_cash al abrir turno |
| Ingresos del Turno | 💰 | Total cobrado en efectivo/digitales |
| Egresos del Turno | 💸 | Total salidas de caja chica |
| Efectivo en Gaveta | 💵 | S/ expected_cash actual en físico |

### 4.3 Tabla de Movimientos del Turno
| Columna | Contenido |
|---------|-----------|
| # | ID del movimiento |
| Hora | Timestamp |
| Tipo | Badge `Ingreso (+)` o `Egreso (-)` |
| Categoría / Concepto | Ej: "Hospedaje Hab 201", "Venta Tienda POS", "Insumos" |
| Método | Badge `Efectivo`, `Yape`, `Tarjeta`, etc. |
| Monto | `+S/ XX.XX` (verde) o `-S/ XX.XX` (rojo) |

### 4.4 Modales
1. **Modal: Abrir Turno**: Monto inicial y notas de apertura.
2. **Modal: Cerrar Turno / Arqueo**: Efectivo esperado, input de efectivo real contado, cálculo dinámico de diferencia con semáforo, notas de cierre.
3. **Modal: Nuevo Movimiento de Caja**: Selector tipo (Ingreso/Egreso), concepto, monto, método.

---

## 5. Criterios de Aceptación (Given / When / Then)

### CA-1: Renderizado con Turno Abierto
- **Given:** Existe un turno de caja abierto con S/ 100.00 inicial.
- **When:** El usuario navega a `/caja`.
- **Then:** Se muestran las 4 métricas, el panel de control del turno, la tabla de movimientos y los botones de movimiento/cierre.

### CA-2: Renderizado sin Turno Abierto
- **Given:** No hay turno abierto para el usuario.
- **When:** El usuario navega a `/caja`.
- **Then:** Se muestra el panel de bienvenida con el formulario para abrir turno con monto inicial.

### CA-3: Registro de Movimiento Rápido de Caja
- **Given:** Turno abierto.
- **When:** El usuario registra un egreso de S/ 15.00 por "Compra de bolsas de aseo".
- **Then:** Se envía a `POST /api/cashbox/movement`, la tabla de movimientos se actualiza y el efectivo esperado se recalcula a S/ 85.00.

### CA-4: Cierre de Turno y Arqueo Auditado
- **Given:** Turno abierto con esperado de S/ 100.00.
- **When:** El usuario abre el modal de cierre, declara S/ 100.00 y confirma.
- **Then:** Se envía a `POST /api/cashbox/close`, se muestra mensaje de cierre exitoso ("Cuadrado"), y la vista pasa a estado sin turno.

---

## 6. Archivos a Modificar / Crear

| Archivo | Acción |
|---------|--------|
| `views/pages/cashbox.php` | **Reescribir** — Vista completa de caja chica con métricas, tabla y modales |
| `public/assets/js/pages/cashbox.js` | **Crear** — Lógica de turno, movimientos, arqueo en vivo y cierre |
| `public/assets/css/pages/cashbox.css` | **Crear** — Estilos para el dashboard de caja y arqueo |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Aserciones completas para `/caja` |
