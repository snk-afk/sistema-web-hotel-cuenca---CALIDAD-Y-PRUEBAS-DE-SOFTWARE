/**
 * CS-Hotel: Reservations Management Frontend Logic
 */

let activeReservationTab = 'all';

document.addEventListener('DOMContentLoaded', () => {
    initFromUrlParams();
    window.addEventListener('popstate', () => {
        initFromUrlParams();
    });
});

function initFromUrlParams() {
    const params = new URLSearchParams(window.location.search);

    // 1. Status / Tab filter
    const statusParam = params.get('status') || params.get('tab');
    if (statusParam) {
        const matchingTabBtn = document.querySelector(`.reservations-tabs-bar .tab-btn[data-status="${statusParam}"], .reservations-tabs-bar .tab-btn[onclick*="'${statusParam}'"]`);
        if (matchingTabBtn) {
            filterByTab(statusParam, matchingTabBtn, false);
        }
    } else {
        const allTabBtn = document.querySelector('.reservations-tabs-bar .tab-btn[data-status="all"]');
        if (allTabBtn && activeReservationTab !== 'all') {
            filterByTab('all', allTabBtn, false);
        }
    }

    // 2. Search term
    const searchParam = params.get('search');
    const searchInput = document.getElementById('filter-reservations-input');
    if (searchParam !== null) {
        if (searchInput) {
            searchInput.value = searchParam;
            filterReservationsTable(false);
        }
    } else if (searchInput && searchInput.value !== '') {
        searchInput.value = '';
        filterReservationsTable(false);
    }

    // 3. Direct Reservation Detail View (?id= or ?res_id=)
    const idParam = params.get('id') || params.get('res_id');
    if (idParam) {
        const targetId = parseInt(idParam, 10);
        let targetRes = null;
        if (window.allReservationsCatalog && Array.isArray(window.allReservationsCatalog)) {
            targetRes = window.allReservationsCatalog.find(r => r.id === targetId);
        }

        if (targetRes) {
            openViewReservationModal(targetRes, false);
        } else {
            // Fallback API lookup
            fetch('/api/reservations')
                .then(r => r.json())
                .then(data => {
                    if (result.data.status === 'success' && Array.isArray(result.data.data)) {
                        const found = result.data.data.find(r => r.id === targetId);
                        if (found) {
                            openViewReservationModal(found, false);
                        } else if (typeof showToast === 'function') {
                            showToast(`Reserva #${targetId} no encontrada en el catálogo.`, 'warning');
                        }
                    }
                })
                .catch(() => {});
        }
    }
}

function updateUrlParams() {
    const url = new URL(window.location.href);
    if (activeReservationTab && activeReservationTab !== 'all') {
        url.searchParams.set('status', activeReservationTab);
    } else {
        url.searchParams.delete('status');
        url.searchParams.delete('tab');
    }

    const searchInput = document.getElementById('filter-reservations-input');
    const term = searchInput ? searchInput.value.trim() : '';
    if (term) {
        url.searchParams.set('search', term);
    } else {
        url.searchParams.delete('search');
    }

    window.history.replaceState({}, '', url.toString());
}

function clearModalUrlParams() {
    const url = new URL(window.location.href);
    url.searchParams.delete('id');
    url.searchParams.delete('res_id');
    url.searchParams.delete('action');
    url.searchParams.delete('room_id');
    url.searchParams.delete('guest_id');
    window.history.replaceState({}, '', url.toString());
}

function filterByTab(status, tabBtn, updateUrl = true) {
    activeReservationTab = status;

    const allButtons = document.querySelectorAll('.reservations-tabs-bar .tab-btn');
    allButtons.forEach(btn => btn.classList.remove('active'));

    if (tabBtn) {
        tabBtn.classList.add('active');
    }

    filterReservationsTable(updateUrl);
}

