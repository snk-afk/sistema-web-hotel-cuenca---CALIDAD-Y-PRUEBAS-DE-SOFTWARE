/**
 * CS-Hotel: Folios / Estados de Cuenta Frontend Logic
 */

let activeFolioTab = 'all';
let foliosData = [];

document.addEventListener('DOMContentLoaded', () => {
    loadFoliosData();
    window.addEventListener('popstate', () => {
        applyUrlParams();
    });
});

async function loadFoliosData() {
    try {
        const result = await apiFetch('/api/rooms');

        if (result.data.status !== 'success' || !Array.isArray(result.data.data)) {
            renderEmptyTable('Error al cargar los datos.');
            return;
        }

        foliosData = [];
        const rooms = result.data.data;

        rooms.forEach(room => {
            if (room.status === 'occupied' && room.current_stay) {
                const stay = room.current_stay;
                foliosData.push({
                    folio_id: stay.id,
                    stay_id: stay.id,
                    room_number: room.number,
                    category_name: room.category_name || room.category || 'General',
                    guest_name: stay.guest_name || 'Sin Nombre',
                    guest_doc: stay.guest_doc || '---',
                    status: 'open',
                    total_charges: parseFloat(stay.total_charges) || 0,
                    total_payments: parseFloat(stay.total_payments) || 0,
                    pending_balance: parseFloat(stay.pending_balance) || 0,
                    charges: stay.charges || [],
                    payments: stay.payments || [],
                    check_in_at: stay.check_in_at || '',
                });
            }
        });

        updateMetrics();
        renderFoliosTable();
        applyUrlParams();
    } catch (e) {
        renderEmptyTable('Error de conexión al cargar folios.');
    }
}

function updateMetrics() {
    const openFolios = foliosData.filter(f => f.status === 'open');
    const totalCharges = openFolios.reduce((sum, f) => sum + f.total_charges, 0);
    const totalPayments = openFolios.reduce((sum, f) => sum + f.total_payments, 0);
    const totalPending = openFolios.reduce((sum, f) => sum + f.pending_balance, 0);

    document.getElementById('metric-folios-open').textContent = openFolios.length;
    document.getElementById('metric-total-charges').textContent = `S/ ${totalCharges.toFixed(2)}`;
    document.getElementById('metric-total-payments').textContent = `S/ ${totalPayments.toFixed(2)}`;
    document.getElementById('metric-pending-balance').textContent = `S/ ${totalPending.toFixed(2)}`;

    const allCount = foliosData.length;
    const openCount = openFolios.length;
    const closedCount = foliosData.filter(f => f.status === 'closed').length;

    document.getElementById('tab-count-all').textContent = allCount;
    document.getElementById('tab-count-open').textContent = openCount;
    document.getElementById('tab-count-closed').textContent = closedCount;
}

