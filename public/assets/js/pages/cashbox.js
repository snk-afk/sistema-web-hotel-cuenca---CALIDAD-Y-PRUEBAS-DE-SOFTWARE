/**
 * CS-Hotel: Cashbox Management Frontend Logic
 * Shifts, Movements, Live Arqueo & Reconciliation
 */

let currentCashboxShift = null;

document.addEventListener('DOMContentLoaded', () => {
    loadCashboxShift();
    loadCashboxHistory();
});

// ─── LOAD CURRENT SHIFT ────────────────────────────────────

async function loadCashboxShift() {
    try {
        const result = await apiFetch('/api/cashbox/current');

        if (result.data.status === 'success' && result.data.data) {
            currentCashboxShift = result.data.data;
            renderActiveShift();
        } else {
            currentCashboxShift = null;
            renderNoShift();
        }
    } catch (e) {
        console.error('Error cargando turno de caja:', e);
        renderNoShift();
    }
}

function renderNoShift() {
    const noShiftCont = document.getElementById('cashbox-no-shift-container');
    const activeCont = document.getElementById('cashbox-active-shift-container');
    const headerActions = document.getElementById('cashbox-header-actions');

    if (noShiftCont) noShiftCont.style.display = 'block';
    if (activeCont) activeCont.style.display = 'none';
    if (headerActions) headerActions.style.display = 'none';
}

function renderActiveShift() {
    const noShiftCont = document.getElementById('cashbox-no-shift-container');
    const activeCont = document.getElementById('cashbox-active-shift-container');
    const headerActions = document.getElementById('cashbox-header-actions');

    if (noShiftCont) noShiftCont.style.display = 'none';
    if (activeCont) activeCont.style.display = 'block';
    if (headerActions) headerActions.style.display = 'flex';

    const s = currentCashboxShift;
    const initialCash = parseFloat(s.initial_cash) || 0;
    const expectedCash = parseFloat(s.expected_cash) || 0;
    const movements = s.movements || [];

    // Calculate Incomes & Expenses
    let totalIncomes = 0;
    let totalExpenses = 0;
    let totalDigital = 0;

    movements.forEach(m => {
        const amt = parseFloat(m.amount) || 0;
        if (m.type === 'income') {
            totalIncomes += amt;
            if (m.payment_method !== 'cash') {
                totalDigital += amt;
            }
        } else if (m.type === 'expense') {
            totalExpenses += amt;
        }
    });

    // Update Metrics
    document.getElementById('metric-initial-cash').textContent = `S/ ${initialCash.toFixed(2)}`;
    document.getElementById('metric-total-incomes').textContent = `S/ ${totalIncomes.toFixed(2)}`;
    document.getElementById('metric-total-expenses').textContent = `S/ ${totalExpenses.toFixed(2)}`;
    document.getElementById('metric-expected-cash').textContent = `S/ ${expectedCash.toFixed(2)}`;

    // Shift Info Details
    document.getElementById('shift-id-display').textContent = s.id;
    document.getElementById('shift-opened-at-display').textContent = s.opened_at || '---';
    document.getElementById('shift-movements-count').textContent = `${movements.length} registrados`;
    document.getElementById('shift-digital-payments').textContent = `S/ ${totalDigital.toFixed(2)}`;

    // Movements Table
    const tbody = document.getElementById('table-cashbox-movements-body');
    if (movements.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay movimientos registrados en este turno aún.</td></tr>';
        return;
    }

    let rowsHtml = '';
    movements.forEach((m, idx) => {
        const isIncome = m.type === 'income';
        const typeBadge = isIncome
            ? '<span class="badge-income">💰 Ingreso (+)</span>'
            : '<span class="badge-expense">💸 Egreso (−)</span>';

        const amountClass = isIncome ? 'text-success' : 'text-danger';
        const sign = isIncome ? '+' : '−';
        const amt = parseFloat(m.amount) || 0;

        const methodLabels = {
            'cash': '💵 Efectivo',
            'yape': '🟣 Yape',
            'plin': '🔵 Plin',
            'card': '💳 Tarjeta',
            'transfer': '🏦 Transferencia',
        };
        const methodLabel = methodLabels[m.payment_method] || m.payment_method;

        rowsHtml += `
            <tr>
                <td><strong>#${m.id || (idx + 1)}</strong></td>
                <td>${m.created_at || '---'}</td>
                <td>${typeBadge}</td>
                <td>${escapeHtml(m.concept || 'Movimiento de caja')}</td>
                <td>${methodLabel}</td>
                <td class="${amountClass}"><strong>${sign} S/ ${amt.toFixed(2)}</strong></td>
            </tr>
        `;
    });

    tbody.innerHTML = rowsHtml;
}

