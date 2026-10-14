/**
 * CS-Hotel: Store POS Frontend Logic
 * Catalog, Cart, Direct Sales, Charge-to-Room, Product CRUD
 */

let storeProducts = [];
let storeCart = [];
let occupiedRooms = [];
let selectedChargeRoomId = null;

document.addEventListener('DOMContentLoaded', () => {
    loadStoreCatalog();
});

// ─── CATALOG ───────────────────────────────────────────────

async function loadStoreCatalog() {
    try {
        const result = await apiFetch('/api/products');

        if (result.data.status === 'success' && Array.isArray(result.data.data)) {
            storeProducts = result.data.data;
        } else {
            storeProducts = [];
        }

        updateStoreMetrics();
        renderCatalog();
    } catch (e) {
        document.getElementById('pos-catalog-container').innerHTML =
            '<p class="text-muted text-center">Error al cargar el catálogo.</p>';
    }
}

function updateStoreMetrics() {
    const count = storeProducts.length;
    const lowStock = storeProducts.filter(p => p.stock <= p.min_stock).length;
    const inventoryValue = storeProducts.reduce((sum, p) => sum + (p.sale_price * p.stock), 0);

    document.getElementById('metric-products-count').textContent = count;
    document.getElementById('metric-low-stock').textContent = lowStock;
    document.getElementById('metric-inventory-value').textContent = `S/ ${inventoryValue.toFixed(2)}`;
}

function renderCatalog() {
    const container = document.getElementById('pos-catalog-container');

    if (storeProducts.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No hay productos registrados. Use el botón "Nuevo Producto" para comenzar.</p>';
        return;
    }

    let html = '';
    storeProducts.forEach(product => {
        const isLowStock = product.stock <= product.min_stock && product.stock > 0;
        const isOutOfStock = product.stock <= 0;
        const stockClass = isOutOfStock ? 'stock-out' : (isLowStock ? 'stock-low' : '');
        const stockLabel = isOutOfStock ? 'SIN STOCK' : `Stock: ${product.stock}`;
        const lowBadge = isLowStock ? '<span class="badge-low-stock">⚠️ Bajo</span>' : '';
        const outBadge = isOutOfStock ? '<span class="badge-low-stock" style="color:#ef4444;background:rgba(239,68,68,0.2)">❌ Agotado</span>' : '';
        const disabled = isOutOfStock ? 'disabled' : '';

        const searchData = `${product.name} ${product.sku || ''}`.toLowerCase();

        html += `
            <div class="pos-product-card" data-search="${searchData}" data-product-id="${product.id}">
                ${lowBadge}${outBadge}
                <span class="product-name">${escapeHtml(product.name)}</span>
                <span class="product-price">S/ ${parseFloat(product.sale_price).toFixed(2)}</span>
                <span class="product-stock ${stockClass}">${stockLabel}</span>
                <button class="btn btn-sm btn-primary btn-add-cart" onclick="addToCart(${product.id})" ${disabled}>➕ Agregar</button>
            </div>
        `;
    });

    container.innerHTML = html;
}

function filterProductsCatalog() {
    const term = document.getElementById('filter-products-input').value.toLowerCase().trim();
    const cards = document.querySelectorAll('.pos-product-card');

    cards.forEach(card => {
        const searchData = card.getAttribute('data-search') || '';
        card.style.display = (term === '' || searchData.includes(term)) ? '' : 'none';
    });
}

// ─── CART ───────────────────────────────────────────────────

function addToCart(productId) {
    const product = storeProducts.find(p => p.id === productId);
    if (!product) return;

    const existing = storeCart.find(item => item.product_id === productId);
    if (existing) {
        if (existing.quantity >= product.stock) {
            if (typeof showToast === 'function') {
                showToast(`Stock máximo alcanzado para "${product.name}".`, 'warning');
            }
            return;
        }
        existing.quantity++;
        existing.subtotal = existing.quantity * existing.unit_price;
    } else {
        if (product.stock <= 0) {
            if (typeof showToast === 'function') {
                showToast(`"${product.name}" está agotado.`, 'error');
            }
            return;
        }
        storeCart.push({
            product_id: productId,
            name: product.name,
            quantity: 1,
            unit_price: parseFloat(product.sale_price),
            subtotal: parseFloat(product.sale_price),
            max_stock: product.stock,
        });
    }

    renderCart();
}

