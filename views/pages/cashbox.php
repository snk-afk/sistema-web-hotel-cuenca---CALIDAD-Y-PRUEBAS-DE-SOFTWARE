<?php
declare(strict_types=1);

$page_title = 'Caja Chica y Turnos';
$page_styles = ['/assets/css/pages/cashbox.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>💵 Turnos y Movimientos de Caja</h2>
        <p class="text-muted">Gestión de Caja Chica y Turnos de Trabajo: control de flujo de efectivo, arqueo diario y trazabilidad de ingresos y egresos.</p>
    </div>
    <div class="d-flex gap-2" id="cashbox-header-actions" style="display: none;">
        <button class="btn btn-outline-info" onclick="openModal('modal-new-movement')">➕ Registrar Movimiento</button>
        <button class="btn btn-danger" onclick="openCloseShiftModal()">🔒 Cerrar Turno / Arqueo</button>
    </div>
</div>

<!-- CONTAINER IF NO ACTIVE SHIFT -->
<div id="cashbox-no-shift-container" class="card cashbox-welcome-box" style="display: none;">
    <div class="welcome-icon">🔒</div>
    <h3>No hay un turno de caja abierto</h3>
    <p class="text-muted mb-4">Para comenzar a operar cobros en recepción, ventas de tienda o pagos de estancias, debe aperturar un nuevo turno de caja chica.</p>

    <form id="form-open-shift" onsubmit="handleOpenShiftSubmit(event)">
        <div class="form-group text-left mb-3">
            <label class="font-bold">Monto Inicial de Caja Chica (Fondo Fijo S/):</label>
            <input type="number" step="0.50" id="open-initial-cash" class="form-control" value="100.00" min="0" required>
        </div>

        <div class="form-group text-left mb-3">
            <label>Notas / Observaciones de Apertura (Opcional):</label>
            <input type="text" id="open-shift-notes" class="form-control" placeholder="Ej: Fondo inicial en billetes de 20 y 10">
        </div>

        <button type="submit" id="btn-submit-open-shift" class="btn btn-success btn-block">🔓 Abrir Turno de Caja Chica</button>
    </form>
</div>

<!-- CONTAINER IF ACTIVE SHIFT -->
<div id="cashbox-active-shift-container" style="display: none;">
    <!-- 4 Metrics Cards -->
    <div class="cashbox-metrics-grid mb-3">
        <div class="cashbox-metric-card">
            <div class="metric-icon bg-primary-soft">🔓</div>
            <div class="metric-info">
                <span class="metric-title">Fondo Inicial</span>
                <span class="metric-value" id="metric-initial-cash">S/ 0.00</span>
            </div>
        </div>

        <div class="cashbox-metric-card">
            <div class="metric-icon bg-success-soft">💰</div>
            <div class="metric-info">
                <span class="metric-title">Ingresos del Turno</span>
                <span class="metric-value" id="metric-total-incomes">S/ 0.00</span>
            </div>
        </div>

        <div class="cashbox-metric-card">
            <div class="metric-icon bg-danger-soft">💸</div>
            <div class="metric-info">
                <span class="metric-title">Egresos del Turno</span>
                <span class="metric-value" id="metric-total-expenses">S/ 0.00</span>
            </div>
        </div>

        <div class="cashbox-metric-card">
            <div class="metric-icon bg-warning-soft">💵</div>
            <div class="metric-info">
                <span class="metric-title">Efectivo en Gaveta</span>
                <span class="metric-value text-success" id="metric-expected-cash">S/ 0.00</span>
            </div>
        </div>
    </div>

    <!-- Shift Details Summary Card -->
    <div class="shift-status-card">
        <div class="shift-status-header">
            <div>
                <h3 style="margin: 0; color: #ffffff;">Turno Activo #<span id="shift-id-display">--</span></h3>
                <span class="badge badge-success mt-1">🟢 EN CURSO</span>
            </div>
            <div class="text-right">
                <span class="text-muted" style="font-size: 0.85rem;">Apertura:</span>
                <strong id="shift-opened-at-display" class="d-block text-info">--</strong>
            </div>
        </div>

        <div class="shift-info-grid">
            <div class="shift-info-item">
                <label>Responsable:</label>
                <strong id="shift-user-display">Recepcionista (Turno #1)</strong>
            </div>
            <div class="shift-info-item">
                <label>Total Movimientos:</label>
                <strong id="shift-movements-count">0 registrados</strong>
            </div>
            <div class="shift-info-item">
                <label>Cobros Digitales (Yape/Card):</label>
                <strong id="shift-digital-payments" class="text-info">S/ 0.00</strong>
            </div>
        </div>
    </div>

    <!-- Movements Table Card -->
    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 style="margin: 0; color: #ffffff;">📜 Movimientos del Turno</h3>
            <button class="btn btn-sm btn-outline-info" onclick="openModal('modal-new-movement')">➕ Registrar Movimiento</button>
        </div>

        <div class="table-responsive">
            <table class="table" id="table-cashbox-movements">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Concepto / Detalle</th>
                        <th>Método</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody id="table-cashbox-movements-body">
                    <tr><td colspan="6" class="text-center text-muted py-3">Cargando movimientos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- SECCIÓN: HISTORIAL DE TURNOS DE CAJA (SPEC-0040) -->
<div class="card mt-4" id="cashbox-history-container">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h3 style="margin: 0; color: #ffffff;">📋 Historial de Turnos de Caja</h3>
            <p class="text-muted" style="margin: 0; font-size: 0.85rem;">Consulta de turnos cerrados, arqueos históricos y trazabilidad de movimientos</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" id="btn-export-csv" class="btn btn-sm btn-outline-success" onclick="exportCashboxHistoryCsv()">📊 Exportar CSV</button>
            <button type="button" id="btn-export-pdf" class="btn btn-sm btn-outline-danger" onclick="exportCashboxHistoryPdf()">📄 Exportar PDF</button>
        </div>
    </div>

    <!-- Barra de Filtros del Historial -->
    <div class="history-filters-bar mb-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 10px; background: rgba(255,255,255,0.03); padding: 12px; border-radius: 8px;">
        <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.75rem; color: #a0aec0;">Estado:</label>
            <select id="history-filter-status" class="form-control form-control-sm" onchange="loadCashboxHistory()">
                <option value="">Todos los Estados</option>
                <option value="closed">🔒 Cerrados</option>
                <option value="open">🔓 Abiertos</option>
            </select>
        </div>
        <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.75rem; color: #a0aec0;">ID Responsable:</label>
            <input type="number" id="history-filter-user-id" class="form-control form-control-sm" placeholder="Ej: 1" min="1" oninput="loadCashboxHistory()">
        </div>
        <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.75rem; color: #a0aec0;">Desde:</label>
            <input type="date" id="history-filter-from" class="form-control form-control-sm" onchange="loadCashboxHistory()">
        </div>
        <div class="form-group" style="margin: 0;">
            <label style="font-size: 0.75rem; color: #a0aec0;">Hasta:</label>
            <input type="date" id="history-filter-to" class="form-control form-control-sm" onchange="loadCashboxHistory()">
        </div>
    </div>

    <!-- Tabla del Historial -->
    <div class="table-responsive">
        <table class="table" id="table-cashbox-history">
            <thead>
                <tr>
                    <th># Turno</th>
                    <th>Responsable</th>
                    <th>Apertura</th>
                    <th>Cierre</th>
                    <th>Fondo Inicial</th>
                    <th>Esperado</th>
                    <th>Declarado</th>
                    <th>Diferencia</th>
                    <th>Estado</th>
                    <th>Arqueo</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody id="table-cashbox-history-body">
                <tr><td colspan="11" class="text-center text-muted py-3">Cargando historial de turnos...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: REGISTRAR MOVIMIENTO EXTRAORDINARIO -->
<div id="modal-new-movement" class="modal">
    <div class="modal-content" style="max-width: 520px;">
        <span class="close" onclick="closeModal('modal-new-movement')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">➕ Registrar Movimiento de Caja</h3>

        <form id="form-new-movement" onsubmit="handleNewMovementSubmit(event)">
            <div class="form-group">
                <label>Tipo de Movimiento *</label>
                <select id="movement-type" class="form-control" onchange="updateMovementCategories()">
                    <option value="expense">💸 Egreso / Salida de Dinero</option>
                    <option value="income">💰 Ingreso / Entrada Extraordinaria</option>
                </select>
            </div>

            <div class="form-group">
                <label>Categoría</label>
                <select id="movement-category" class="form-control">
                    <option value="petty_expense">Gasto Menor / Insumos Urgentes</option>
                    <option value="delivery_taxi">Movilidad / Envíos</option>
                    <option value="services">Pago de Servicios</option>
                    <option value="other">Otro Egreso</option>
                </select>
            </div>

            <div class="form-group">
                <label>Concepto / Detalle *</label>
                <input type="text" id="movement-concept" class="form-control" placeholder="Ej: Compra de focos para recepción" required>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Monto (S/) *</label>
                    <input type="number" step="0.10" id="movement-amount" class="form-control" placeholder="0.00" min="0.10" required>
                </div>
                <div class="form-group">
                    <label>Método de Pago</label>
                    <select id="movement-payment-method" class="form-control">
                        <option value="cash">💵 Efectivo</option>
                        <option value="yape">🟣 Yape / Plin</option>
                        <option value="card">💳 Tarjeta</option>
                        <option value="transfer">🏦 Transferencia</option>
                    </select>
                </div>
            </div>

            <button type="submit" id="btn-submit-movement" class="btn btn-primary btn-block mt-3">💾 Registrar Movimiento</button>
        </form>
    </div>
</div>

<!-- MODAL: CERRAR TURNO / ARQUEO -->
<div id="modal-close-shift" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <span class="close" onclick="closeModal('modal-close-shift')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">🔒 Cierre de Turno y Arqueo de Caja</h3>

        <div class="arqueo-comparison-grid">
            <div class="arqueo-box">
                <label>Fondo Inicial:</label>
                <strong id="arqueo-initial-cash" class="text-info">S/ 0.00</strong>
            </div>
            <div class="arqueo-box">
                <label>Efectivo Esperado:</label>
                <strong id="arqueo-expected-cash" class="text-success">S/ 0.00</strong>
            </div>
        </div>

        <form id="form-close-shift-modal" onsubmit="handleCloseShiftModalSubmit(event)">
            <div class="form-group">
                <label class="font-bold">Efectivo Real Contado en Gaveta (S/) *</label>
                <input type="number" step="0.01" id="close-real-cash-declared" class="form-control" placeholder="0.00" min="0" required oninput="calculateArqueoDifference()">
            </div>

            <div class="form-group">
                <label>Yape Real Verificado (S/) *</label>
                <input type="number" step="0.01" id="close-real-yape-declared" class="form-control" placeholder="0.00" min="0" required>
            </div>

            <div class="form-group">
                <label>Plin Real Verificado (S/) *</label>
                <input type="number" step="0.01" id="close-real-plin-declared" class="form-control" placeholder="0.00" min="0" required>
            </div>

            <div class="form-group">
                <label>Tarjeta Real Verificada (S/) *</label>
                <input type="number" step="0.01" id="close-real-card-declared" class="form-control" placeholder="0.00" min="0" required>
            </div>

            <div class="form-group">
                <label>Transferencia Real Verificada (S/) *</label>
                <input type="number" step="0.01" id="close-real-transfer-declared" class="form-control" placeholder="0.00" min="0" required>
            </div>

            <div id="arqueo-diff-display" class="arqueo-difference-box balanced">
                DIFERENCIA: S/ 0.00 (CUADRADO)
            </div>

            <div class="form-group">
                <label>Observaciones de Cierre (Opcional):</label>
                <textarea id="close-shift-notes" class="form-control" rows="2" placeholder="Ej: Se deja sencillo para el siguiente turno."></textarea>
            </div>

            <button type="submit" id="btn-submit-close-shift" class="btn btn-danger btn-block mt-3">🔒 Confirmar Cierre y Sellado de Turno</button>
        </form>
    </div>
</div>

<!-- MODAL: DETALLE DE TURNO CERRADO (SPEC-0040) -->
<div id="modal-shift-detail" class="modal">
    <div class="modal-content" style="max-width: 760px;">
        <span class="close" onclick="closeModal('modal-shift-detail')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">🔍 Detalle de Turno #<span id="detail-shift-id">---</span></h3>

        <div class="shift-detail-header-grid mb-3" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px;">
            <div class="arqueo-box">
                <label>Responsable:</label>
                <strong id="detail-shift-user">---</strong>
            </div>
            <div class="arqueo-box">
                <label>Apertura:</label>
                <strong id="detail-shift-opened-at">---</strong>
            </div>
            <div class="arqueo-box">
                <label>Cierre:</label>
                <strong id="detail-shift-closed-at">---</strong>
            </div>
            <div class="arqueo-box">
                <label>Fondo Inicial:</label>
                <strong id="detail-shift-initial-cash" class="text-info">S/ 0.00</strong>
            </div>
            <div class="arqueo-box">
                <label>Efectivo Esperado:</label>
                <strong id="detail-shift-expected-cash" class="text-success">S/ 0.00</strong>
            </div>
            <div class="arqueo-box">
                <label>Efectivo Declarado:</label>
                <strong id="detail-shift-declared-cash">S/ 0.00</strong>
            </div>
        </div>

        <div id="detail-shift-diff-box" class="arqueo-difference-box balanced mb-3">
            DIFERENCIA: S/ 0.00
        </div>

        <div id="detail-shift-notes-box" class="mb-3 text-muted" style="font-size: 0.85rem; font-style: italic;">
            <!-- Notas de cierre -->
        </div>

        <h4 style="color: #ffffff; margin-bottom: 0.5rem; font-size: 1rem;">📜 Movimientos Registrados</h4>
        <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
            <table class="table" id="table-detail-movements">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Hora</th>
                        <th>Tipo</th>
                        <th>Concepto / Detalle</th>
                        <th>Método</th>
                        <th>Monto</th>
                    </tr>
                </thead>
                <tbody id="table-detail-movements-body">
                    <tr><td colspan="6" class="text-center text-muted py-3">Cargando movimientos...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/assets/js/pages/cashbox.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