// ─── OPEN SHIFT ────────────────────────────────────────────

async function handleOpenShiftSubmit(event) {
    event.preventDefault();

    const initialCash = parseFloat(document.getElementById('open-initial-cash').value) || 0;
    const notes = document.getElementById('open-shift-notes').value.trim() || null;

    const btn = document.getElementById('btn-submit-open-shift');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Aperturando turno...';
    }

    try {
        const result = await apiFetch('/api/cashbox/open', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                initial_cash: initialCash,
                notes: notes,
            }),
        });


        if (result.ok && result.data.status === 'success') {
            if (typeof showToast === 'function') {
                showToast('✅ Turno de caja aperturado exitosamente.', 'success');
            }
            loadCashboxShift();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al abrir el turno.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión al abrir turno.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '🔓 Abrir Turno de Caja Chica';
        }
    }
}

// ─── NEW EXTRAORDINARY MOVEMENT ────────────────────────────

function updateMovementCategories() {
    const type = document.getElementById('movement-type').value;
    const catSelect = document.getElementById('movement-category');

    if (type === 'income') {
        catSelect.innerHTML = `
            <option value="petty_deposit">Aporte Extraordinario / Sencillo</option>
            <option value="other_income">Otro Ingreso</option>
        `;
    } else {
        catSelect.innerHTML = `
            <option value="petty_expense">Gasto Menor / Insumos Urgentes</option>
            <option value="delivery_taxi">Movilidad / Envíos</option>
            <option value="services">Pago de Servicios</option>
            <option value="other">Otro Egreso</option>
        `;
    }
}

async function handleNewMovementSubmit(event) {
    event.preventDefault();

    const type = document.getElementById('movement-type').value;
    const category = document.getElementById('movement-category').value;
    const concept = document.getElementById('movement-concept').value.trim();
    const amount = parseFloat(document.getElementById('movement-amount').value) || 0;
    const paymentMethod = document.getElementById('movement-payment-method').value;

    if (!concept || amount <= 0) {
        if (typeof showToast === 'function') {
            showToast('Ingrese un concepto válido y un monto mayor a 0.', 'warning');
        }
        return;
    }

    const btn = document.getElementById('btn-submit-movement');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Registrando...';
    }

    try {
        const result = await apiFetch('/api/cashbox/movement', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: type,
                category: category,
                concept: concept,
                amount: amount,
                payment_method: paymentMethod,
            }),
        });


        if (result.ok && result.data.status === 'success') {
            closeModal('modal-new-movement');
            document.getElementById('form-new-movement').reset();
            if (typeof showToast === 'function') {
                showToast('✅ Movimiento registrado exitosamente.', 'success');
            }
            loadCashboxShift();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al registrar movimiento.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '💾 Registrar Movimiento';
        }
    }
}

// ─── CLOSE SHIFT & ARQUEO ──────────────────────────────────

function openCloseShiftModal() {
    if (!currentCashboxShift) return;

    const initialCash = parseFloat(currentCashboxShift.initial_cash) || 0;
    const expectedCash = parseFloat(currentCashboxShift.expected_cash) || 0;

    document.getElementById('arqueo-initial-cash').textContent = `S/ ${initialCash.toFixed(2)}`;
    document.getElementById('arqueo-expected-cash').textContent = `S/ ${expectedCash.toFixed(2)}`;

    const declaredInput = document.getElementById('close-real-cash-declared');
    declaredInput.value = expectedCash.toFixed(2);
    document.getElementById('close-real-yape-declared').value = (parseFloat(currentCashboxShift.expected_yape) || 0).toFixed(2);
    document.getElementById('close-real-plin-declared').value = (parseFloat(currentCashboxShift.expected_plin) || 0).toFixed(2);
    document.getElementById('close-real-card-declared').value = (parseFloat(currentCashboxShift.expected_card) || 0).toFixed(2);
    document.getElementById('close-real-transfer-declared').value = (parseFloat(currentCashboxShift.expected_transfer) || 0).toFixed(2);

    calculateArqueoDifference();
    openModal('modal-close-shift');
}