function changeCartQty(productId, delta) {
    const item = storeCart.find(i => i.product_id === productId);
    if (!item) return;

    const newQty = item.quantity + delta;
    if (newQty <= 0) {
        removeFromCart(productId);
        return;
    }

    if (newQty > item.max_stock) {
        if (typeof showToast === 'function') {
            showToast(`Stock máximo: ${item.max_stock} unidades.`, 'warning');
        }
        return;
    }

    item.quantity = newQty;
    item.subtotal = newQty * item.unit_price;
    renderCart();
}

function removeFromCart(productId) {
    storeCart = storeCart.filter(i => i.product_id !== productId);
    renderCart();
}

function clearCart() {
    storeCart = [];
    renderCart();
}

function renderCart() {
    const listEl = document.getElementById('store-cart-items');
    const totalEl = document.getElementById('store-cart-total');
    const btnSell = document.getElementById('btn-sell-direct');
    const btnCharge = document.getElementById('btn-charge-room');

    if (storeCart.length === 0) {
        listEl.innerHTML = '<li class="cart-empty-msg">El carrito está vacío.</li>';
        totalEl.textContent = 'S/ 0.00';
        btnSell.disabled = true;
        btnCharge.disabled = true;
        return;
    }

    const total = storeCart.reduce((sum, item) => sum + item.subtotal, 0);

    let html = '';
    storeCart.forEach(item => {
        html += `
            <li class="cart-item">
                <div class="cart-item-info">
                    <span class="cart-item-name">${escapeHtml(item.name)}</span>
                    <span class="cart-item-subtotal">${item.quantity} × S/ ${item.unit_price.toFixed(2)} = S/ ${item.subtotal.toFixed(2)}</span>
                </div>
                <div class="cart-qty-controls">
                    <button onclick="changeCartQty(${item.product_id}, -1)">−</button>
                    <span class="cart-qty-value">${item.quantity}</span>
                    <button onclick="changeCartQty(${item.product_id}, 1)">+</button>
                </div>
                <button class="btn-remove-item" onclick="removeFromCart(${item.product_id})">🗑️</button>
            </li>
        `;
    });

    listEl.innerHTML = html;
    totalEl.textContent = `S/ ${total.toFixed(2)}`;
    btnSell.disabled = false;
    btnCharge.disabled = false;
}

// ─── DIRECT SALE CHECKOUT (Comprobante y Métodos de Pago) ───

function toggleStoreInvoiceFields() {
    const docType = document.getElementById('store-checkout-doc-type').value;
    const facturaBox = document.getElementById('field-store-factura-ruc');
    if (facturaBox) {
        facturaBox.style.display = (docType === 'FACTURA') ? 'block' : 'none';
    }
}

function processDirectSale() {
    if (storeCart.length === 0) {
        if (typeof showToast === 'function') {
            showToast('El carrito está vacío.', 'warning');
        }
        return;
    }

    const total = storeCart.reduce((sum, item) => sum + item.subtotal, 0);
    const amountEl = document.getElementById('store-checkout-total-amount');
    if (amountEl) {
        amountEl.textContent = `S/ ${total.toFixed(2)}`;
    }

    const docTypeSelect = document.getElementById('store-checkout-doc-type');
    if (docTypeSelect) {
        docTypeSelect.value = 'NOTA_VENTA';
        toggleStoreInvoiceFields();
    }

    const rucInput = document.getElementById('store-checkout-ruc');
    if (rucInput) rucInput.value = '';
    const companyInput = document.getElementById('store-checkout-company');
    if (companyInput) companyInput.value = '';
    const refInput = document.getElementById('store-checkout-reference');
    if (refInput) refInput.value = '';

    openModal('modal-store-checkout');
}

