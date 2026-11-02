# SPEC-0035: Interfaz Completa de Reportes Ejecutivos, KPIs y Analítica Hotelera (`/reportes`)

- **Fecha:** 2026-08-22
- **Estado:** Borrador (Esperando Aprobación del Usuario)
- **Módulo:** Cuadro de Mando, KPIs Hoteleros (ADR, RevPAR, Ocupación), Consolidado Financiero y Exportación
- **Dependencia:** SPEC-0011 (Backend de Reportes y KPIs — Implementado)

---

## 1. Objetivo

Construir la **interfaz completa, interactiva y profesional** del módulo de Reportes y KPIs Hoteleros (`/reportes`) para que el administrador y la gerencia puedan:

1. **Monitorear los KPIs Hoteleros Fundamentales**:
   - **Tasa de Ocupación (%)**: Porcentaje de habitaciones ocupadas vs total inventario.
   - **ADR (Average Daily Rate)**: Tarifa promedio diaria cobrada por habitación ocupada.
   - **RevPAR (Revenue Per Available Room)**: Ingreso promedio por habitación disponible del hotel.
   - **Utilidad Operativa Neta**: Ingresos Totales (Alojamiento + Tienda + Otros Ingresos) menos Egresos.
2. **Filtrar por Rango de Fechas Reactivo**:
   - Selector `Desde` y `Hasta` con botones rápidos: `Hoy`, `Esta Semana`, `Este Mes`, `Todo el Histórico`.
   - Actualización dinámica de métricas vía fetch sin recargar la página (`GET /api/reports/occupancy`, `GET /api/reports/financial`).
3. **Consolidado Financiero y Estructura de Ingresos**:
   - Desglose por centro de ingresos: Alojamiento / Estancias, Tienda POS / Minibar, Otros Ingresos (Cocheras, Lavandería, Eventos).
   - Total Egresos y Margen de Utilidad en tabla con diseño dark theme y barras visuales de distribución.
4. **Exportación de Reportes**:
   - Botón directo para **Descargar Excel (CSV)** (`/api/reports/export/csv`).
   - Botón de **Impresión / Reporte PDF Ejecutivo** (`window.print()`).

---

## 2. Alcance

### Dentro del Alcance:
- Vista `views/pages/reports.php` rediseñada con KPIs ejecutivos, filtros de fecha, barras de progreso y consolidado financiero.
- JavaScript `public/assets/js/pages/reports.js` con consumo dinámico de `/api/reports/occupancy` y `/api/reports/financial`.
- CSS `public/assets/css/pages/reports.css` con estilos dark theme.
- Pruebas automatizadas actualizadas.

---

## 3. Reglas de Negocio e Invariantes

1. **Fórmulas Hoteleras Estándar:**
   - $\text{Ocupación (\%)} = (\text{Habitaciones Ocupadas} / \text{Habitaciones Totales}) \times 100$
   - $\text{ADR} = \text{Ingreso Hospedaje} / \text{Habitaciones Ocupadas}$
   - $\text{RevPAR} = \text{Ingreso Hospedaje} / \text{Habitaciones Totales}$
   - $\text{Utilidad Neta} = \text{Ingreso Hospedaje} + \text{Ingreso Tienda} + \text{Otros Ingresos} - \text{Total Egresos}$

---

## 4. Componentes UI de la Vista

### 4.1 Header y Barra de Filtros
```
📈 Cuadro de Mando y Reportes Ejecutivos
Métricas clave de rendimiento hotelero (KPIs), consolidado financiero y exportación contable.
[📥 Exportar CSV (Excel)]  [🖨️ Imprimir Reporte]

[Hoy] [Esta Semana] [Este Mes] [Todo] | Desde: [YYYY-MM-DD] Hasta: [YYYY-MM-DD] [🔍 Filtrar]
```

### 4.2 Métricas Principales (4 tarjetas)
| Tarjeta | Icono | KPI |
|---------|-------|-----|
| % Ocupación | 🏨 | % de ocupación con barra de progreso |
| ADR | 🏷️ | Tarifa Promedio Diaria |
| RevPAR | 📊 | Ingreso por Habitación Disponible |
| Utilidad Neta | 💰 | Margen neto final con color dinámico |

### 4.3 Tablas de Rendimiento y Desglose Financiero
- Tabla de fuentes de ingreso: Alojamiento, Tienda POS, Otros Ingresos.
- Desglose de egresos operativos.
- Utilidad neta y margen operativo.

---

## 5. Criterios de Aceptación (Given / When / Then)

### CA-1: Renderizado Completo de `/reportes`
- **Given:** El usuario navega a `/reportes`.
- **When:** La página carga.
- **Then:** Se muestran las 4 métricas de KPIs, la barra de filtros por fecha y la tabla del consolidado financiero.

### CA-2: Filtrado por Rango de Fechas
- **Given:** El usuario selecciona un rango de fechas o pulsa `Este Mes`.
- **When:** Hace clic en filtrar.
- **Then:** Se consultan los endpoints de la API con los parámetros `from` y `to` y las métricas se recalculan reactivamente.

### CA-3: Exportación en Excel (CSV)
- **Given:** El usuario hace clic en `📥 Exportar CSV`.
- **When:** Se ejecuta la petición.
- **Then:** Se descarga el archivo `reporte_financiero.csv` con los datos consolidados.

---

## 6. Archivos a Modificar / Crear

| Archivo | Acción |
|---------|--------|
| `views/pages/reports.php` | **Reescribir** — Vista completa con KPIs, filtros interactivos y consolidado |
| `public/assets/js/pages/reports.js` | **Crear** — Lógica de filtros, recálculo y actualización de KPIs |
| `public/assets/css/pages/reports.css` | **Crear** — Estilos para el cuadro de mando y reportes |
| `tests/Feature/Controllers/ViewControllerTest.php` | **Modificar** — Aserciones para `/reportes` |
