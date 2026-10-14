<?php
declare(strict_types=1);

$page_title = 'Reportes y Métricas';
$page_styles = ['/assets/css/pages/reports.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>📈 Cuadro de Mando y Reportes Ejecutivos</h2>
        <p class="text-muted">Reportes Ejecutivos: indicadores clave de rendimiento hotelero (KPIs), ingresos, egresos y utilidad neta.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="/api/reports/export/pdf" id="btn-export-pdf" target="_blank" class="btn btn-danger">📄 Descargar Informe PDF</a>
        <a href="/api/reports/export/csv" id="btn-export-csv" class="btn btn-success">📥 Exportar CSV (Excel)</a>
    </div>
</div>

<!-- Filter Bar -->
<div class="reports-filter-container">
    <div class="quick-filter-group">
        <button class="quick-filter-btn active" data-range="all" onclick="applyQuickReportFilter('all', this)">Todo el Histórico</button>
        <button class="quick-filter-btn" data-range="month" onclick="applyQuickReportFilter('month', this)">Este Mes</button>
        <button class="quick-filter-btn" data-range="week" onclick="applyQuickReportFilter('week', this)">Esta Semana</button>
        <button class="quick-filter-btn" data-range="today" onclick="applyQuickReportFilter('today', this)">Hoy</button>
    </div>

    <form id="form-report-date-filter" class="date-range-group" onsubmit="handleCustomDateReportFilter(event)">
        <span class="text-muted" style="font-size: 0.82rem;">Desde:</span>
        <input type="date" id="report-filter-from">
        <span class="text-muted" style="font-size: 0.82rem;">Hasta:</span>
        <input type="date" id="report-filter-to">
        <button type="submit" class="btn btn-sm btn-primary">🔍 Filtrar</button>
    </form>
</div>

<!-- 4 Executive KPIs Grid -->
<div class="reports-metrics-grid mb-3">
    <div class="reports-metric-card">
        <div class="reports-metric-header">
            <span class="reports-metric-title">Tasa de Ocupación</span>
            <span class="reports-metric-icon">🏨</span>
        </div>
        <div class="reports-metric-value" id="kpi-occupancy-rate">0.0 %</div>
        <span class="reports-metric-subtitle" id="kpi-occupancy-rooms-count">0 de 0 habitaciones ocupadas</span>
        <div class="occupancy-progress-bar">
            <div class="occupancy-progress-fill" id="kpi-occupancy-fill" style="width: 0%;"></div>
        </div>
    </div>

    <div class="reports-metric-card">
        <div class="reports-metric-header">
            <span class="reports-metric-title">ADR (Tarifa Promedio Diaria)</span>
            <span class="reports-metric-icon">🏷️</span>
        </div>
        <div class="reports-metric-value text-info" id="kpi-adr-value">S/ 0.00</div>
        <span class="reports-metric-subtitle">Ingreso promedio por hab. ocupada</span>
    </div>

    <div class="reports-metric-card">
        <div class="reports-metric-header">
            <span class="reports-metric-title">RevPAR (Ingreso por Hab. Disp.)</span>
            <span class="reports-metric-icon">📊</span>
        </div>
        <div class="reports-metric-value text-primary" id="kpi-revpar-value">S/ 0.00</div>
        <span class="reports-metric-subtitle">Ingreso total entre inventario de habs</span>
    </div>

    <div class="reports-metric-card">
        <div class="reports-metric-header">
            <span class="reports-metric-title">Utilidad Operativa Neta</span>
            <span class="reports-metric-icon">💰</span>
        </div>
        <div class="reports-metric-value text-success" id="kpi-net-profit">S/ 0.00</div>
        <span class="reports-metric-subtitle">Total Ingresos − Total Egresos</span>
    </div>
</div>

<!-- Financial Summary Table Card -->
<div class="card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 style="color: #ffffff; margin: 0;">📊 Estado de Rendimiento Financiero Consolidado</h3>
        <span class="badge badge-info" id="report-period-label">Período: Histórico Completo</span>
    </div>

    <div class="table-responsive">
        <table class="financial-summary-table">
            <thead>
                <tr>
                    <th>Centro de Ingreso / Concepto</th>
                    <th style="text-align: right;">Monto Consolidado (S/)</th>
                    <th style="text-align: right; width: 120px;">Participación</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>🛏️ Ingresos por Hospedaje / Alojamiento</td>
                    <td style="text-align: right;"><strong id="fin-acc-income">S/ 0.00</strong></td>
                    <td style="text-align: right;" class="text-muted" id="fin-acc-share">0.0%</td>
                </tr>
                <tr>
                    <td>🛒 Ingresos por Ventas POS Tienda / Minibar</td>
                    <td style="text-align: right;"><strong id="fin-store-income">S/ 0.00</strong></td>
                    <td style="text-align: right;" class="text-muted" id="fin-store-share">0.0%</td>
                </tr>
                <tr>
                    <td>🚗 Otros Ingresos No Operativos (Cochera, Lavandería, Eventos)</td>
                    <td style="text-align: right;"><strong id="fin-other-income">S/ 0.00</strong></td>
                    <td style="text-align: right;" class="text-muted" id="fin-other-share">0.0%</td>
                </tr>
                <tr class="total-income-row">
                    <td><strong>TOTAL INGRESOS OPERATIVOS BRUTOS</strong></td>
                    <td style="text-align: right;"><strong class="text-success" id="fin-total-income">S/ 0.00</strong></td>
                    <td style="text-align: right;"><span class="badge badge-success">100.0%</span></td>
                </tr>
                <tr>
                    <td>💸 Egresos, Gastos de Caja Chica y Mantenimiento Menor</td>
                    <td style="text-align: right;"><strong class="text-danger" id="fin-total-expenses">- S/ 0.00</strong></td>
                    <td style="text-align: right;" class="text-muted" id="fin-expenses-share">--</td>
                </tr>
                <tr class="net-profit-row">
                    <td><strong>UTILIDAD OPERATIVA NETA FINAL</strong></td>
                    <td style="text-align: right;"><strong id="fin-net-profit" class="text-success">S/ 0.00</strong></td>
                    <td style="text-align: right;"><span class="badge badge-primary" id="fin-profit-margin">Margen: 0%</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script src="/assets/js/pages/reports.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
