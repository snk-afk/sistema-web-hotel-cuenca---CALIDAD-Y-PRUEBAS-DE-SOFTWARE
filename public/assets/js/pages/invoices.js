/**
 * CS-Hotel: Invoices & SUNAT Billing Frontend Logic
 */

let activeInvoiceTab = 'all';
let allInvoicesList = [];

document.addEventListener('DOMContentLoaded', () => {
    loadInvoicesData();
});

async function loadInvoicesData() {
    try {
        const result = await apiFetch('/api/invoices');

        if (result.data.status === 'success' && Array.isArray(result.data.data)) {
            allInvoicesList = result.data.data;
            updateInvoicesMetrics();
            renderInvoicesTable();
        }
    } catch (e) {
        console.error('Error cargando comprobantes:', e);
    }
}

function updateInvoicesMetrics() {
    const totalAmount = allInvoicesList.reduce((sum, inv) => sum + (parseFloat(inv.total_amount) || 0), 0);
    const boletas = allInvoicesList.filter(inv => inv.document_type === 'BOLETA').length;
    const facturas = allInvoicesList.filter(inv => inv.document_type === 'FACTURA').length;
    const notas = allInvoicesList.filter(inv => inv.document_type === 'NOTA_VENTA').length;
    const sunatAccepted = allInvoicesList.filter(inv => inv.sunat_status === 'ACCEPTED').length;

    document.getElementById('metric-invoices-total-amount').textContent = `S/ ${totalAmount.toFixed(2)}`;
    document.getElementById('metric-boletas-count').textContent = boletas;
    document.getElementById('metric-facturas-count').textContent = facturas;
    document.getElementById('metric-sunat-accepted-count').textContent = sunatAccepted;

    document.getElementById('tab-count-all').textContent = allInvoicesList.length;
    document.getElementById('tab-count-boletas').textContent = boletas;
    document.getElementById('tab-count-facturas').textContent = facturas;
    document.getElementById('tab-count-notas').textContent = notas;
}

function filterInvoicesByTab(docType, btn) {
    activeInvoiceTab = docType;

    const allButtons = document.querySelectorAll('.invoices-tabs-bar .tab-btn');
    allButtons.forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    filterInvoicesTable();
}