function filterReservationsTable(updateUrl = true) {
    const searchInput = document.getElementById('filter-reservations-input');
    const term = searchInput ? searchInput.value.toLowerCase().trim() : '';

    const rows = document.querySelectorAll('#table-reservations-body tr');
    rows.forEach(row => {
        if (row.classList.contains('empty-row')) return;

        const rowStatus = row.getAttribute('data-status');
        const rowSearch = row.getAttribute('data-search') || '';

        const matchesTab = activeReservationTab === 'all' || rowStatus === activeReservationTab;
        const matchesSearch = term === '' || rowSearch.includes(term);

        if (matchesTab && matchesSearch) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });

    if (updateUrl) {
        updateUrlParams();
    }
}

function openViewReservationModal(res, updateUrl = true) {
    if (updateUrl && res && res.id) {
        const url = new URL(window.location.href);
        url.searchParams.set('id', res.id);
        window.history.replaceState({}, '', url.toString());
    }

    document.getElementById('view-res-id').value = res.id;
    document.getElementById('view-res-room-number').value = res.room_number;
    document.getElementById('view-res-title').textContent = `📜 Detalle de Reserva #${res.id} - Hab. ${res.room_number}`;

    const statusMap = {
        'pending': '<span class="reservation-status-badge status-badge-pending">⏳ Pendiente</span>',
        'confirmed': '<span class="reservation-status-badge status-badge-confirmed">✅ Confirmada</span>',
        'checked_in': '<span class="reservation-status-badge status-badge-checked_in">🏨 Hospedado</span>',
        'cancelled': '<span class="reservation-status-badge status-badge-cancelled">❌ Cancelada</span>',
        'no_show': '<span class="reservation-status-badge status-badge-no_show">🚫 No-show</span>',
        'pending_refund': '<span class="reservation-status-badge status-badge-pending_refund">💸 Devolución pendiente</span>',
    };

    document.getElementById('view-res-status-badge').innerHTML = statusMap[res.status] || res.status;
    document.getElementById('view-res-advance').textContent = parseFloat(res.advance_payment) > 0 ? `S/ ${parseFloat(res.advance_payment).toFixed(2)}` : 'S/ 0.00 (Sin adelanto)';

    document.getElementById('view-res-guest-name').textContent = res.guest_name;
    document.getElementById('view-res-guest-doc').textContent = `${res.guest_document_type}: ${res.guest_document_number}`;
    document.getElementById('view-res-guest-phone').textContent = res.guest_phone ? `📞 ${res.guest_phone}` : '';

    document.getElementById('view-res-room').textContent = `Habitación ${res.room_number} - ${res.category_name} (Piso ${res.room_floor})`;
    document.getElementById('view-res-tariff').textContent = `Tarifa: ${res.tariff_name} - S/ ${parseFloat(res.tariff_price).toFixed(2)} (${res.tariff_duration_hours} horas)`;

    document.getElementById('view-res-checkin').textContent = res.reserved_check_in_at;
    document.getElementById('view-res-checkout').textContent = res.reserved_check_out_at;

    const notesContainer = document.getElementById('view-res-notes-container');
    const notesText = document.getElementById('view-res-notes');
    if (res.notes && res.notes.trim() !== '') {
        notesText.textContent = `"${res.notes}"`;
        notesContainer.style.display = 'block';
    } else {
        notesContainer.style.display = 'none';
    }

    const actionsContainer = document.getElementById('view-res-actions');
    if (['pending', 'confirmed'].includes(res.status)) {
        actionsContainer.style.display = 'flex';
        document.getElementById('btn-modal-no-show-res').style.display = 'inline-flex';
    } else {
        actionsContainer.style.display = 'none';
        document.getElementById('btn-modal-no-show-res').style.display = 'none';
    }

    openModal('modal-view-reservation');
}

