// ==========================================
// 🪪 GUESTS CRM & MASTER DIRECTORY JAVASCRIPT
// ==========================================

function filterGuestsTable() {
    const searchInput = document.getElementById('filter-guests-input');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const typeFilter = document.getElementById('filter-guests-type');
    const selectedType = typeFilter ? typeFilter.value.trim() : '';

    const rows = document.querySelectorAll('#table-guests-directory tbody .guest-row-item');
    rows.forEach(row => {
        const docType = (row.getAttribute('data-doc-type') || '').trim();
        const docNum = (row.getAttribute('data-doc-num') || '').toLowerCase();
        const name = (row.getAttribute('data-name') || '').toLowerCase();
        const phone = (row.getAttribute('data-phone') || '').toLowerCase();
        const notes = (row.getAttribute('data-notes') || '').toLowerCase();

        const matchesQuery = query === '' || docNum.includes(query) || name.includes(query) || phone.includes(query) || notes.includes(query);
        const matchesType = selectedType === '' || docType === selectedType;

        if (matchesQuery && matchesType) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function toggleGuestModalDocType() {
    const docType = document.getElementById('guest-form-doc-type').value;
    const docNum = document.getElementById('guest-form-doc-num');
    const lblDoc = document.getElementById('lbl-modal-doc-num');
    const lblName = document.getElementById('lbl-modal-name');

    if (docType === 'RUC') {
        if (lblDoc) lblDoc.textContent = 'RUC (11 dígitos):';
        if (lblName) lblName.textContent = 'Razón Social:';
        if (docNum) {
            docNum.placeholder = 'Ej: 20601234567';
            docNum.maxLength = 11;
        }
    } else {
        if (lblDoc) lblDoc.textContent = 'DNI (8 dígitos):';
        if (lblName) lblName.textContent = 'Nombre Completo:';
        if (docNum) {
            docNum.placeholder = 'Ej: 74819203';
            docNum.maxLength = 8;
        }
    }
}

function openCreateGuestModal() {
    document.getElementById('modal-guest-title').textContent = '➕ Registrar Nuevo Huésped';
    document.getElementById('guest-form-id').value = '';
    document.getElementById('guest-form-doc-type').value = 'DNI';
    document.getElementById('guest-form-doc-num').value = '';
    document.getElementById('guest-form-name').value = '';
    document.getElementById('guest-form-phone').value = '';
    document.getElementById('guest-form-email').value = '';
    document.getElementById('guest-form-address').value = '';
    document.getElementById('guest-form-notes').value = '';
    document.getElementById('btn-save-guest').textContent = 'Guardar Huésped';
    toggleGuestModalDocType();
    openModal('modal-guest-form');
}

function openEditGuestModal(id, docType, docNum, name, phone, email, address, notes) {
    document.getElementById('modal-guest-title').textContent = `✏️ Editar Huésped: ${name}`;
    document.getElementById('guest-form-id').value = id;
    document.getElementById('guest-form-doc-type').value = docType || 'DNI';
    document.getElementById('guest-form-doc-num').value = docNum || '';
    document.getElementById('guest-form-name').value = name || '';
    document.getElementById('guest-form-phone').value = phone || '';
    document.getElementById('guest-form-email').value = email || '';
    document.getElementById('guest-form-address').value = address || '';
    document.getElementById('guest-form-notes').value = notes || '';
    document.getElementById('btn-save-guest').textContent = 'Actualizar Huésped';
    toggleGuestModalDocType();
    openModal('modal-guest-form');
}

async function lookupGuestDocumentInModal() {
    const docType = document.getElementById('guest-form-doc-type').value;
    const docNum = document.getElementById('guest-form-doc-num').value.trim();

    if (!docNum) {
        showToast('Ingrese el número de documento para consultar', 'warning');
        return;
    }

    try {
        const res = await apiFetch(`/api/guests/lookup?document_type=${docType}&document_number=${docNum}&autocomplete=true`);
        if (res.ok && res.data.data) {
            const data = res.data.data;
            if (data.guest) {
                const g = data.guest;
                document.getElementById('guest-form-name').value = g.name || `${g.first_name || ''} ${g.last_name || ''}`.trim();
                if (g.phone) document.getElementById('guest-form-phone').value = g.phone;
                if (g.email) document.getElementById('guest-form-email').value = g.email;
                if (g.address) document.getElementById('guest-form-address').value = g.address;
                showToast(`Huésped encontrado en el sistema: ${g.name}`);
            } else if (data.external_data) {
                const ext = data.external_data;
                const fullName = ext.company_name || `${ext.first_name || ''} ${ext.last_name || ''}`.trim();
                document.getElementById('guest-form-name').value = fullName;
                if (ext.address) document.getElementById('guest-form-address').value = ext.address;
                showToast(`Datos validados con RENIEC/SUNAT: ${fullName}`);
            } else {
                showToast('Documento no encontrado. Complete los datos manualmente.', 'warning');
            }
        }
    } catch (e) {
        showToast('Error al consultar padrón', 'error');
    }
}

async function handleGuestFormSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('guest-form-id').value;
    const docType = document.getElementById('guest-form-doc-type').value;
    const docNum = document.getElementById('guest-form-doc-num').value.trim();
    const name = document.getElementById('guest-form-name').value.trim();
    const phone = document.getElementById('guest-form-phone').value.trim();
    const email = document.getElementById('guest-form-email').value.trim();
    const address = document.getElementById('guest-form-address').value.trim();
    const notes = document.getElementById('guest-form-notes').value.trim();

    const isEdit = Boolean(id);

    try {
        const res = await apiFetch('/api/guests', {
            method: 'POST',
            body: JSON.stringify({
                id: id ? parseInt(id, 10) : undefined,
                document_type: docType,
                document_number: docNum,
                name: name,
                first_name: name,
                phone: phone,
                email: email,
                address: address,
                notes: notes
            })
        });

        if (res.ok) {
            showToast(res.data.message || (isEdit ? 'Huésped actualizado exitosamente' : 'Huésped registrado exitosamente'));
            closeModal('modal-guest-form');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'Error al guardar huésped', 'error');
        }
    } catch (err) {
        showToast('Error de comunicación con el servidor', 'error');
    }
}

let currentProfileGuestId = null;
let currentProfileGuestDoc = null;

async function openGuestProfileModal(guestId) {
    currentProfileGuestId = guestId;
    currentProfileGuestDoc = null;
    const historyBody = document.getElementById('guest-history-body');
    historyBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Cargando ficha...</td></tr>';
    openModal('modal-guest-profile');

    try {
        const res = await apiFetch(`/api/guests`);
        if (res.ok && res.data && res.data.data) {
            const guest = res.data.data.find(g => g.id === guestId);
            if (guest) {
                currentProfileGuestDoc = guest.document_number;
                document.getElementById('profile-guest-name').textContent = guest.name;
                document.getElementById('profile-guest-doc').textContent = `${guest.document_type}: ${guest.document_number}`;
                document.getElementById('profile-guest-phone').textContent = `📱 ${guest.phone || 'Sin teléfono'}`;
                document.getElementById('profile-total-visits').textContent = guest.stays_count || 0;
                document.getElementById('profile-total-spent').textContent = `S/ ${parseFloat(guest.total_spent || 0).toFixed(2)}`;
                document.getElementById('profile-guest-address').textContent = guest.address || 'No especificada';

                const isVip = guest.stays_count >= 2 || (guest.notes && guest.notes.toUpperCase().includes('VIP'));
                const vipBadge = document.getElementById('profile-guest-vip');
                if (vipBadge) {
                    vipBadge.style.display = isVip ? 'inline-block' : 'none';
                }
            }
        }

        // Fetch individual room stays matching this guest from /api/rooms
        const roomsRes = await apiFetch('/api/rooms');
        let staysHtml = '';
        if (roomsRes.ok && roomsRes.data && roomsRes.data.data) {
            const allRooms = roomsRes.data.data;
            const currentGuestStays = allRooms.filter(r => r.current_stay && r.current_stay.guest_id === guestId);

            if (currentGuestStays.length > 0) {
                currentGuestStays.forEach(r => {
                    const s = r.current_stay;
                    staysHtml += `
                        <tr>
                            <td>#${s.id}</td>
                            <td><strong>Hab. ${r.number}</strong></td>
                            <td><span class="category-chip">${r.category_name}</span></td>
                            <td>${s.check_in_at}</td>
                            <td>${s.expected_check_out_at}</td>
                            <td>S/ ${parseFloat(s.applied_price).toFixed(2)}</td>
                            <td><strong class="text-success">S/ ${parseFloat(s.total_charges || s.applied_price).toFixed(2)}</strong></td>
                            <td><span class="badge badge-primary">ACTIVA</span></td>
                        </tr>
                    `;
                });
            }
        }

        if (staysHtml === '') {
            staysHtml = '<tr><td colspan="8" class="text-center text-muted py-3">No hay estancias activas recientes registradas.</td></tr>';
        }
        historyBody.innerHTML = staysHtml;
    } catch (e) {
        historyBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">Error al cargar historial.</td></tr>';
    }
}

function goToReservationsHistoryForGuest() {
    if (currentProfileGuestDoc) {
        window.location.href = `/reservas?search=${encodeURIComponent(currentProfileGuestDoc)}`;
    }
}