async function executeStoreCheckout(event) {
    if (event) event.preventDefault();

    if (storeCart.length === 0) return;

    const docType = document.getElementById('store-checkout-doc-type').value;
    const paymentMethod = document.getElementById('store-checkout-payment-method').value;
    const reference = document.getElementById('store-checkout-reference').value.trim();

    if (docType === 'FACTURA') {
        const ruc = document.getElementById('store-checkout-ruc').value.trim();
        const company = document.getElementById('store-checkout-company').value.trim();
        if (ruc.length !== 11 || !/^\d+$/.test(ruc)) {
            if (typeof showToast === 'function') {
                showToast('Para Factura Electrónica, el RUC debe tener exactamente 11 dígitos numéricos.', 'warning');
            }
            return;
        }
        if (!company) {
            if (typeof showToast === 'function') {
                showToast('Debe ingresar la Razón Social para Factura.', 'warning');
            }
            return;
        }
    }

    const items = storeCart.map(i => ({
        product_id: i.product_id,
        quantity: i.quantity,
    }));

    const btnSubmit = document.getElementById('btn-submit-store-checkout');
    if (btnSubmit) {
        btnSubmit.disabled = true;
        btnSubmit.textContent = '⏳ Procesando venta...';
    }

    try {
        const ruc = document.getElementById('store-checkout-ruc') ? document.getElementById('store-checkout-ruc').value.trim() : '';
        const company = document.getElementById('store-checkout-company') ? document.getElementById('store-checkout-company').value.trim() : '';

        const result = await apiFetch('/api/store/sales', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Idempotency-Key': crypto.randomUUID(),
            },
            body: JSON.stringify({
                payment_method: paymentMethod,
                doc_type: docType,
                customer_doc: docType === 'FACTURA' ? ruc : '00000000',
                customer_name: docType === 'FACTURA' ? company : 'CLIENTE GENERAL',
                reference: reference,
                items: items,
            }),
        });

        if (result.ok && result.data.status === 'success') {
            closeModal('modal-store-checkout');
            const saleData = result.data.data?.sale;
            const invoiceData = result.data.data?.invoice;
            const voucherUrl = result.data.data?.voucher_url || result.data.data?.pdf_url;

            if (typeof showToast === 'function') {
                showToast(`✅ Venta #${saleData?.id || ''} procesada exitosamente. Comprobante ${invoiceData?.full_number || ''} emitido.`, 'success');
            }
            clearCart();
            loadStoreCatalog();

            // Abrir automáticamente el modal del voucher térmico generado y guardado
            if (voucherUrl) {
                const frame = document.getElementById('store-receipt-pdf-frame');
                if (frame) frame.src = voucherUrl;

                const actionsEl = document.getElementById('store-receipt-actions');
                if (actionsEl) {
                    let actHtml = `<a href="${escapeHtml(voucherUrl)}" target="_blank" class="btn btn-outline-success btn-sm">🖨️ Abrir Voucher</a> `;
                    if (invoiceData?.id) {
                        actHtml += `<button type="button" class="btn btn-warning btn-sm" onclick="triggerEscPosPrint(${invoiceData.id})">⚡ Imprimir ESC/POS</button>`;
                    }
                    actionsEl.innerHTML = actHtml;
                }

                openModal('modal-store-receipt');
            }
        } else {
            const errorMsg = result.data.message || 'Error al procesar la venta.';
            if (typeof showToast === 'function') {
                showToast(`❌ ${errorMsg}`, 'error');
            }
            if (errorMsg.includes('caja')) {
                const alertBox = document.getElementById('store-cashbox-alert');
                if (alertBox) alertBox.style.display = 'block';
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión o servidor al procesar la venta.', 'error');
        }
    } finally {
        if (btnSubmit) {
            btnSubmit.disabled = false;
            btnSubmit.textContent = '💵 Confirmar Pago y Emitir Venta';
        }
    }
}

function triggerEscPosPrint(invoiceId) {
    const printUrl = `/api/invoices/${invoiceId}/escpos?open_drawer=1`;
    const w = window.open(printUrl, '_blank');
    if (w) {
        setTimeout(() => w.close(), 1000);
    }
    if (typeof showToast === 'function') {
        showToast('Enviando comando de impresión a impresora térmica y apertura de gaveta...', 'info');
    }
}

// ─── CHARGE TO ROOM ────────────────────────────────────────

