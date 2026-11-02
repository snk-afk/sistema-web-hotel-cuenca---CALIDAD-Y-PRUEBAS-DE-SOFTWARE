# SPEC-0011: Reportes Financieros, Métricas de Ocupación (RevPAR, ADR) y Exportación CSV

- **Fecha:** 2026-08-21
- **Estado:** En Revisión (Esperando Aprobación del Usuario)
- **Módulo:** Reportes Ejecutivos, Métricas de Desempeño Hotelero (RevPAR, ADR, Ocupación) y Exportación de Datos CSV/Excel

---

## 1. Objetivo

Establecer la estructura de servicios y controladores para el cálculo dinámico de reportes financieros consolidados (ingresos por hospedaje, tienda, otros ingresos y egresos de caja chica), métricas avanzadas de gestión hotelera ($\text{Ocupación \%}$, $\text{ADR}$, $\text{RevPAR}$) y utilidades de exportación estructurada en formato CSV/Excel.

---

## 2. Alcance

### Dentro del Alcance:
- Reporte Ejecutivo de Ocupación:
  - Porcentaje de Ocupación: $\text{Ocupación \%} = \left(\frac{\text{Habitaciones Ocupadas}}{\text{Total Habitaciones}}\right) \times 100$
  - Tarifa Promedio Diaria ($\text{ADR}$): $\text{ADR} = \frac{\text{Ingresos por Alojamiento}}{\text{Habitaciones Vendidas}}$
  - Ingreso por Habitación Disponible ($\text{RevPAR}$): $\text{RevPAR} = \frac{\text{Ingresos por Alojamiento}}{\text{Total Habitaciones Disponibles}}$
- Resumen Financiero por Rango de Fechas:
  - Ingresos por Hospedaje (Pagos de Folios).
  - Ingresos por Tienda POS.
  - Otros Ingresos No Operativos.
  - Egresos de Caja Chica.
  - Balance Neto de Utilidad Operativa.
- Exportador de Reportes (`Support\CsvExporter`): Generación limpia de archivos `.csv` compatibles con Microsoft Excel.

### Fuera del Alcance:
- Gráficos en tiempo real por WebSocket (Módulo frontend).

---

## 3. Reglas de Negocio

1. **Precisión Matemática y División por Cero:**
   - Si no existen habitaciones registradas o no hay ventas en el periodo, las métricas ($\text{ADR}$, $\text{RevPAR}$, $\text{Ocupación \%}$) devuelven `0.00` sin lanzar advertencias de PHP.

2. **Consolidación Operativa Completa:**
   - El resultado neto deduce estrictamente todos los egresos registrados de los ingresos acumulados.

3. **Formatos de Exportación:**
   - La exportación CSV incluye cabeceras con codificación UTF-8 con BOM (`\xEF\xBB\xBF`) para que Excel interprete tildes y caracteres especiales correctamente.

---

## 4. Modelo de Datos (SQL DDL)

Este módulo **no** requiere nuevas tablas en la base de datos. Utiliza consultas de agregación SQL (`COUNT`, `SUM`, `AVG`) sobre las tablas existentes (`rooms`, `stays`, `folio_payments`, `store_sales`, `expenses`, `other_incomes`).

---

## 5. Endpoints / Métodos de Servicios

### Rutas HTTP
- `GET /api/reports/occupancy` — Reporte de ocupación y métricas de desempeño ($\text{Ocupación \%}$, $\text{ADR}$, $\text{RevPAR}$).
- `GET /api/reports/financial` — Resumen financiero de ingresos, egresos y utilidad neta.
- `GET /api/reports/export/csv` — Descarga de reporte financiero en formato `.csv` para Excel.

---

## 6. Criterios de Aceptación (Given / When / Then)

### Criterio 1: Cálculo de Métricas de Desempeño Hotelero
- **Given:** Un hotel de 10 habitaciones con 4 ocupadas y un ingreso por hospedaje en el periodo de `S/ 200.00`.
- **When:** Se consulta `GET /api/reports/occupancy`.
- **Then:** El sistema calcula:
  - $\text{Ocupación \%} = 40.0\%$
  - $\text{ADR} = \frac{200}{4} = \text{S/ } 50.00$
  - $\text{RevPAR} = \frac{200}{10} = \text{S/ } 20.00$

### Criterio 2: Exportación de Archivo CSV Compatible con Excel
- **Given:** Solicitud a `GET /api/reports/export/csv`.
- **When:** El servidor procesa los datos del periodo.
- **Then:** Retorna `Content-Type: text/csv` con `Content-Disposition: attachment; filename="reporte_financiero.csv"` e imprime los datos tabulados con UTF-8 BOM.

---

## 7. Casos Límite y Errores

1. **Rango de Fechas Inválido (`from_date > to_date`):**
   - Retorna `HTTP 422 Unprocessable Entity`.
2. **Hotel Vacío (0 habitaciones activas):**
   - Retorna respuesta con métricas calculadas en `0.00`.

---

## 8. Transacciones PDO

- Al tratarse de consultas de consolidación de lectura (`SELECT`), no se requiere abrir transacciones destructivas.