function renderInvoicesTable() {
    const tbody = document.getElementById('table-invoices-body');

    if (allInvoicesList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No hay comprobantes emitidos aún.</td></tr>';
        return;
    }

    let html = '';
    allInvoicesList.forEach((inv, idx) => {
        let sunatBadge = '';
        if (inv.sunat_status === 'ACCEPTED') {
            sunatBadge = '<span class="badge-sunat-accepted">✅ ACEPTADO</span>';
        } else if (inv.sunat_status === 'PENDING') {
            sunatBadge = '<span class="badge-sunat-pending">⏳ PENDIENTE</span>';
        } else {
            sunatBadge = '<span class="badge-sunat-rejected">❌ RECHAZADO</span>';
        }

        const total = parseFloat(inv.total_amount) || 0;

        let downloadBtns = `<button class="btn btn-sm btn-outline-info mr-1" onclick="openInvoiceDetailModal(${inv.id})">👁️ Ver</button> `;
        if (inv.pdf_url) {
            downloadBtns += `<a href="${escapeHtml(inv.pdf_url)}" target="_blank" class="btn btn-sm btn-outline-danger mr-1">📄 PDF</a> `;
        }
        if (inv.voucher_url) {
            downloadBtns += `<a href="${escapeHtml(inv.voucher_url)}" target="_blank" class="btn btn-sm btn-outline-success mr-1">🖨️ Voucher</a> `;
        }
        if (inv.xml_url) {
            downloadBtns += `<a href="${escapeHtml(inv.xml_url)}" target="_blank" class="btn btn-sm btn-outline-primary">💻 XML</a>`;
        }

        const searchStr = `${inv.id} ${inv.full_number} ${inv.document_type} ${inv.customer_document_number} ${inv.customer_name}`.toLowerCase();

        html += `
            <tr data-type="${inv.document_type}" data-search="${searchStr}">
                <td><strong>#${inv.id || (idx + 1)}</strong></td>
                <td><strong class="text-info">${escapeHtml(inv.full_number)}</strong></td>
                <td><span class="badge badge-secondary">${escapeHtml(inv.document_type)}</span></td>
                <td>${escapeHtml(inv.customer_document_type)}: ${escapeHtml(inv.customer_document_number)}</td>
                <td>${escapeHtml(inv.customer_name)}</td>
                <td class="text-success"><strong>S/ ${total.toFixed(2)}</strong></td>
                <td>${sunatBadge}</td>
                <td>${downloadBtns}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    filterInvoicesTable();
}

function filterInvoicesTable() {
    const term = document.getElementById('filter-invoices-input').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#table-invoices-body tr');

    rows.forEach(row => {
        const rowType = row.getAttribute('data-type');
        const rowSearch = row.getAttribute('data-search') || '';

        const matchesTab = activeInvoiceTab === 'all' || rowType === activeInvoiceTab;
        const matchesSearch = term === '' || rowSearch.includes(term);

        row.style.display = (matchesTab && matchesSearch) ? '' : 'none';
    });
}

// ─── INVOICE DETAIL / VOUCHER MODAL ────────────────────────

let currentDetailInvoice = null;

function openInvoiceDetailModal(invoiceId) {
    const invoice = allInvoicesList.find(inv => inv.id === invoiceId);
    if (!invoice) return;

    currentDetailInvoice = invoice;

    // Por defecto mostrar el Voucher 80mm si existe, sino el PDF A4
    const defaultMode = invoice.voucher_url ? 'voucher' : 'a4';
    switchInvoiceViewMode(defaultMode);

    openModal('modal-invoice-detail');
}

function switchInvoiceViewMode(mode) {
    if (!currentDetailInvoice) return;

    const frame = document.getElementById('invoice-pdf-frame');
    const btnVoucher = document.getElementById('btn-view-mode-voucher');
    const btnA4 = document.getElementById('btn-view-mode-a4');

    if (mode === 'voucher') {
        if (btnVoucher) { btnVoucher.className = 'btn btn-primary active'; }
        if (btnA4) { btnA4.className = 'btn btn-outline-secondary'; }

        if (currentDetailInvoice.voucher_url) {
            frame.src = currentDetailInvoice.voucher_url;
        } else if (currentDetailInvoice.pdf_url) {
            frame.src = currentDetailInvoice.pdf_url;
        } else {
            frame.srcdoc = '<div style="font-family: sans-serif; padding: 40px; text-align: center; color: #6b7280;"><h3>Sin Voucher</h3><p>Este comprobante no tiene archivo generado.</p></div>';
        }
    } else {
        if (btnA4) { btnA4.className = 'btn btn-primary active'; }
        if (btnVoucher) { btnVoucher.className = 'btn btn-outline-secondary'; }

        if (currentDetailInvoice.pdf_url) {
            frame.src = currentDetailInvoice.pdf_url;
        } else {
            frame.srcdoc = '<div style="font-family: sans-serif; padding: 40px; text-align: center; color: #6b7280;"><h3>Sin PDF A4</h3><p>Este comprobante no tiene PDF A4 generado.</p></div>';
        }
    }

    // Botones de descarga y de impresión térmica directa
    const downloadsCont = document.getElementById('invoice-detail-downloads');
    let downHtml = '';
    if (currentDetailInvoice.voucher_url) {
        downHtml += `<a href="${escapeHtml(currentDetailInvoice.voucher_url)}" target="_blank" class="btn btn-outline-success btn-sm">🖨️ Voucher</a> `;
    }
    if (currentDetailInvoice.pdf_url) {
        downHtml += `<a href="${escapeHtml(currentDetailInvoice.pdf_url)}" target="_blank" class="btn btn-outline-danger btn-sm">📄 PDF A4</a> `;
    }
    if (currentDetailInvoice.xml_url) {
        downHtml += `<a href="${escapeHtml(currentDetailInvoice.xml_url)}" target="_blank" class="btn btn-outline-primary btn-sm">💻 XML</a> `;
    }

    downHtml += `<button type="button" class="btn btn-warning btn-sm" onclick="triggerEscPosPrint(${currentDetailInvoice.id})">⚡ Imprimir ESC/POS</button>`;

    downloadsCont.innerHTML = downHtml;
}

function triggerEscPosPrint(invoiceId) {
    const printUrl = `/api/invoices/${invoiceId}/escpos?open_drawer=1`;
    // Abre el flujo ESC/POS directo o descarga de binario
    const w = window.open(printUrl, '_blank');
    if (w) {
        setTimeout(() => w.close(), 1000);
    }
    if (typeof showToast === 'function') {
        showToast('Enviando comando de impresión a impresora térmica y apertura de gaveta...', 'info');
    }
}

// ─── ISSUE MANUAL INVOICE ──────────────────────────────────

function toggleManualInvoiceDocFields() {
    const docType = document.getElementById('issue-doc-type').value;
    const docTypeSelect = document.getElementById('issue-customer-doc-type');

    if (docType === 'FACTURA') {
        docTypeSelect.value = 'RUC';
        document.getElementById('issue-customer-doc-num').placeholder = 'Ej: 20601234567';
        document.getElementById('issue-customer-name').placeholder = 'Ej: EMPRESA TURISMO S.A.C.';
    } else {
        docTypeSelect.value = 'DNI';
        document.getElementById('issue-customer-doc-num').placeholder = 'Ej: 72345678';
        document.getElementById('issue-customer-name').placeholder = 'Ej: Juan Pérez';
    }
}

function recalculateManualInvoiceTotal() {
    const qty = parseInt(document.getElementById('issue-item-qty').value, 10) || 1;
    const price = parseFloat(document.getElementById('issue-item-price').value) || 0;
    const total = qty * price;

    const taxable = total / 1.18;
    const igv = total - taxable;

    document.getElementById('manual-preview-taxable').textContent = `S/ ${taxable.toFixed(2)}`;
    document.getElementById('manual-preview-igv').textContent = `S/ ${igv.toFixed(2)}`;
    document.getElementById('manual-preview-total').textContent = `S/ ${total.toFixed(2)}`;
}

async function handleIssueInvoiceSubmit(event) {
    event.preventDefault();

    const docType = document.getElementById('issue-doc-type').value;
    const customerDocType = document.getElementById('issue-customer-doc-type').value;
    const customerDocNum = document.getElementById('issue-customer-doc-num').value.trim();
    const customerName = document.getElementById('issue-customer-name').value.trim();
    const customerAddress = document.getElementById('issue-customer-address').value.trim() || null;

    const itemDesc = document.getElementById('issue-item-description').value.trim();
    const itemQty = parseInt(document.getElementById('issue-item-qty').value, 10) || 1;
    const itemPrice = parseFloat(document.getElementById('issue-item-price').value) || 0;
    const totalAmount = itemQty * itemPrice;

    if (!customerDocNum || !customerName || !itemDesc || totalAmount <= 0) {
        if (typeof showToast === 'function') {
            showToast('Complete todos los campos requeridos con montos válidos.', 'warning');
        }
        return;
    }

    const btn = document.getElementById('btn-submit-issue-invoice');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Emitiendo y firmando comprobante...';
    }

    try {
        const result = await apiFetch('/api/invoices/issue', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Idempotency-Key': crypto.randomUUID(),
            },
            body: JSON.stringify({
                document_type: docType,
                customer_document_type: customerDocType,
                customer_document_number: customerDocNum,
                customer_name: customerName,
                customer_address: customerAddress,
                total_amount: totalAmount,
                items: [
                    {
                        description: itemDesc,
                        quantity: itemQty,
                        unit_price: itemPrice,
                        subtotal: totalAmount,
                    }
                ]
            }),
        });


        if (result.ok && result.data.status === 'success') {
            closeModal('modal-issue-invoice');
            document.getElementById('form-issue-invoice').reset();
            if (typeof showToast === 'function') {
                showToast(`✅ Comprobante ${result.data.data?.full_number || ''} emitido y aceptado por SUNAT.`, 'success');
            }
            loadInvoicesData();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al emitir comprobante.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión con el servidor.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '🚀 Emitir y Enviar a SUNAT';
        }
    }
}

// ─── UTILITIES ─────────────────────────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
