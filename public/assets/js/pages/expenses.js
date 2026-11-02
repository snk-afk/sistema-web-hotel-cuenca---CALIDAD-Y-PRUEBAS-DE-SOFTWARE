/**
 * CS-Hotel: Expenses & Other Incomes Frontend Logic
 */

let activeExpensesTab = 'expenses';
let expensesList = [];
let otherIncomesList = [];

document.addEventListener('DOMContentLoaded', () => {
    loadExpensesAndIncomesData();
});

async function loadExpensesAndIncomesData() {
    try {
        const [expRes, incRes] = await Promise.all([
            fetch('/api/expenses'),
            fetch('/api/other-incomes')
        ]);

        const expData = await expRes.json();
        const incData = await incRes.json();

        expensesList = (expData.status === 'success' && Array.isArray(expData.data)) ? expData.data : [];
        otherIncomesList = (incData.status === 'success' && Array.isArray(incData.data)) ? incData.data : [];

        updateExpensesMetrics();
        renderExpensesTable();
        renderIncomesTable();
    } catch (e) {
        console.error('Error cargando egresos e ingresos:', e);
    }
}

function updateExpensesMetrics() {
    const totalExpenses = expensesList.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    const totalIncomes = otherIncomesList.reduce((sum, item) => sum + (parseFloat(item.amount) || 0), 0);
    const netBalance = totalIncomes - totalExpenses;

    document.getElementById('metric-total-expenses').textContent = `S/ ${totalExpenses.toFixed(2)}`;
    document.getElementById('metric-total-other-incomes').textContent = `S/ ${totalIncomes.toFixed(2)}`;

    const netBalanceEl = document.getElementById('metric-net-balance');
    netBalanceEl.textContent = `${netBalance >= 0 ? '+' : ''} S/ ${netBalance.toFixed(2)}`;
    netBalanceEl.className = `metric-value ${netBalance >= 0 ? 'text-success' : 'text-danger'}`;

    document.getElementById('tab-count-expenses').textContent = expensesList.length;
    document.getElementById('tab-count-incomes').textContent = otherIncomesList.length;
}

function switchExpensesTab(tab, btn) {
    activeExpensesTab = tab;

    const allButtons = document.querySelectorAll('.expenses-tabs-bar .tab-btn');
    allButtons.forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const expContent = document.getElementById('tab-expenses-content');
    const incContent = document.getElementById('tab-incomes-content');

    if (tab === 'expenses') {
        if (expContent) expContent.style.display = 'block';
        if (incContent) incContent.style.display = 'none';
    } else {
        if (expContent) expContent.style.display = 'none';
        if (incContent) incContent.style.display = 'block';
    }

    filterExpensesTables();
}