function renderFoliosTable() {
    const tbody = document.getElementById('table-folios-body');

    if (foliosData.length === 0) {
        renderEmptyTable('No hay folios registrados actualmente.');
        return;
    }

    let html = '';
    foliosData.forEach(folio => {
        const statusBadge = folio.status === 'open'
            ? '<span class="folio-status-open">📂 Abierto</span>'
            : '<span class="folio-status-closed">🔒 Cerrado</span>';

        const balanceClass = folio.pending_balance > 0 ? 'text-danger' : 'text-success';
        const balanceText = folio.pending_balance > 0
            ? `S/ ${folio.pending_balance.toFixed(2)}`
            : `S/ 0.00 ✅`;

        const searchStr = `${folio.folio_id} ${folio.room_number} ${folio.guest_name} ${folio.guest_doc} ${folio.category_name}`.toLowerCase();

        html += `
            <tr data-status="${folio.status}" data-search="${searchStr}" data-stay-id="${folio.stay_id}">
                <td><strong>#${folio.folio_id}</strong></td>
                <td>Hab. ${folio.room_number} <small class="text-muted">(${folio.category_name})</small></td>
                <td>${folio.guest_name}</td>
                <td>${statusBadge}</td>
                <td>S/ ${folio.total_charges.toFixed(2)}</td>
                <td>S/ ${folio.total_payments.toFixed(2)}</td>
                <td><strong class="${balanceClass}">${balanceText}</strong></td>
                <td>
                    <button class="btn btn-sm btn-outline-info" onclick="openFolioDetailModal(${folio.stay_id})">📋 Ver Detalle</button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    filterFoliosTable(false);
}

function renderEmptyTable(message) {
    document.getElementById('table-folios-body').innerHTML =
        `<tr class="empty-row"><td colspan="8" class="text-center text-muted py-3">${message}</td></tr>`;
}

function filterFoliosByTab(status, tabBtn, updateUrl = true) {
    activeFolioTab = status;

    const allButtons = document.querySelectorAll('.folios-tabs-bar .tab-btn');
    allButtons.forEach(btn => btn.classList.remove('active'));

    if (tabBtn) {
        tabBtn.classList.add('active');
    }

    filterFoliosTable(updateUrl);
}

function filterFoliosTable(updateUrl = true) {
    const searchInput = document.getElementById('filter-folios-input');
    const term = searchInput ? searchInput.value.toLowerCase().trim() : '';

    const rows = document.querySelectorAll('#table-folios-body tr');
    rows.forEach(row => {
        if (row.classList.contains('empty-row')) return;

        const rowStatus = row.getAttribute('data-status');
        const rowSearch = row.getAttribute('data-search') || '';

        const matchesTab = activeFolioTab === 'all' || rowStatus === activeFolioTab;
        const matchesSearch = term === '' || rowSearch.includes(term);

        row.style.display = (matchesTab && matchesSearch) ? '' : 'none';
    });

    if (updateUrl) {
        updateFolioUrlParams();
    }
}

function updateFolioUrlParams() {
    const url = new URL(window.location.href);

    if (activeFolioTab && activeFolioTab !== 'all') {
        url.searchParams.set('status', activeFolioTab);
    } else {
        url.searchParams.delete('status');
    }

    const searchInput = document.getElementById('filter-folios-input');
    const term = searchInput ? searchInput.value.trim() : '';
    if (term) {
        url.searchParams.set('search', term);
    } else {
        url.searchParams.delete('search');
    }

    window.history.replaceState({}, '', url.toString());
}

function clearFolioUrlParams() {
    const url = new URL(window.location.href);
    url.searchParams.delete('stay_id');
    window.history.replaceState({}, '', url.toString());
}

function applyUrlParams() {
    const params = new URLSearchParams(window.location.search);

    // Status tab
    const statusParam = params.get('status');
    if (statusParam) {
        const matchingBtn = document.querySelector(`.folios-tabs-bar .tab-btn[data-status="${statusParam}"]`);
        if (matchingBtn) {
            filterFoliosByTab(statusParam, matchingBtn, false);
        }
    }

    // Search
    const searchParam = params.get('search');
    const searchInput = document.getElementById('filter-folios-input');
    if (searchParam !== null && searchInput) {
        searchInput.value = searchParam;
        filterFoliosTable(false);
    }

    // Deep-link stay_id
    const stayIdParam = params.get('stay_id');
    if (stayIdParam) {
        const targetId = parseInt(stayIdParam, 10);
        if (!isNaN(targetId)) {
            openFolioDetailModal(targetId, false);
        }
    }
}

function openFolioDetailModal(stayId, updateUrl = true) {
    const folio = foliosData.find(f => f.stay_id === stayId);

    if (!folio) {
        if (typeof showToast === 'function') {
            showToast(`Folio para estancia #${stayId} no encontrado.`, 'warning');
        }
        return;
    }

    if (updateUrl) {
        const url = new URL(window.location.href);
        url.searchParams.set('stay_id', stayId);
        window.history.replaceState({}, '', url.toString());
    }

    // Title
    document.getElementById('folio-detail-title').textContent =
        `📋 Folio #${folio.folio_id} — Hab. ${folio.room_number} — ${folio.guest_name}`;

    // Summary
    document.getElementById('folio-detail-charges').textContent = `S/ ${folio.total_charges.toFixed(2)}`;
    document.getElementById('folio-detail-payments').textContent = `S/ ${folio.total_payments.toFixed(2)}`;

    const balanceEl = document.getElementById('folio-detail-balance');
    balanceEl.textContent = folio.pending_balance > 0
        ? `S/ ${folio.pending_balance.toFixed(2)}`
        : 'S/ 0.00 ✅ Pagado';
    balanceEl.className = folio.pending_balance > 0
        ? 'summary-value text-balance-positive'
        : 'summary-value text-balance-zero';

    // Charges table
    const chargesBody = document.getElementById('folio-charges-body');
    if (folio.charges.length === 0) {
        chargesBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sin cargos registrados.</td></tr>';
    } else {
        const chargeTypeLabels = {
            'accommodation': '🏨 Alojamiento',
            'extension': '⏰ Extensión',
            'store_item': '🛒 Consumo',
            'service': '🛎️ Servicio',
            'penalty': '⚠️ Penalidad',
            'adjustment': '🔄 Ajuste',
        };

        let chargesHtml = '';
        folio.charges.forEach(c => {
            const typeLabel = chargeTypeLabels[c.charge_type] || c.charge_type;
            const amount = parseFloat(c.total_amount);
            const amountClass = amount < 0 ? 'text-danger' : '';
            const reversalBadge = amount < 0 ? ' <span class="badge-reversal">REVERSO</span>' : '';

            chargesHtml += `
                <tr>
                    <td>${typeLabel}${reversalBadge}</td>
                    <td>${c.description}</td>
                    <td>${c.quantity}</td>
                    <td>S/ ${parseFloat(c.unit_price).toFixed(2)}</td>
                    <td class="${amountClass}"><strong>S/ ${amount.toFixed(2)}</strong></td>
                </tr>
            `;
        });
        chargesBody.innerHTML = chargesHtml;
    }

    // Payments table
    const paymentsBody = document.getElementById('folio-payments-body');
    if (folio.payments.length === 0) {
        paymentsBody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">Sin abonos registrados.</td></tr>';
    } else {
        const paymentTypeLabels = {
            'advance': '💵 Adelanto',
            'partial': '💳 Pago Parcial',
            'final': '✅ Pago Final',
            'settlement': '🏦 Liquidación',
        };

        const methodLabels = {
            'cash': '💵 Efectivo',
            'yape': '🟣 Yape',
            'plin': '🔵 Plin',
            'card': '💳 Tarjeta',
            'transfer': '🏦 Transferencia',
        };

        let paymentsHtml = '';
        folio.payments.forEach(p => {
            const typeLabel = paymentTypeLabels[p.payment_type] || p.payment_type;
            const methodLabel = methodLabels[p.payment_method] || p.payment_method;

            paymentsHtml += `
                <tr>
                    <td>${typeLabel}</td>
                    <td><strong class="text-success">S/ ${parseFloat(p.amount).toFixed(2)}</strong></td>
                    <td>${methodLabel}</td>
                    <td>${p.reference_number || '—'}</td>
                    <td>${p.created_at || '—'}</td>
                </tr>
            `;
        });
        paymentsBody.innerHTML = paymentsHtml;
    }

    openModal('modal-folio-detail');
}