function calculateArqueoDifference() {
    if (!currentCashboxShift) return;

    const expectedCash = parseFloat(currentCashboxShift.expected_cash) || 0;
    const declaredInput = document.getElementById('close-real-cash-declared');
    const declaredCash = parseFloat(declaredInput.value) || 0;

    const diff = declaredCash - expectedCash;
    const displayBox = document.getElementById('arqueo-diff-display');

    if (Math.abs(diff) < 0.01) {
        displayBox.className = 'arqueo-difference-box balanced';
        displayBox.textContent = '✅ DIFERENCIA: S/ 0.00 (CUADRADO PERFECTO)';
    } else if (diff < 0) {
        displayBox.className = 'arqueo-difference-box shortage';
        displayBox.textContent = `❌ DIFERENCIA: - S/ ${Math.abs(diff).toFixed(2)} (FALTANTE DE CAJA)`;
    } else {
        displayBox.className = 'arqueo-difference-box surplus';
        displayBox.textContent = `⚠️ DIFERENCIA: + S/ ${diff.toFixed(2)} (SOBRANTE DE CAJA)`;
    }
}

async function handleCloseShiftModalSubmit(event) {
    event.preventDefault();

    const declaredCash = parseFloat(document.getElementById('close-real-cash-declared').value) || 0;
    const declaredYape = parseFloat(document.getElementById('close-real-yape-declared').value) || 0;
    const declaredPlin = parseFloat(document.getElementById('close-real-plin-declared').value) || 0;
    const declaredCard = parseFloat(document.getElementById('close-real-card-declared').value) || 0;
    const declaredTransfer = parseFloat(document.getElementById('close-real-transfer-declared').value) || 0;
    const notes = document.getElementById('close-shift-notes').value.trim() || null;

    const btn = document.getElementById('btn-submit-close-shift');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Cerrando turno...';
    }

    try {
        const result = await apiFetch('/api/cashbox/close', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                real_cash_declared: declaredCash,
                real_yape_declared: declaredYape,
                real_plin_declared: declaredPlin,
                real_card_declared: declaredCard,
                real_transfer_declared: declaredTransfer,
                notes: notes,
            }),
        });


        if (result.ok && result.data.status === 'success') {
            closeModal('modal-close-shift');
            const shiftResult = result.data.data;
            const balanceMsg = shiftResult.balance_status === 'balanced'
                ? 'Turno cerrado y CUADRADO exitosamente.'
                : `Turno cerrado con ${shiftResult.balance_status === 'shortage' ? 'FALTANTE' : 'SOBRANTE'} de S/ ${Math.abs(shiftResult.difference).toFixed(2)}.`;

            if (typeof showToast === 'function') {
                showToast(`🔒 ${balanceMsg}`, 'success');
            }
            loadCashboxShift();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al cerrar el turno.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión al cerrar turno.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '🔒 Confirmar Cierre y Sellado de Turno';
        }
    }
}

// ─── LOAD SHIFTS HISTORY (SPEC-0040) ──────────────────────