async function quickCheckinReservation(reservationId, roomNumber) {
    if (!confirm(`🏨 ¿Deseas ejecutar el Check-In inmediato de la reserva #${reservationId} para la Habitación ${roomNumber}?`)) {
        return;
    }

    try {
        const result = await apiFetch('/api/reservations', {
            method: 'POST',
            body: JSON.stringify({
                action: 'checkin',
                reservation_id: reservationId,
            })
        });

        if (!result.ok || result.data.status !== 'success') {
            showToast(result.data.message || 'No se pudo realizar el check-in de la reserva.', 'error');
            return;
        }

        showToast('🏨 Check-in completado exitosamente. Habitación asignada a Ocupada.', 'success');
        setTimeout(() => {
            window.location.href = '/'; // Redirect to Dashboard room grid
        }, 700);

    } catch (err) {
        console.error('Error during reservation checkin:', err);
        showToast('Error al comunicar con el servidor.', 'error');
    }
}

async function quickCancelReservation(reservationId) {
    if (!confirm(`❌ ¿Estás seguro de cancelar la reserva #${reservationId}? Esta acción liberará el espacio en la habitación.`)) {
        return;
    }

    const reason = prompt('Ingrese el motivo de la cancelación:');
    if (!reason || reason.trim() === '') {
        showToast('El motivo de cancelación es obligatorio.', 'warning');
        return;
    }

    try {
        const result = await apiFetch('/api/reservations', {
            method: 'POST',
            body: JSON.stringify({
                action: 'cancel',
                reservation_id: reservationId,
                reason: reason.trim()
            })
        });

        if (!result.ok || result.data.status !== 'success') {
            showToast(result.data.message || 'No se pudo cancelar la reserva.', 'error');
            return;
        }

        const settlement = result.data.data || {};
        const refund_message = settlement.refund_amount > 0
            ? ` Devolución pendiente: S/ ${Number(settlement.refund_amount).toFixed(2)}; penalidad: S/ ${Number(settlement.penalty_amount).toFixed(2)}.`
            : ' Sin devolución pendiente.';
        showToast(`Reserva cancelada correctamente.${refund_message}`, 'success');
        setTimeout(() => {
            window.location.reload();
        }, 600);

    } catch (err) {
        console.error('Error during reservation cancellation:', err);
        showToast('Error al comunicar con el servidor.', 'error');
    }
}

async function quickNoShowReservation(reservationId) {
    if (!confirm(`🚫 ¿Registrar no-show para la reserva #${reservationId}?`)) {
        return;
    }

    const reason = prompt('Ingrese el motivo del no-show:');
    if (!reason || reason.trim() === '') {
        showToast('El motivo de no-show es obligatorio.', 'warning');
        return;
    }

    try {
        const result = await apiFetch('/api/reservations', {
            method: 'POST',
            body: JSON.stringify({
                action: 'no_show',
                reservation_id: reservationId,
                reason: reason.trim()
            })
        });

        if (!result.ok || result.data.status !== 'success') {
            showToast(result.data.message || 'No se pudo registrar el no-show.', 'error');
            return;
        }

        showToast('No-show registrado. La reserva queda penalizada sin devolución.', 'success');
        setTimeout(() => window.location.reload(), 600);
    } catch (err) {
        console.error('Error during reservation no-show:', err);
        showToast('Error al comunicar con el servidor.', 'error');
    }
}

function executeModalCheckinReservation() {
    const resId = parseInt(document.getElementById('view-res-id').value, 10);
    const roomNumber = document.getElementById('view-res-room-number').value;
    if (resId) {
        quickCheckinReservation(resId, roomNumber);
    }
}

function executeModalCancelReservation() {
    const resId = parseInt(document.getElementById('view-res-id').value, 10);
    if (resId) {
        quickCancelReservation(resId);
    }
}

function executeModalNoShowReservation() {
    const resId = parseInt(document.getElementById('view-res-id').value, 10);
    if (resId) {
        quickNoShowReservation(resId);
    }
}
