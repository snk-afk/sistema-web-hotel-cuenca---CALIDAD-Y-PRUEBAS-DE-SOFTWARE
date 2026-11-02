<?php
declare(strict_types=1);

$page_title = 'Facturación y Comprobantes';
$page_styles = ['/assets/css/pages/invoices.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>🧾 Facturación Electrónica SUNAT y Comprobantes</h2>
        <p class="text-muted">Comprobantes y Facturación SUNAT: emisión de boletas, facturas, notas de venta y almacenamiento tributario.</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="openModal('modal-issue-invoice')">➕ Emitir Comprobante Manual</button>
    </div>
</div>

<!-- 4 Metrics Cards -->
<div class="invoices-metrics-grid mb-3">
    <div class="invoices-metric-card">
        <div class="metric-icon bg-success-soft">💰</div>
        <div class="metric-info">
            <span class="metric-title">Total Facturado</span>
            <span class="metric-value text-success" id="metric-invoices-total-amount">S/ 0.00</span>
        </div>
    </div>

    <div class="invoices-metric-card">
        <div class="metric-icon bg-primary-soft">📄</div>
        <div class="metric-info">
            <span class="metric-title">Boletas Emitidas</span>
            <span class="metric-value" id="metric-boletas-count">0</span>
        </div>
    </div>

    <div class="invoices-metric-card">
        <div class="metric-icon bg-info-soft">🏢</div>
        <div class="metric-info">
            <span class="metric-title">Facturas Emitidas</span>
            <span class="metric-value" id="metric-facturas-count">0</span>
        </div>
    </div>

    <div class="invoices-metric-card">
        <div class="metric-icon bg-warning-soft">✅</div>
        <div class="metric-info">
            <span class="metric-title">Aceptadas SUNAT</span>
            <span class="metric-value" id="metric-sunat-accepted-count">0</span>
        </div>
    </div>
</div>

<!-- Main Table Card with Tabs & Search -->
<div class="card">
    <div class="invoices-tabs-bar">
        <button class="tab-btn active" data-type="all" onclick="filterInvoicesByTab('all', this)">
            Todos <span class="tab-counter" id="tab-count-all">0</span>
        </button>
        <button class="tab-btn" data-type="BOLETA" onclick="filterInvoicesByTab('BOLETA', this)">
            📄 Boletas <span class="tab-counter" id="tab-count-boletas">0</span>
        </button>
        <button class="tab-btn" data-type="FACTURA" onclick="filterInvoicesByTab('FACTURA', this)">
            🏢 Facturas <span class="tab-counter" id="tab-count-facturas">0</span>
        </button>
        <button class="tab-btn" data-type="NOTA_VENTA" onclick="filterInvoicesByTab('NOTA_VENTA', this)">
            📝 Notas de Venta <span class="tab-counter" id="tab-count-notas">0</span>
        </button>
    </div>

    <!-- Search Bar -->
    <div class="reservations-filter-bar mb-3">
        <div class="search-box flex-1">
            <span class="search-icon">🔍</span>
            <input type="text" id="filter-invoices-input" class="search-input" placeholder="Buscar por número de comprobante, cliente o DNI/RUC..." onkeyup="filterInvoicesTable()">
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="table-responsive">
        <table class="table" id="table-invoices">
            <thead>
                <tr>
                    <th>#</th>
                    <th>N° Comprobante</th>
                    <th>Tipo Doc</th>
                    <th>Doc Cliente</th>
                    <th>Cliente / Razón Social</th>
                    <th>Total (S/)</th>
                    <th>Estado SUNAT</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="table-invoices-body">
                <tr><td colspan="8" class="text-center text-muted py-3">Cargando comprobantes...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: DETALLE / VOUCHER DE COMPROBANTE OFICIAL SUNAT -->
<div id="modal-invoice-detail" class="modal">
    <div class="modal-content" style="max-width: 550px; background: var(--bg-card);">
        <span class="close" onclick="closeModal('modal-invoice-detail')">&times;</span>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 style="color: #ffffff; margin: 0; font-size: 1.1rem;">🧾 Comprobante Electrónico</h3>
            <div class="btn-group btn-group-sm">
                <button type="button" id="btn-view-mode-voucher" class="btn btn-primary active" onclick="switchInvoiceViewMode('voucher')">🖨️ Voucher 80mm</button>
                <button type="button" id="btn-view-mode-a4" class="btn btn-outline-secondary" onclick="switchInvoiceViewMode('a4')">📄 PDF A4</button>
            </div>
        </div>

        <!-- PDF iframe: muestra el PDF/VOUCHER REAL generado y almacenado en disco -->
        <iframe id="invoice-pdf-frame" src="" style="width: 100%; height: 60vh; border: 1px solid var(--border-color); border-radius: var(--radius-sm); background: #ffffff;"></iframe>

        <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
            <div class="d-flex gap-2" id="invoice-detail-downloads">
                <!-- Botones dinámicos de descarga y acciones térmicas -->
            </div>
            <button class="btn btn-secondary" onclick="closeModal('modal-invoice-detail')">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL: EMITIR COMPROBANTE MANUAL -->
<div id="modal-issue-invoice" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeModal('modal-issue-invoice')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">➕ Emitir Comprobante Manual</h3>

        <form id="form-issue-invoice" onsubmit="handleIssueInvoiceSubmit(event)">
            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Tipo de Comprobante *</label>
                    <select id="issue-doc-type" class="form-control" onchange="toggleManualInvoiceDocFields()">
                        <option value="BOLETA">📄 Boleta Electrónica (B001)</option>
                        <option value="FACTURA">🏢 Factura Electrónica (F001)</option>
                        <option value="NOTA_VENTA">📝 Nota de Venta (NV01)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo Doc. Cliente</label>
                    <select id="issue-customer-doc-type" class="form-control">
                        <option value="DNI">DNI (8 dígitos)</option>
                        <option value="RUC">RUC (11 dígitos)</option>
                        <option value="CE">Carnet Extranjería</option>
                        <option value="PASSPORT">Pasaporte</option>
                    </select>
                </div>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>N° Documento *</label>
                    <input type="text" id="issue-customer-doc-num" class="form-control" placeholder="Ej: 72345678" required>
                </div>
                <div class="form-group">
                    <label>Cliente / Razón Social *</label>
                    <input type="text" id="issue-customer-name" class="form-control" placeholder="Ej: Juan Pérez" required>
                </div>
            </div>

            <div class="form-group">
                <label>Dirección Fiscal (Opcional):</label>
                <input type="text" id="issue-customer-address" class="form-control" placeholder="Ej: Av. Principal 123, Lima">
            </div>

            <div class="form-group">
                <label>Descripción del Servicio / Ítem Facturado *</label>
                <input type="text" id="issue-item-description" class="form-control" placeholder="Ej: Hospedaje Habitación 201 - 2 Noches" required>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Cantidad</label>
                    <input type="number" id="issue-item-qty" class="form-control" value="1" min="1" required oninput="recalculateManualInvoiceTotal()">
                </div>
                <div class="form-group">
                    <label>Precio Unitario (S/) *</label>
                    <input type="number" step="0.50" id="issue-item-price" class="form-control" placeholder="0.00" min="1.00" required oninput="recalculateManualInvoiceTotal()">
                </div>
            </div>

            <div class="voucher-breakdown-card mb-3">
                <div class="row-total">
                    <span class="text-muted">Base Imponible:</span>
                    <strong id="manual-preview-taxable">S/ 0.00</strong>
                </div>
                <div class="row-total">
                    <span class="text-muted">IGV (18%):</span>
                    <strong id="manual-preview-igv">S/ 0.00</strong>
                </div>
                <div class="row-total grand-total">
                    <span>TOTAL COMPROBANTE:</span>
                    <span id="manual-preview-total">S/ 0.00</span>
                </div>
            </div>

            <button type="submit" id="btn-submit-issue-invoice" class="btn btn-primary btn-block">🚀 Emitir y Enviar a SUNAT</button>
        </form>
    </div>
</div>

<script src="/assets/js/pages/invoices.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