async function loadCashboxHistory() {
    const tbody = document.getElementById('table-cashbox-history-body');
    if (!tbody) return;

    const status = document.getElementById('history-filter-status') ? document.getElementById('history-filter-status').value : '';
    const userId = document.getElementById('history-filter-user-id') ? document.getElementById('history-filter-user-id').value.trim() : '';
    const from = document.getElementById('history-filter-from') ? document.getElementById('history-filter-from').value : '';
    const to = document.getElementById('history-filter-to') ? document.getElementById('history-filter-to').value : '';

    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (userId) params.append('user_id', userId);
    if (from) params.append('from', from);
    if (to) params.append('to', to);

    const queryString = params.toString() ? `?${params.toString()}` : '';

    try {
        const result = await apiFetch(`/api/cashbox/shifts${queryString}`);
        if (result.ok && result.data.status === 'success') {
            renderShiftsHistory(result.data.data || []);
        } else {
            tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-3">Error al cargar historial.</td></tr>';
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-danger py-3">Error de conexión al obtener historial.</td></tr>';
    }
}

function renderShiftsHistory(shifts) {
    const tbody = document.getElementById('table-cashbox-history-body');
    if (!tbody) return;

    if (shifts.length === 0) {
        tbody.innerHTML = '<tr><td colspan="11" class="text-center text-muted py-3">No hay turnos registrados que coincidan con los filtros.</td></tr>';
        return;
    }

    let rowsHtml = '';
    shifts.forEach(s => {
        const isOpen = s.status === 'open';
        const statusBadge = isOpen
            ? '<span class="badge badge-success">🔓 Abierto</span>'
            : '<span class="badge badge-secondary">🔒 Cerrado</span>';

        let arqueoBadge = '<span class="text-muted">---</span>';
        if (!isOpen) {
            if (s.balance_status === 'balanced') {
                arqueoBadge = '<span class="badge badge-success">✅ Cuadrado</span>';
            } else if (s.balance_status === 'shortage') {
                arqueoBadge = '<span class="badge badge-danger">❌ Faltante</span>';
            } else if (s.balance_status === 'surplus') {
                arqueoBadge = '<span class="badge badge-warning">⚠️ Sobrante</span>';
            }
        }

        const initialCash = parseFloat(s.initial_cash) || 0;
        const expectedCash = parseFloat(s.expected_cash) || 0;
        const declaredCash = s.real_cash_declared !== null ? parseFloat(s.real_cash_declared) : null;
        const diff = s.difference !== null ? parseFloat(s.difference) : null;

        let diffHtml = '<span class="text-muted">---</span>';
        if (diff !== null) {
            if (Math.abs(diff) < 0.01) {
                diffHtml = '<strong class="text-success">S/ 0.00</strong>';
            } else if (diff < 0) {
                diffHtml = `<strong class="text-danger">- S/ ${Math.abs(diff).toFixed(2)}</strong>`;
            } else {
                diffHtml = `<strong class="text-warning">+ S/ ${diff.toFixed(2)}</strong>`;
            }
        }

        rowsHtml += `
            <tr>
                <td><strong>#${s.id}</strong></td>
                <td>Recepcionista (ID: ${s.user_id})</td>
                <td><small>${s.opened_at || '---'}</small></td>
                <td><small>${s.closed_at || '<em class="text-muted">En curso</em>'}</small></td>
                <td>S/ ${initialCash.toFixed(2)}</td>
                <td>S/ ${expectedCash.toFixed(2)}</td>
                <td>${declaredCash !== null ? `S/ ${declaredCash.toFixed(2)}` : '<em class="text-muted">---</em>'}</td>
                <td>${diffHtml}</td>
                <td>${statusBadge}</td>
                <td>${arqueoBadge}</td>
                <td>
                    <button type="button" class="btn btn-xs btn-outline-info" onclick="openShiftDetailModal(${s.id})">🔍 Ver Detalle</button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = rowsHtml;
}

async function openShiftDetailModal(shiftId) {
    try {
        const result = await apiFetch(`/api/cashbox/shift?id=${shiftId}`);
        if (!result.ok || result.data.status !== 'success') {
            if (typeof showToast === 'function') {
                showToast(result.data.message || 'No se pudo cargar el turno.', 'error');
            }
            return;
        }

        const s = result.data.data;
        document.getElementById('detail-shift-id').textContent = s.id;
        document.getElementById('detail-shift-user').textContent = `Usuario #${s.user_id}`;
        document.getElementById('detail-shift-opened-at').textContent = s.opened_at || '---';
        document.getElementById('detail-shift-closed-at').textContent = s.closed_at || 'En curso (Abierto)';
        document.getElementById('detail-shift-initial-cash').textContent = `S/ ${(parseFloat(s.initial_cash) || 0).toFixed(2)}`;
        document.getElementById('detail-shift-expected-cash').textContent = `S/ ${(parseFloat(s.expected_cash) || 0).toFixed(2)}`;
        document.getElementById('detail-shift-declared-cash').textContent = s.real_cash_declared !== null
            ? `S/ ${(parseFloat(s.real_cash_declared) || 0).toFixed(2)}`
            : 'No declarado';

        const diffBox = document.getElementById('detail-shift-diff-box');
        if (s.difference !== null) {
            const diff = parseFloat(s.difference) || 0;
            if (Math.abs(diff) < 0.01) {
                diffBox.className = 'arqueo-difference-box balanced mb-3';
                diffBox.textContent = '✅ DIFERENCIA: S/ 0.00 (CUADRADO PERFECTO)';
            } else if (diff < 0) {
                diffBox.className = 'arqueo-difference-box shortage mb-3';
                diffBox.textContent = `❌ DIFERENCIA: - S/ ${Math.abs(diff).toFixed(2)} (FALTANTE DE CAJA)`;
            } else {
                diffBox.className = 'arqueo-difference-box surplus mb-3';
                diffBox.textContent = `⚠️ DIFERENCIA: + S/ ${diff.toFixed(2)} (SOBRANTE DE CAJA)`;
            }
        } else {
            diffBox.className = 'arqueo-difference-box balanced mb-3';
            diffBox.textContent = '🔓 TURNO ACTIVO (BALANCE EN CURSO)';
        }

        const notesBox = document.getElementById('detail-shift-notes-box');
        if (s.notes) {
            notesBox.textContent = `Observaciones: "${s.notes}"`;
            notesBox.style.display = 'block';
        } else {
            notesBox.style.display = 'none';
        }

        // Render Movements in modal
        const movements = s.movements || [];
        const tbody = document.getElementById('table-detail-movements-body');
        if (movements.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hubo movimientos en este turno.</td></tr>';
        } else {
            let mHtml = '';
            movements.forEach((m, idx) => {
                const isIncome = m.type === 'income';
                const typeBadge = isIncome
                    ? '<span class="badge-income">💰 Ingreso (+)</span>'
                    : '<span class="badge-expense">💸 Egreso (−)</span>';
                const sign = isIncome ? '+' : '−';
                const amt = parseFloat(m.amount) || 0;
                const amtClass = isIncome ? 'text-success' : 'text-danger';

                mHtml += `
                    <tr>
                        <td><strong>#${m.id || (idx + 1)}</strong></td>
                        <td><small>${m.created_at || '---'}</small></td>
                        <td>${typeBadge}</td>
                        <td>${escapeHtml(m.concept || 'Movimiento')}</td>
                        <td>${m.payment_method}</td>
                        <td class="${amtClass}"><strong>${sign} S/ ${amt.toFixed(2)}</strong></td>
                    </tr>
                `;
            });
            tbody.innerHTML = mHtml;
        }

        openModal('modal-shift-detail');
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('Error de conexión al cargar detalle.', 'error');
        }
    }
}

function exportCashboxHistoryCsv() {
    const status = document.getElementById('history-filter-status') ? document.getElementById('history-filter-status').value : '';
    const userId = document.getElementById('history-filter-user-id') ? document.getElementById('history-filter-user-id').value.trim() : '';
    const from = document.getElementById('history-filter-from') ? document.getElementById('history-filter-from').value : '';
    const to = document.getElementById('history-filter-to') ? document.getElementById('history-filter-to').value : '';

    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (userId) params.append('user_id', userId);
    if (from) params.append('from', from);
    if (to) params.append('to', to);

    const queryString = params.toString() ? `?${params.toString()}` : '';
    window.location.href = `/api/cashbox/export/csv${queryString}`;
}

function exportCashboxHistoryPdf() {
    const status = document.getElementById('history-filter-status') ? document.getElementById('history-filter-status').value : '';
    const userId = document.getElementById('history-filter-user-id') ? document.getElementById('history-filter-user-id').value.trim() : '';
    const from = document.getElementById('history-filter-from') ? document.getElementById('history-filter-from').value : '';
    const to = document.getElementById('history-filter-to') ? document.getElementById('history-filter-to').value : '';

    const params = new URLSearchParams();
    if (status) params.append('status', status);
    if (userId) params.append('user_id', userId);
    if (from) params.append('from', from);
    if (to) params.append('to', to);

    const queryString = params.toString() ? `?${params.toString()}` : '';
    window.open(`/api/cashbox/export/pdf${queryString}`, '_blank');
}

// ─── UTILITIES ─────────────────────────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
