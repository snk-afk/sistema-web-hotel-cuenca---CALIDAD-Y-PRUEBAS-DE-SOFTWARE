/**
 * CS-Hotel: Executive Reports & KPIs Frontend Logic
 */

let currentReportFilter = { from: null, to: null, label: 'Todo el Histórico' };

document.addEventListener('DOMContentLoaded', () => {
    loadReportsData();
});

async function loadReportsData() {
    let occupancyUrl = '/api/reports/occupancy';
    let financialUrl = '/api/reports/financial';
    let exportUrl = '/api/reports/export/csv';
    let exportPdfUrl = '/api/reports/export/pdf';

    const params = new URLSearchParams();
    if (currentReportFilter.from) params.append('from', currentReportFilter.from);
    if (currentReportFilter.to) params.append('to', currentReportFilter.to);

    const qs = params.toString();
    if (qs) {
        occupancyUrl += `?${qs}`;
        financialUrl += `?${qs}`;
        exportUrl += `?${qs}`;
        exportPdfUrl += `?${qs}`;
    }

    const exportBtn = document.getElementById('btn-export-csv');
    if (exportBtn) exportBtn.href = exportUrl;

    const exportPdfBtn = document.getElementById('btn-export-pdf');
    if (exportPdfBtn) exportPdfBtn.href = exportPdfUrl;

    try {
        const [occRes, finRes] = await Promise.all([
            fetch(occupancyUrl),
            fetch(financialUrl)
        ]);

        const occData = await occRes.json();
        const finData = await finRes.json();

        if (occData.status === 'success' && occData.data) {
            renderOccupancyKpis(occData.data);
        }

        if (finData.status === 'success' && finData.data) {
            renderFinancialSummary(finData.data);
        }
    } catch (e) {
        console.error('Error cargando reportes ejecutivos:', e);
    }
}

function renderOccupancyKpis(data) {
    const rate = parseFloat(data.occupancy_rate) || 0;
    const occupied = parseInt(data.occupied_rooms, 10) || 0;
    const total = parseInt(data.total_rooms, 10) || 0;
    const adr = parseFloat(data.adr) || 0;
    const revpar = parseFloat(data.revpar) || 0;

    document.getElementById('kpi-occupancy-rate').textContent = `${rate.toFixed(1)} %`;
    document.getElementById('kpi-occupancy-rooms-count').textContent = `${occupied} de ${total} habitaciones ocupadas`;
    document.getElementById('kpi-occupancy-fill').style.width = `${Math.min(100, rate)}%`;

    document.getElementById('kpi-adr-value').textContent = `S/ ${adr.toFixed(2)}`;
    document.getElementById('kpi-revpar-value').textContent = `S/ ${revpar.toFixed(2)}`;
}

function renderFinancialSummary(data) {
    const acc = parseFloat(data.accommodation_income) || 0;
    const store = parseFloat(data.store_income) || 0;
    const other = parseFloat(data.other_income) || 0;
    const totalIncome = parseFloat(data.total_income) || 0;
    const totalExp = parseFloat(data.total_expenses) || 0;
    const netProfit = parseFloat(data.net_profit) || 0;

    // Header KPI
    const kpiProfit = document.getElementById('kpi-net-profit');
    kpiProfit.textContent = `${netProfit >= 0 ? '+' : ''} S/ ${netProfit.toFixed(2)}`;
    kpiProfit.className = `reports-metric-value ${netProfit >= 0 ? 'text-success' : 'text-danger'}`;

    // Table values
    document.getElementById('fin-acc-income').textContent = `S/ ${acc.toFixed(2)}`;
    document.getElementById('fin-store-income').textContent = `S/ ${store.toFixed(2)}`;
    document.getElementById('fin-other-income').textContent = `S/ ${other.toFixed(2)}`;
    document.getElementById('fin-total-income').textContent = `S/ ${totalIncome.toFixed(2)}`;
    document.getElementById('fin-total-expenses').textContent = `- S/ ${totalExp.toFixed(2)}`;

    const finNet = document.getElementById('fin-net-profit');
    finNet.textContent = `${netProfit >= 0 ? '+' : ''} S/ ${netProfit.toFixed(2)}`;
    finNet.className = netProfit >= 0 ? 'text-success' : 'text-danger';

    // Shares
    const accShare = totalIncome > 0 ? ((acc / totalIncome) * 100).toFixed(1) : '0.0';
    const storeShare = totalIncome > 0 ? ((store / totalIncome) * 100).toFixed(1) : '0.0';
    const otherShare = totalIncome > 0 ? ((other / totalIncome) * 100).toFixed(1) : '0.0';
    const expShare = totalIncome > 0 ? ((totalExp / totalIncome) * 100).toFixed(1) : '0.0';
    const margin = totalIncome > 0 ? ((netProfit / totalIncome) * 100).toFixed(1) : '0.0';

    document.getElementById('fin-acc-share').textContent = `${accShare}%`;
    document.getElementById('fin-store-share').textContent = `${storeShare}%`;
    document.getElementById('fin-other-share').textContent = `${otherShare}%`;
    document.getElementById('fin-expenses-share').textContent = `${expShare}% de ingresos`;
    document.getElementById('fin-profit-margin').textContent = `Margen: ${margin}%`;
}

// ─── FILTER HANDLERS ───────────────────────────────────────

function applyQuickReportFilter(range, btn) {
    const allButtons = document.querySelectorAll('.quick-filter-btn');
    allButtons.forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    const todayStr = `${y}-${m}-${d}`;

    if (range === 'today') {
        currentReportFilter = { from: todayStr, to: todayStr, label: 'Hoy' };
    } else if (range === 'week') {
        const firstDay = new Date(now.setDate(now.getDate() - now.getDay() + 1));
        const fy = firstDay.getFullYear();
        const fm = String(firstDay.getMonth() + 1).padStart(2, '0');
        const fd = String(firstDay.getDate()).padStart(2, '0');
        currentReportFilter = { from: `${fy}-${fm}-${fd}`, to: todayStr, label: 'Esta Semana' };
    } else if (range === 'month') {
        currentReportFilter = { from: `${y}-${m}-01`, to: todayStr, label: 'Este Mes' };
    } else {
        currentReportFilter = { from: null, to: null, label: 'Todo el Histórico' };
    }

    document.getElementById('report-filter-from').value = currentReportFilter.from || '';
    document.getElementById('report-filter-to').value = currentReportFilter.to || '';
    document.getElementById('report-period-label').textContent = `Período: ${currentReportFilter.label}`;

    loadReportsData();
}

function handleCustomDateReportFilter(event) {
    event.preventDefault();

    const fromVal = document.getElementById('report-filter-from').value;
    const toVal = document.getElementById('report-filter-to').value;

    const allButtons = document.querySelectorAll('.quick-filter-btn');
    allButtons.forEach(b => b.classList.remove('active'));

    currentReportFilter = {
        from: fromVal || null,
        to: toVal || null,
        label: `${fromVal || 'Inicio'} a ${toVal || 'Hoy'}`
    };

    document.getElementById('report-period-label').textContent = `Período: ${currentReportFilter.label}`;
    loadReportsData();
}
