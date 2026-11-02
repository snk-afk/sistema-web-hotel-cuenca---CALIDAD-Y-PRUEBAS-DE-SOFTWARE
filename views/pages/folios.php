<?php
declare(strict_types=1);

$page_title = 'Estados de Cuenta / Folios';
$page_styles = ['/assets/css/pages/folios.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>📋 Estados de Cuenta / Folios</h2>
        <p class="text-muted">Auditoría financiera de cargos, abonos y saldos pendientes por estancia.</p>
    </div>
    <a href="/" class="btn btn-primary">📊 Ir al Mapa de Habitaciones</a>
</div>

<!-- Metrics Cards -->
<div class="folio-metrics-grid mb-3">
    <div class="folio-metric-card">
        <div class="metric-icon bg-primary-soft">📂</div>
        <div class="metric-info">
            <span class="metric-title">Folios Abiertos</span>
            <span class="metric-value" id="metric-folios-open">0</span>
        </div>
    </div>

    <div class="folio-metric-card">
        <div class="metric-icon bg-warning-soft">💳</div>
        <div class="metric-info">
            <span class="metric-title">Cargos Acumulados</span>
            <span class="metric-value" id="metric-total-charges">S/ 0.00</span>
        </div>
    </div>

    <div class="folio-metric-card">
        <div class="metric-icon bg-success-soft">💰</div>
        <div class="metric-info">
            <span class="metric-title">Abonos Recibidos</span>
            <span class="metric-value" id="metric-total-payments">S/ 0.00</span>
        </div>
    </div>

    <div class="folio-metric-card">
        <div class="metric-icon bg-danger-soft">⚠️</div>
        <div class="metric-info">
            <span class="metric-title">Deuda Pendiente</span>
            <span class="metric-value" id="metric-pending-balance">S/ 0.00</span>
        </div>
    </div>
</div>

<!-- Main Card -->
<div class="card">
    <!-- Status Filter Tabs -->
    <div class="folios-tabs-bar">
        <button class="tab-btn active" data-status="all" onclick="filterFoliosByTab('all', this)">
            Todos <span class="tab-counter" id="tab-count-all">0</span>
        </button>
        <button class="tab-btn" data-status="open" onclick="filterFoliosByTab('open', this)">
            📂 Abiertos <span class="tab-counter" id="tab-count-open">0</span>
        </button>
        <button class="tab-btn" data-status="closed" onclick="filterFoliosByTab('closed', this)">
            🔒 Cerrados <span class="tab-counter" id="tab-count-closed">0</span>
        </button>
    </div>

    <!-- Search Bar -->
    <div class="reservations-filter-bar mb-3">
        <div class="search-box flex-1">
            <span class="search-icon">🔍</span>
            <input type="text" id="filter-folios-input" class="search-input" placeholder="Buscar por habitación, huésped, ID de folio..." onkeyup="filterFoliosTable()">
        </div>
    </div>

    <!-- Folios Table -->
    <div class="table-responsive">
        <table class="table" id="table-folios">
            <thead>
                <tr>
                    <th>Folio #</th>
                    <th>Habitación</th>
                    <th>Huésped</th>
                    <th>Estado</th>
                    <th>Total Cargos</th>
                    <th>Total Abonos</th>
                    <th>Saldo Pendiente</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="table-folios-body">
                <tr class="empty-row">
                    <td colspan="8" class="text-center text-muted py-3">Cargando folios...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: DETALLE DEL FOLIO -->
<div id="modal-folio-detail" class="modal">
    <div class="modal-content" style="max-width: 720px;">
        <span class="close" onclick="closeModal('modal-folio-detail'); clearFolioUrlParams();">&times;</span>
        <h3 id="folio-detail-title" style="color: #ffffff; margin-bottom: 1rem;">📋 Detalle del Folio</h3>

        <!-- Financial Summary -->
        <div class="folio-summary-grid">
            <div class="folio-summary-box">
                <span class="summary-label">Total Cargos</span>
                <span class="summary-value text-charges" id="folio-detail-charges">S/ 0.00</span>
            </div>
            <div class="folio-summary-box">
                <span class="summary-label">Total Abonos</span>
                <span class="summary-value text-payments" id="folio-detail-payments">S/ 0.00</span>
            </div>
            <div class="folio-summary-box">
                <span class="summary-label">Saldo Pendiente</span>
                <span class="summary-value" id="folio-detail-balance">S/ 0.00</span>
            </div>
        </div>

        <!-- Charges Table -->
        <div class="folio-detail-section">
            <h4>📝 Cargos Registrados</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Cant.</th>
                            <th>P. Unit.</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody id="folio-charges-body">
                        <tr><td colspan="5" class="text-center text-muted">Sin cargos registrados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="folio-detail-section">
            <h4>💰 Abonos / Pagos</h4>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipo</th>
                            <th>Monto</th>
                            <th>Método</th>
                            <th>Referencia</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody id="folio-payments-body">
                        <tr><td colspan="5" class="text-center text-muted">Sin abonos registrados.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/pages/folios.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
