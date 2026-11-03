/**
 * CS-Hotel: Expenses & Other Incomes Frontend Logic
 */

const expensePaymentLabels = {
    'cash': '💵 Efectivo',
    'yape': '🟣 Yape / Plin',
    'card': '💳 Tarjeta',
    'transfer': '🏦 Transferencia',
};

let activeExpensesTab = 'expenses';
let expensesList = [];
let otherIncomesList = [];

document.addEventListener('DOMContentLoaded', loadExpensesAndIncomesData);

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
    const totalExpenses = expensesList.reduce((sum, item) => sum + (Number.parseFloat(item.amount) || 0), 0);
    const totalIncomes = otherIncomesList.reduce((sum, item) => sum + (Number.parseFloat(item.amount) || 0), 0);
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

    let html = '';
    expensesList.forEach((exp, idx) => {
        const cat = categoryLabels[exp.category] || exp.category;
        const method = expensePaymentLabels[exp.payment_method] || exp.payment_method;
        const amount = Number.parseFloat(exp.amount) || 0;
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

    let html = '';
    otherIncomesList.forEach((inc, idx) => {
        const cat = categoryLabels[inc.category] || inc.category;
        const method = expensePaymentLabels[inc.payment_method] || inc.payment_method;
        const amount = Number.parseFloat(inc.amount) || 0;
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
    return submitExpenseOrIncome('expense');
}

async function handleCreateIncomeSubmit(event) {
    event.preventDefault();
    return submitExpenseOrIncome('income');
}

async function submitExpenseOrIncome(kind) {
    const isExpense = kind === 'expense';
    const name = isExpense ? 'egreso' : 'ingreso';
    const field = suffix => document.getElementById(`${kind}-${suffix}`).value;
    const payload = {
        category: field('category'),
        concept: field('concept').trim(),
        amount: Number.parseFloat(field('amount')) || 0,
        payment_method: field('payment-method'),
    };
    if (isExpense) {
        payload.supplier_name = field('supplier').trim() || null;
        payload.receipt_number = field('receipt').trim() || null;
    } else {
        payload.notes = field('notes').trim() || null;
    }

    if (!payload.concept || payload.amount <= 0) {
        if (typeof showToast === 'function') {
            showToast(`Ingrese el concepto del ${isExpense ? 'gasto' : 'ingreso'} y un monto mayor a 0.`, 'warning');
        }
        return;
    }

    const btn = document.getElementById(`btn-submit-${kind}`);
    if (btn) {
        btn.disabled = true;
        btn.textContent = `⏳ Registrando ${name}...`;
    }
    try {
        const result = await apiFetch(isExpense ? '/api/expenses' : '/api/other-incomes', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        if (result.ok && result.data.status === 'success') {
            closeModal(`modal-create-${kind}`);
            document.getElementById(`form-create-${kind}`).reset();
            if (typeof showToast === 'function') {
                showToast(`✅ ${isExpense ? 'Egreso' : 'Otro ingreso'} registrado exitosamente.`, 'success');
            }
            await loadExpensesAndIncomesData();
        } else {
            const errorMsg = result.data.message || `Error al registrar el ${name}.`;
            if (typeof showToast === 'function') showToast(`❌ ${errorMsg}`, 'error');
            if (errorMsg.includes('caja')) {
                const alertBox = document.getElementById('expenses-cashbox-alert');
                if (alertBox) alertBox.style.display = 'block';
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') showToast(`❌ Error de conexión al registrar ${name}.`, 'error');
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = isExpense ? '🔴 Registrar y Descontar Egreso' : '🟢 Registrar Ingreso en Caja';
        }
    }
}

// ─── UTILITIES ─────────────────────────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
