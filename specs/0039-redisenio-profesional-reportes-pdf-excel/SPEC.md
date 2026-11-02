# SPEC-0039: Rediseño Profesional y Corporativo de Reportes Financieros y KPIs (PDF & Excel/CSV)

## 1. Objetivo
Transformar radicalmente la generación de informes ejecutivos en PDF y la exportación de reportes a Excel/CSV desde un formato de texto plano/monotono a un diseño corporativo hotelero premium, con estructura visual moderna, tarjetas de KPIs, tablas estilizadas, colores institucionales y metadatos completos.

## 2. Contexto y Alcance
- **Módulos Afectados:**
  - `src/Support/PdfGenerator.php` (`generateExecutiveReportPdf`)
  - `src/Services/ReportService.php` (`exportFinancialCsv`)
  - `src/Support/CsvExporter.php`
  - `tests/Unit/Support/PdfGeneratorTest.php`
  - `tests/Unit/Services/ReportServiceTest.php`
- **Alcance Funcional:**
  1. **Rediseño del PDF Ejecutivo (`informe_ejecutivo_hotel.pdf`):**
     - Reemplazo de tipografía `Courier` plana por `Helvetica` / `Helvetica-Bold` con codificación `WinAnsiEncoding`.
     - Cabecera corporativa `#1C3360` con datos fiscales del hotel y recuadro de período evaluado.
     - Sección de **KPIs Operativos** visuales en cuadrícula (Ocupación, ADR, RevPAR, Margen Neto).
     - Tabla formal de **Estado de Resultados Operativos** con bandas alternadas, líneas de división suaves, columnas alineadas y totalizador destacado.
     - Pie de página institucional con numeración y leyenda de control administrativo.
  2. **Estructuración del Reporte Excel / CSV (`reporte_financiero.csv`):**
     - Encabezado con metadatos del hotel, RUC y período de corte.
     - Tablas estructuradas separadas por categorías: Indicadores Hoteleros, Ingresos por Unidad de Negocio, Egresos y Resultado Neto.
     - Compatibilidad con UTF-8 con BOM para apertura perfecta en Excel.

## 3. Criterios de Aceptación

### Escenario 1: Generación de PDF Ejecutivo de alta calidad
- **Given** métricas de ocupación e ingresos financieros calculados.
- **When** se invoca `PdfGenerator::generateExecutiveReportPdf($data)`.
- **Then** el PDF resultante usa `Helvetica` / `Helvetica-Bold`, incluye elementos vectoriales (rectángulos de color, líneas, cajas de métricas) y renderiza correctamente caracteres en español (tildes, eñes).

### Escenario 2: Exportación de CSV estructurado y enriquecido
- **Given** un rango de fechas seleccionado en reportes.
- **When** se solicita la exportación CSV (`ReportService::exportFinancialCsv()`).
- **Then** el CSV generado contiene cabeceras descriptivas, desglose por conceptos y totales numéricos formateados con precisión de 2 decimales.

## 4. Calidad y Verificación
- `make verify` 100% en verde (PHPStan nivel 9, PHPUnit completo).
