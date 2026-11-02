<?php
declare(strict_types=1);

$page_title = 'Egresos y Otros Ingresos';
$page_styles = ['/assets/css/pages/expenses.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>💸 Egresos de Caja Chica y Otros Ingresos</h2>
        <p class="text-muted">Control de compras menores, insumos, mantenimiento, cocheras y servicios externos.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-danger" onclick="openModal('modal-create-expense')">🔴 Registrar Egreso</button>
        <button class="btn btn-success" onclick="openModal('modal-create-income')">🟢 Registrar Otro Ingreso</button>
    </div>
</div>

<!-- Cashbox Closed Alert Banner (Hidden if open) -->
<div id="expenses-cashbox-alert" class="alert alert-warning mb-3" style="display: none;">
    <span>⚠️ <strong>Caja Cerrada:</strong> Para registrar movimientos de efectivo debe haber un turno de caja abierto.</span>
    <a href="/caja" class="btn btn-sm btn-outline-warning ml-2">Abrir Turno en Caja</a>
</div>

<!-- 3 Metrics Cards -->
<div class="expenses-metrics-grid mb-3">
    <div class="expenses-metric-card">
        <div class="metric-icon bg-danger-soft">💸</div>
        <div class="metric-info">
            <span class="metric-title">Total Egresos</span>
            <span class="metric-value text-danger" id="metric-total-expenses">S/ 0.00</span>
        </div>
    </div>

    <div class="expenses-metric-card">
        <div class="metric-icon bg-success-soft">💰</div>
        <div class="metric-info">
            <span class="metric-title">Otros Ingresos</span>
            <span class="metric-value text-success" id="metric-total-other-incomes">S/ 0.00</span>
        </div>
    </div>

    <div class="expenses-metric-card">
        <div class="metric-icon bg-primary-soft">⚖️</div>
        <div class="metric-info">
            <span class="metric-title">Balance Neto</span>
            <span class="metric-value" id="metric-net-balance">S/ 0.00</span>
        </div>
    </div>
</div>

<!-- Main Table Card with Tabs -->
<div class="card">
    <div class="expenses-tabs-bar">
        <button class="tab-btn active" data-tab="expenses" onclick="switchExpensesTab('expenses', this)">
            💸 Egresos / Gastos <span class="tab-counter" id="tab-count-expenses">0</span>
        </button>
        <button class="tab-btn" data-tab="incomes" onclick="switchExpensesTab('incomes', this)">
            💰 Otros Ingresos <span class="tab-counter" id="tab-count-incomes">0</span>
        </button>
    </div>

    <!-- Search Bar -->
    <div class="reservations-filter-bar mb-3">
        <div class="search-box flex-1">
            <span class="search-icon">🔍</span>
            <input type="text" id="filter-expenses-input" class="search-input" placeholder="Buscar por concepto, categoría, proveedor o N° comprobante..." onkeyup="filterExpensesTables()">
        </div>
    </div>

    <!-- Table 1: Egresos -->
    <div id="tab-expenses-content" class="table-responsive">
        <table class="table" id="table-expenses">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Categoría</th>
                    <th>Concepto / Detalle</th>
                    <th>Proveedor</th>
                    <th>N° Comprobante</th>
                    <th>Método</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody id="table-expenses-body">
                <tr><td colspan="8" class="text-center text-muted py-3">Cargando egresos...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Table 2: Otros Ingresos -->
    <div id="tab-incomes-content" class="table-responsive" style="display: none;">
        <table class="table" id="table-incomes">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Categoría</th>
                    <th>Concepto / Detalle</th>
                    <th>Observaciones</th>
                    <th>Método</th>
                    <th>Monto</th>
                </tr>
            </thead>
            <tbody id="table-incomes-body">
                <tr><td colspan="7" class="text-center text-muted py-3">Cargando otros ingresos...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: REGISTRAR EGRESO -->
<div id="modal-create-expense" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <span class="close" onclick="closeModal('modal-create-expense')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">🔴 Registrar Egreso / Gasto</h3>

        <form id="form-create-expense" onsubmit="handleCreateExpenseSubmit(event)">
            <div class="form-group">
                <label>Categoría de Gasto *</label>
                <select id="expense-category" class="form-control">
                    <option value="supplies">Artículos de Limpieza / Insumos</option>
                    <option value="maintenance">Mantenimiento de Infraestructura</option>
                    <option value="utilities">Servicios Públicos / Compras Menores</option>
                    <option value="food_beverage">Reposición Tienda / Minibar</option>
                    <option value="petty_cash">Caja Chica General</option>
                    <option value="other">Otro Egreso</option>
                </select>
            </div>

            <div class="form-group">
                <label>Concepto / Justificación del Gasto *</label>
                <input type="text" id="expense-concept" class="form-control" placeholder="Ej: Compra de focos, lejía y ambientadores" required>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Proveedor / Establecimiento</label>
                    <input type="text" id="expense-supplier" class="form-control" placeholder="Ej: Ferretería El Sol">
                </div>
                <div class="form-group">
                    <label>N° Boleta / Factura / Ticket</label>
                    <input type="text" id="expense-receipt" class="form-control" placeholder="Ej: B001-4421">
                </div>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Monto Pagado (S/) *</label>
                    <input type="number" step="0.50" id="expense-amount" class="form-control" placeholder="0.00" min="0.50" required>
                </div>
                <div class="form-group">
                    <label>Método de Pago</label>
                    <select id="expense-payment-method" class="form-control">
                        <option value="cash">💵 Efectivo (Caja Chica)</option>
                        <option value="yape">🟣 Yape / Plin</option>
                        <option value="card">💳 Tarjeta Débito/Crédito</option>
                        <option value="transfer">🏦 Transferencia Bancaria</option>
                    </select>
                </div>
            </div>

            <button type="submit" id="btn-submit-expense" class="btn btn-danger btn-block mt-3">🔴 Registrar y Descontar Egreso</button>
        </form>
    </div>
</div>

<!-- MODAL: REGISTRAR OTRO INGRESO -->
<div id="modal-create-income" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <span class="close" onclick="closeModal('modal-create-income')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">🟢 Registrar Otro Ingreso (No Operativo)</h3>

        <form id="form-create-income" onsubmit="handleCreateIncomeSubmit(event)">
            <div class="form-group">
                <label>Categoría de Ingreso *</label>
                <select id="income-category" class="form-control">
                    <option value="parking">Alquiler Cochera Independiente</option>
                    <option value="laundry_external">Servicio de Lavandería Externo</option>
                    <option value="penalty_damage">Penalidad / Rotura de Bienes</option>
                    <option value="events">Eventos / Alquiler de Espacio</option>
                    <option value="other_income">Otro Ingreso Extraordinario</option>
                </select>
            </div>

            <div class="form-group">
                <label>Concepto / Detalle del Ingreso *</label>
                <input type="text" id="income-concept" class="form-control" placeholder="Ej: Cochera camioneta noche completa" required>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Monto Cobrado (S/) *</label>
                    <input type="number" step="0.50" id="income-amount" class="form-control" placeholder="0.00" min="0.50" required>
                </div>
                <div class="form-group">
                    <label>Método de Cobro</label>
                    <select id="income-payment-method" class="form-control">
                        <option value="cash">💵 Efectivo</option>
                        <option value="yape">🟣 Yape / Plin</option>
                        <option value="card">💳 Tarjeta</option>
                        <option value="transfer">🏦 Transferencia</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Notas / Observaciones (Opcional):</label>
                <input type="text" id="income-notes" class="form-control" placeholder="Ej: Placa ABC-123">
            </div>

            <button type="submit" id="btn-submit-income" class="btn btn-success btn-block mt-3">🟢 Registrar Ingreso en Caja</button>
        </form>
    </div>
</div>

<script src="/assets/js/pages/expenses.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