function renderExpensesTable() {
    const tbody = document.getElementById('table-expenses-body');
    if (expensesList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No hay egresos registrados aún.</td></tr>';
        return;
    }

    const categoryLabels = {
        'supplies': '🧹 Limpieza e Insumos',
        'maintenance': '🔧 Mantenimiento',
        'utilities': '💡 Servicios Públicos',
        'food_beverage': '🛒 Tienda / Minibar',
        'petty_cash': '💵 Caja Chica General',
        'other': '📦 Otro Egreso',
    };

    const methodLabels = {
        'cash': '💵 Efectivo',
        'yape': '🟣 Yape / Plin',
        'card': '💳 Tarjeta',
        'transfer': '🏦 Transferencia',
    };

    let html = '';
    expensesList.forEach((exp, idx) => {
        const cat = categoryLabels[exp.category] || exp.category;
        const method = methodLabels[exp.payment_method] || exp.payment_method;
        const amount = parseFloat(exp.amount) || 0;
        const searchStr = `${exp.id} ${exp.concept} ${exp.category} ${exp.supplier_name || ''} ${exp.receipt_number || ''}`.toLowerCase();

        html += `
            <tr data-search="${searchStr}">
                <td><strong>#${exp.id || (idx + 1)}</strong></td>
                <td>${exp.created_at || '---'}</td>
                <td><span class="category-tag">${cat}</span></td>
                <td>${escapeHtml(exp.concept)}</td>
                <td>${escapeHtml(exp.supplier_name || '—')}</td>
                <td><span class="badge badge-secondary">${escapeHtml(exp.receipt_number || '—')}</span></td>
                <td>${method}</td>
                <td class="text-danger"><strong>- S/ ${amount.toFixed(2)}</strong></td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function renderIncomesTable() {
    const tbody = document.getElementById('table-incomes-body');
    if (otherIncomesList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No hay otros ingresos registrados aún.</td></tr>';
        return;
    }

    const categoryLabels = {
        'parking': '🚗 Cochera / Parking',
        'laundry_external': '🧺 Lavandería Externa',
        'penalty_damage': '⚠️ Penalidad / Rotura',
        'events': '🎉 Eventos / Espacios',
        'other_income': '💰 Otro Ingreso',
    };

    const methodLabels = {
        'cash': '💵 Efectivo',
        'yape': '🟣 Yape / Plin',
        'card': '💳 Tarjeta',
        'transfer': '🏦 Transferencia',
    };

    let html = '';
    otherIncomesList.forEach((inc, idx) => {
        const cat = categoryLabels[inc.category] || inc.category;
        const method = methodLabels[inc.payment_method] || inc.payment_method;
        const amount = parseFloat(inc.amount) || 0;
        const searchStr = `${inc.id} ${inc.concept} ${inc.category} ${inc.notes || ''}`.toLowerCase();

        html += `
            <tr data-search="${searchStr}">
                <td><strong>#${inc.id || (idx + 1)}</strong></td>
                <td>${inc.created_at || '---'}</td>
                <td><span class="category-tag">${cat}</span></td>
                <td>${escapeHtml(inc.concept)}</td>
                <td>${escapeHtml(inc.notes || '—')}</td>
                <td>${method}</td>
                <td class="text-success"><strong>+ S/ ${amount.toFixed(2)}</strong></td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function filterExpensesTables() {
    const term = document.getElementById('filter-expenses-input').value.toLowerCase().trim();
    const rows = document.querySelectorAll(activeExpensesTab === 'expenses' ? '#table-expenses-body tr' : '#table-incomes-body tr');

    rows.forEach(row => {
        const searchStr = row.getAttribute('data-search') || '';
        row.style.display = (term === '' || searchStr.includes(term)) ? '' : 'none';
    });
}

// ─── CREATE EXPENSE ────────────────────────────────────────

async function handleCreateExpenseSubmit(event) {
    event.preventDefault();

    const category = document.getElementById('expense-category').value;
    const concept = document.getElementById('expense-concept').value.trim();
    const supplier = document.getElementById('expense-supplier').value.trim() || null;
    const receipt = document.getElementById('expense-receipt').value.trim() || null;
    const amount = parseFloat(document.getElementById('expense-amount').value) || 0;
    const paymentMethod = document.getElementById('expense-payment-method').value;

    if (!concept || amount <= 0) {
        if (typeof showToast === 'function') {
            showToast('Ingrese el concepto del gasto y un monto mayor a 0.', 'warning');
        }
        return;
    }

    const btn = document.getElementById('btn-submit-expense');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Registrando egreso...';
    }

    try {
        const result = await apiFetch('/api/expenses', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                category: category,
                concept: concept,
                supplier_name: supplier,
                receipt_number: receipt,
                amount: amount,
                payment_method: paymentMethod,
            }),
        });


        if (result.ok && result.data.status === 'success') {
            closeModal('modal-create-expense');
            document.getElementById('form-create-expense').reset();
            if (typeof showToast === 'function') {
                showToast('✅ Egreso registrado exitosamente.', 'success');
            }
            loadExpensesAndIncomesData();
        } else {
            const errorMsg = result.data.message || 'Error al registrar el egreso.';
            if (typeof showToast === 'function') {
                showToast(`❌ ${errorMsg}`, 'error');
            }
            if (errorMsg.includes('caja')) {
                const alertBox = document.getElementById('expenses-cashbox-alert');
                if (alertBox) alertBox.style.display = 'block';
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión al registrar egreso.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '🔴 Registrar y Descontar Egreso';
        }
    }
}

// ─── CREATE OTHER INCOME ───────────────────────────────────

async function handleCreateIncomeSubmit(event) {
    event.preventDefault();

    const category = document.getElementById('income-category').value;
    const concept = document.getElementById('income-concept').value.trim();
    const amount = parseFloat(document.getElementById('income-amount').value) || 0;
    const paymentMethod = document.getElementById('income-payment-method').value;
    const notes = document.getElementById('income-notes').value.trim() || null;

    if (!concept || amount <= 0) {
        if (typeof showToast === 'function') {
            showToast('Ingrese el concepto del ingreso y un monto mayor a 0.', 'warning');
        }
        return;
    }

    const btn = document.getElementById('btn-submit-income');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Registrando ingreso...';
    }

    try {
        const result = await apiFetch('/api/other-incomes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                category: category,
                concept: concept,
                amount: amount,
                payment_method: paymentMethod,
                notes: notes,
            }),
        });


        if (result.ok && result.data.status === 'success') {
            closeModal('modal-create-income');
            document.getElementById('form-create-income').reset();
            if (typeof showToast === 'function') {
                showToast('✅ Otro ingreso registrado exitosamente.', 'success');
            }
            loadExpensesAndIncomesData();
        } else {
            const errorMsg = result.data.message || 'Error al registrar el ingreso.';
            if (typeof showToast === 'function') {
                showToast(`❌ ${errorMsg}`, 'error');
            }
            if (errorMsg.includes('caja')) {
                const alertBox = document.getElementById('expenses-cashbox-alert');
                if (alertBox) alertBox.style.display = 'block';
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión al registrar ingreso.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '🟢 Registrar Ingreso en Caja';
        }
    }
}

// ─── UTILITIES ─────────────────────────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
