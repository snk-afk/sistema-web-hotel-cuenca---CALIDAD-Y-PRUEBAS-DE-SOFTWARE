# SPEC-0031: Interfaz Completa de Gestión de Egresos y Otros Ingresos (`/egresos`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Egresos de Caja Chica, Gastos Operativos y Otros Ingresos No Operativos
- **Dependencia:** SPEC-0007 (Backend de Egresos y Otros Ingresos — Implementado)

---

## 1. Objetivo

Construir la **interfaz completa y profesional** del módulo de Egresos y Otros Ingresos (`/egresos`) para que el recepcionista y el administrador puedan:

1. **Visualizar Métricas Financieras Clave**:
   - Total Egresos Acumulados.
   - Total Otros Ingresos Acumulados.
   - Balance Neto Extra-Operativo ($\sum \text{Otros Ingresos} - \sum \text{Egresos}$).
2. **Consultar y Auditar el Historial con Tabs**:
   - Tab 1: **💸 Egresos / Gastos** (Categoría, Concepto/Justificación, Proveedor, N° Comprobante, Método de Pago, Monto).
   - Tab 2: **💰 Otros Ingresos** (Cochera/Estacionamiento, Lavandería Externa, Eventos, Penalidades/Roturas, Método de Pago, Monto).
3. **Registro Rápido con Modales**:
   - Modal para **Nuevo Egreso / Gasto**: Categoría (insumos, mantenimiento, servicios, compras), proveedor, comprobante, método y monto.
   - Modal para **Nuevo Otro Ingreso**: Categoría (cochera, lavandería, roturas, alquiler de espacios), concepto, método y monto.
4. **Validación de Caja Chica**:
   - Alerta visible si la caja chica está cerrada con enlace directo a `/caja`.

---

## 2. Alcance

### Dentro del Alcance:
- Vista `views/pages/expenses.php` rediseñada con métricas, tabs de navegación, tablas de historial y modales de registro.
- JavaScript `public/assets/js/pages/expenses.js` con consumo de `GET /api/expenses`, `POST /api/expenses`, `GET /api/other-incomes`, `POST /api/other-incomes`.
- CSS `public/assets/css/pages/expenses.css` con dark theme consistente.
- Pruebas automatizadas actualizadas.

### Fuera del Alcance:
- Facturación SUNAT electrónica (módulo 10).

---

## 3. Reglas de Negocio e Invariantes

1. **Afectación a Caja Chica:**
   - Si el método de pago es `cash`, se valida que exista turno de caja abierto y se descuenta/ingresa dinero en el balance esperado de caja.
   - Si es digital/tarjeta, no afecta el efectivo físico de caja pero sí el balance general contable.
2. **Campos Obligatorios:**
   - Egreso: Concepto y Monto > 0 obligatorios. Proveedor y Comprobante opcionales.
   - Ingreso: Concepto y Monto > 0 obligatorios.

---

## 4. Componentes UI de la Vista

### 4.1 Header
```
💸 Egresos de Caja Chica y Otros Ingresos
Control de compras menores, gastos de mantenimiento, cocheras y servicios externos.
[🔴 Registrar Egreso]  [🟢 Registrar Ingreso]
```

### 4.2 Métricas (3 tarjetas)
| Tarjeta | Icono | Descripción |
|---------|-------|-------------|
| Total Egresos | 💸 | Suma de todos los egresos registrados |
| Otros Ingresos | 💰 | Suma de todos los ingresos no operativos |
| Balance Extra-Operativo | ⚖️ | Ingresos - Egresos |

### 4.3 Tabs de Navegación y Tablas
- **Tab 1: 💸 Historial de Egresos**: ID, Fecha, Categoría, Concepto, Proveedor, N° Comprobante, Método, Monto.
- **Tab 2: 💰 Historial de Otros Ingresos**: ID, Fecha, Categoría, Concepto, Notas, Método, Monto.

---

## 5. Criterios de Aceptación (Given / When / Then)

### CA-1: Renderizado Completo de `/egresos`
- **Given:** El usuario navega a `/egresos`.
- **When:** La página carga.
- **Then:** Se muestran las 3 métricas, los tabs de Egresos / Otros Ingresos, la barra de búsqueda y las tablas correspondientes.

### CA-2: Registro Exitoso de Egreso
- **Given:** Turno de caja abierto.
- **When:** El usuario completa el formulario de egreso por S/ 25.00 ("Compra de bombillos LED") y envía.
- **Then:** Se envía a `POST /api/expenses`, la tabla de egresos se actualiza y se muestra notificación de éxito.

### CA-3: Registro Exitoso de Otro Ingreso
- **Given:** Turno de caja abierto.
- **When:** El usuario registra un ingreso de cochera por S/ 10.00 y envía.
- **Then:** Se envía a `POST /api/other-incomes`, la tabla de ingresos se actualiza y se muestra notificación de éxito.

---

## 6. Archivos a Modificar / Crear

| Archivo | Acción |
|---------|--------|
| `views/pages/expenses.php` | **Reescribir** — Vista completa con métricas, tabs, tablas y modales |
| `public/assets/js/pages/expenses.js` | **Crear** — Lógica de carga, filtrado y envío de egresos e ingresos |
| `public/assets/css/pages/expenses.css` | **Crear** — Estilos para el módulo de egresos e ingresos |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Aserciones para `/egresos` |
