<?php
declare(strict_types=1);

$page_title = 'Tienda POS / Minibar';
$page_styles = ['/assets/css/pages/store.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>🛒 Punto de Venta (POS) — Tienda del Hotel</h2>
        <p class="text-muted">Punto de Venta (POS) Tienda: venta directa, carga a habitación y gestión de inventario.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-success" onclick="openModal('modal-create-product')">➕ Nuevo Producto</button>
        <a href="/" class="btn btn-primary">📊 Ir al Dashboard</a>
    </div>
</div>

<!-- Metrics Cards -->
<div class="store-metrics-grid mb-3">
    <div class="store-metric-card">
        <div class="metric-icon bg-primary-soft">📦</div>
        <div class="metric-info">
            <span class="metric-title">Productos Activos</span>
            <span class="metric-value" id="metric-products-count">0</span>
        </div>
    </div>

    <div class="store-metric-card">
        <div class="metric-icon bg-warning-soft">⚠️</div>
        <div class="metric-info">
            <span class="metric-title">Stock Bajo</span>
            <span class="metric-value" id="metric-low-stock">0</span>
        </div>
    </div>

    <div class="store-metric-card">
        <div class="metric-icon bg-success-soft">💰</div>
        <div class="metric-info">
            <span class="metric-title">Valor del Inventario</span>
            <span class="metric-value" id="metric-inventory-value">S/ 0.00</span>
        </div>
    </div>
</div>

<!-- POS Layout -->
<div class="pos-layout">
    <!-- Left Column: Product Catalog -->
    <div class="card">
        <div class="catalog-search-bar">
            <div class="search-box flex-1">
                <span class="search-icon">🔍</span>
                <input type="text" id="filter-products-input" class="search-input" placeholder="Buscar producto por nombre o SKU..." onkeyup="filterProductsCatalog()">
            </div>
        </div>

        <div class="pos-catalog-grid" id="pos-catalog-container">
            <p class="text-muted text-center">Cargando catálogo...</p>
        </div>
    </div>

    <!-- Right Column: Cart -->
    <div class="pos-cart-panel" id="pos-cart-panel">
        <h3>🛒 Carrito de Compras</h3>

        <ul class="cart-items-list" id="store-cart-items">
            <li class="cart-empty-msg">El carrito está vacío.</li>
        </ul>

        <div class="cart-total-bar">
            <span class="cart-total-label">TOTAL:</span>
            <span class="cart-total-amount" id="store-cart-total">S/ 0.00</span>
        </div>

        <div class="cart-actions">
            <button class="btn btn-success" id="btn-sell-direct" onclick="processDirectSale()" disabled>💵 Venta Directa (Contado)</button>
            <button class="btn btn-warning" id="btn-charge-room" onclick="openChargeToRoomModal()" disabled>🏨 Cargar a Habitación</button>
        </div>
    </div>
</div>

<!-- MODAL: CREATE PRODUCT -->
<div id="modal-create-product" class="modal">
    <div class="modal-content" style="max-width: 560px;">
        <span class="close" onclick="closeModal('modal-create-product')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">➕ Registrar Nuevo Producto</h3>

        <form id="form-create-product" onsubmit="handleCreateProduct(event)">
            <div class="form-group">
                <label>Nombre del Producto *</label>
                <input type="text" id="product-name" class="form-control" placeholder="Ej: Coca-Cola 500ml" required>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>SKU (Opcional)</label>
                    <input type="text" id="product-sku" class="form-control" placeholder="Ej: BEB-001">
                </div>
                <div class="form-group">
                    <label>Descripción (Opcional)</label>
                    <input type="text" id="product-description" class="form-control" placeholder="Breve descripción">
                </div>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Precio de Venta (S/) *</label>
                    <input type="number" id="product-sale-price" class="form-control" step="0.01" min="0" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Precio de Costo (S/)</label>
                    <input type="number" id="product-cost-price" class="form-control" step="0.01" min="0" placeholder="0.00" value="0">
                </div>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Stock Inicial</label>
                    <input type="number" id="product-stock" class="form-control" min="0" placeholder="0" value="0">
                </div>
                <div class="form-group">
                    <label>Stock Mínimo (Alerta)</label>
                    <input type="number" id="product-min-stock" class="form-control" min="0" placeholder="5" value="5">
                </div>
            </div>

            <button type="submit" class="btn btn-success btn-block mt-3">💾 Guardar Producto</button>
        </form>
    </div>
</div>

<!-- Cashbox Alert Banner (Hidden if open) -->
<div id="store-cashbox-alert" class="alert alert-warning mb-3" style="display: none;">
    <span>⚠️ <strong>Caja Cerrada:</strong> No hay un turno de caja abierto para registrar pagos en efectivo.</span>
    <a href="/caja" class="btn btn-sm btn-outline-warning ml-2">Abrir Turno en Caja</a>
</div>

<!-- MODAL: SELECT ROOM FOR CHARGE -->
<div id="modal-charge-room" class="modal">
    <div class="modal-content" style="max-width: 520px;">
        <span class="close" onclick="closeModal('modal-charge-room')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">🏨 Seleccionar Habitación para Cargar Consumo</h3>

        <div class="room-select-list" id="room-select-list">
            <p class="text-muted text-center">Cargando habitaciones ocupadas...</p>
        </div>

        <button class="btn btn-warning btn-block mt-3" id="btn-confirm-charge-room" onclick="confirmChargeToRoom()" disabled>
            ✅ Confirmar Cargo a Habitación
        </button>
    </div>
</div>

<!-- MODAL: STORE DIRECT SALE CHECKOUT (Comprobante y Métodos de Pago) -->
<div id="modal-store-checkout" class="modal">
    <div class="modal-content" style="max-width: 540px;">
        <span class="close" onclick="closeModal('modal-store-checkout')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">💵 Finalizar Venta Directa POS</h3>

        <div class="checkout-balance-box mb-3" style="background: rgba(15, 23, 42, 0.6); padding: 14px; border-radius: 6px; text-align: center; border: 1px solid var(--border-color);">
            <span class="text-muted" style="font-size: 0.85rem; text-transform: uppercase;">Total a Cobrar:</span>
            <h2 id="store-checkout-total-amount" class="text-success" style="font-size: 1.8rem; margin: 4px 0 0 0; font-weight: 800;">S/ 0.00</h2>
        </div>

        <form id="form-store-checkout" onsubmit="executeStoreCheckout(event)">
            <div class="form-group">
                <label>Emitir Comprobante:</label>
                <select id="store-checkout-doc-type" class="form-control" onchange="toggleStoreInvoiceFields()">
                    <option value="NOTA_VENTA">Nota de Venta Interna</option>
                    <option value="BOLETA">Boleta de Venta Electrónica (B001)</option>
                    <option value="FACTURA">Factura Electrónica (F001)</option>
                </select>
            </div>

            <!-- Campos Dinámicos para Factura SUNAT -->
            <div id="field-store-factura-ruc" class="form-group" style="display: none;">
                <label class="text-info">RUC del Cliente (11 dígitos):</label>
                <input type="text" id="store-checkout-ruc" class="form-control" placeholder="Ej: 20601234567" maxlength="11">
                
                <label class="text-info mt-2">Razón Social:</label>
                <input type="text" id="store-checkout-company" class="form-control" placeholder="Razón Social de la Empresa">
            </div>

            <div class="form-group">
                <label>Método de Pago:</label>
                <select id="store-checkout-payment-method" class="form-control">
                    <option value="cash">💵 Efectivo</option>
                    <option value="yape">🟣 Yape / Plin</option>
                    <option value="card">💳 Tarjeta de Crédito / Débito</option>
                    <option value="transfer">🏦 Transferencia Bancaria</option>
                </select>
            </div>

            <div class="form-group">
                <label>N° de Operación / Referencia (Opcional):</label>
                <input type="text" id="store-checkout-reference" class="form-control" placeholder="Ej: 981240 / POS-4412">
            </div>

            <button type="submit" id="btn-submit-store-checkout" class="btn btn-success btn-block mt-3">
                💵 Confirmar Pago y Emitir Venta
            </button>
        </form>
    </div>
</div>

<!-- MODAL: RECIBO / VOUCHER TÉRMICO TRAS VENTA DIRECTA POS -->
<div id="modal-store-receipt" class="modal">
    <div class="modal-content" style="max-width: 520px; background: var(--bg-card);">
        <span class="close" onclick="closeModal('modal-store-receipt')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 0.8rem; font-size: 1.1rem;">🧾 Comprobante de Venta Emitido</h3>

        <iframe id="store-receipt-pdf-frame" src="" style="width: 100%; height: 60vh; border: 1px solid var(--border-color); border-radius: var(--radius-sm); background: #ffffff;"></iframe>

        <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
            <div class="d-flex gap-2" id="store-receipt-actions">
                <!-- Botones dinámicos de voucher y ESC/POS -->
            </div>
            <button class="btn btn-secondary" onclick="closeModal('modal-store-receipt')">Listo / Cerrar</button>
        </div>
    </div>
</div>

<script src="/assets/js/pages/store.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
