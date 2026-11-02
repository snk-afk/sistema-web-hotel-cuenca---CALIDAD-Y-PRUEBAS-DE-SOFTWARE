async function loadDrawerPOSProducts() {
    const container = document.getElementById('drawer-products-container');
    if (!container) return;

    try {
        const res = await apiFetch('/api/products');
        if (res.ok && res.data && res.data.data && res.data.data.length > 0) {
            container.innerHTML = res.data.data.map(p => `
                <div class="ns-room-modal__product-card ns-drawer__product-card">
                    <div class="ns-room-modal__product-info ns-drawer__product-info">
                        <span class="ns-room-modal__product-icon ns-drawer__product-icon">📦</span>
                        <div>
                            <h5 class="ns-room-modal__product-name ns-drawer__product-name">${p.name}</h5>
                            <span class="ns-room-modal__product-stock ns-drawer__product-stock">Stock: ${p.stock}</span>
                        </div>
                    </div>
                    <div class="ns-room-modal__product-footer ns-drawer__product-footer">
                        <div class="ns-room-modal__product-price ns-drawer__product-price">S/ ${parseFloat(p.sale_price).toFixed(2)}</div>
                        <button class="ns-room-modal__btn ns-room-modal__btn--primary" style="width: auto; padding: 6px 14px; font-size: 0.82rem;" onclick="handleDrawerAddPOSProduct(${p.id}, '${p.name.replace(/'/g, "\\'")}', ${p.sale_price})">➕ Cargar</button>
                    </div>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="text-muted">No hay productos disponibles en el catálogo de tienda.</p>';
        }
    } catch (e) {
        container.innerHTML = '<p class="text-muted">Error al cargar el catálogo de tienda.</p>';
    }
}

async function handleDrawerAddPOSProduct(productId, productName, salePrice) {
    if (typeof currentDrawerRoomId === 'undefined' || !currentDrawerRoomId) {
        showToast('Abra una habitación ocupada para cargar consumos', 'warning');
        return;
    }

    try {
        const res = await apiFetch('/api/store/charge-to-room', {
            method: 'POST',
            body: JSON.stringify({
                room_id: currentDrawerRoomId,
                items: [{ product_id: productId, quantity: 1 }],
            })
        });

        if (res.ok) {
            showToast(`✅ Cargado a Habitación: ${productName} (S/ ${parseFloat(salePrice).toFixed(2)})`);
            if (typeof loadDrawerFolioData === 'function') {
                loadDrawerFolioData(currentDrawerRoomId);
            }
        } else {
            showToast(res.data.message || 'Error al cargar producto a la habitación', 'error');
        }
    } catch (e) {
        showToast('Error de comunicación al registrar consumo', 'error');
    }
}