async function openChargeToRoomModal() {
    if (storeCart.length === 0) return;

    selectedChargeRoomId = null;
    document.getElementById('btn-confirm-charge-room').disabled = true;

    const listEl = document.getElementById('room-select-list');
    listEl.innerHTML = '<p class="text-muted text-center">Cargando habitaciones...</p>';

    openModal('modal-charge-room');

    try {
        const result = await apiFetch('/api/rooms');

        if (result.data.status !== 'success' || !Array.isArray(result.data.data)) {
            listEl.innerHTML = '<p class="text-muted text-center">Error al cargar habitaciones.</p>';
            return;
        }

        occupiedRooms = result.data.data.filter(r => r.status === 'occupied' && r.current_stay);

        if (occupiedRooms.length === 0) {
            listEl.innerHTML = '<p class="text-muted text-center">No hay habitaciones ocupadas actualmente.</p>';
            return;
        }

        let html = '';
        occupiedRooms.forEach(room => {
            const stay = room.current_stay;
            const guestName = stay.guest_name || 'Sin Nombre';
            const balance = parseFloat(stay.pending_balance) || 0;

            html += `
                <div class="room-select-option" data-room-id="${room.id}" onclick="selectChargeRoom(${room.id}, this)">
                    <div class="room-info">
                        <span class="room-number">🏨 Hab. ${room.number}</span>
                        <span class="room-guest">${guestName}</span>
                    </div>
                    <span class="room-balance">Saldo: S/ ${balance.toFixed(2)}</span>
                </div>
            `;
        });

        listEl.innerHTML = html;
    } catch (e) {
        listEl.innerHTML = '<p class="text-muted text-center">Error de conexión.</p>';
    }
}

function selectChargeRoom(roomId, el) {
    selectedChargeRoomId = roomId;

    document.querySelectorAll('.room-select-option').forEach(opt => opt.classList.remove('selected'));
    el.classList.add('selected');

    document.getElementById('btn-confirm-charge-room').disabled = false;
}

async function confirmChargeToRoom() {
    if (!selectedChargeRoomId || storeCart.length === 0) return;

    const items = storeCart.map(i => ({
        product_id: i.product_id,
        quantity: i.quantity,
    }));

    try {
        const result = await apiFetch('/api/store/charge-to-room', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                room_id: selectedChargeRoomId,
                items: items,
            }),
        });


        if (result.data.status === 'success') {
            closeModal('modal-charge-room');
            if (typeof showToast === 'function') {
                showToast('✅ Consumo cargado a la habitación exitosamente.', 'success');
            }
            clearCart();
            loadStoreCatalog();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al cargar a la habitación.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión.', 'error');
        }
    }
}

// ─── CREATE PRODUCT ────────────────────────────────────────

async function handleCreateProduct(event) {
    event.preventDefault();

    const name = document.getElementById('product-name').value.trim();
    const sku = document.getElementById('product-sku').value.trim() || null;
    const description = document.getElementById('product-description').value.trim() || null;
    const salePrice = parseFloat(document.getElementById('product-sale-price').value) || 0;
    const costPrice = parseFloat(document.getElementById('product-cost-price').value) || 0;
    const stock = parseInt(document.getElementById('product-stock').value, 10) || 0;
    const minStock = parseInt(document.getElementById('product-min-stock').value, 10) || 5;

    if (!name) {
        if (typeof showToast === 'function') {
            showToast('El nombre del producto es obligatorio.', 'warning');
        }
        return;
    }

    try {
        const result = await apiFetch('/api/products', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                name: name,
                sku: sku,
                description: description,
                sale_price: salePrice,
                cost_price: costPrice,
                stock: stock,
                min_stock: minStock,
            }),
        });


        if (result.data.status === 'success') {
            closeModal('modal-create-product');
            document.getElementById('form-create-product').reset();
            if (typeof showToast === 'function') {
                showToast(`✅ Producto "${name}" registrado exitosamente.`, 'success');
            }
            loadStoreCatalog();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al crear el producto.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión al crear el producto.', 'error');
        }
    }
}

// ─── UTILITIES ─────────────────────────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
