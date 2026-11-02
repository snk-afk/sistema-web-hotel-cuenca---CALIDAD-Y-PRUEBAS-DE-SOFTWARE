function switchRoomsModuleTab(tabId, btnElement) {
    const contents = document.querySelectorAll('.rooms-tab-content');
    contents.forEach(c => c.style.display = 'none');

    const activeContent = document.getElementById(tabId);
    if (activeContent) {
        activeContent.style.display = 'block';
    }

    const tabBtns = document.querySelectorAll('.rooms-nav-tabs .drawer-tab');
    tabBtns.forEach(b => b.classList.remove('active'));

    if (btnElement) {
        btnElement.classList.add('active');
    }
}

// ==========================================
// 🏨 FILTERS
// ==========================================
function filterRoomsTable() {
    const searchInput = document.getElementById('filter-rooms-input');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const catFilter = document.getElementById('filter-rooms-category');
    const selectedCat = catFilter ? catFilter.value.toLowerCase().trim() : '';

    const rows = document.querySelectorAll('#table-rooms-inventory tbody .room-row-item');
    rows.forEach(row => {
        const num = (row.getAttribute('data-number') || '').toLowerCase();
        const floor = (row.getAttribute('data-floor') || '').toLowerCase();
        const cat = (row.getAttribute('data-category') || '').toLowerCase();
        const status = (row.getAttribute('data-status') || '').toLowerCase();

        const matchesQuery = query === '' || num.includes(query) || floor.includes(query) || cat.includes(query) || status.includes(query);
        const matchesCat = selectedCat === '' || cat === selectedCat;

        if (matchesQuery && matchesCat) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function filterCategoriesTable() {
    const searchInput = document.getElementById('filter-cats-input');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const rows = document.querySelectorAll('#table-categories tbody .category-row-item');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = query === '' || text.includes(query) ? '' : 'none';
    });
}

function filterTariffsTable() {
    const searchInput = document.getElementById('filter-tariffs-input');
    const query = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const rows = document.querySelectorAll('#table-tariffs tbody .tariff-row-item');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = query === '' || text.includes(query) ? '' : 'none';
    });
}

// ==========================================
// 🏨 ROOMS CRUD
// ==========================================
function openCreateRoomModal() {
    document.getElementById('modal-room-title').textContent = '➕ Registrar Nueva Habitación';
    document.getElementById('room-form-id').value = '';
    document.getElementById('room-form-number').value = '';
    document.getElementById('room-form-floor').value = '1';
    document.getElementById('room-form-description').value = '';
    document.getElementById('btn-save-room').textContent = 'Guardar Habitación';
    openModal('modal-room-form');
}

function openEditRoomModal(id, number, floor, categoryId, description) {
    document.getElementById('modal-room-title').textContent = `✏️ Editar Habitación ${number}`;
    document.getElementById('room-form-id').value = id;
    document.getElementById('room-form-number').value = number;
    document.getElementById('room-form-floor').value = floor;
    document.getElementById('room-form-category-id').value = categoryId;
    document.getElementById('room-form-description').value = description || '';
    document.getElementById('btn-save-room').textContent = 'Actualizar Habitación';
    openModal('modal-room-form');
}

async function handleRoomFormSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('room-form-id').value;
    const number = document.getElementById('room-form-number').value.trim();
    const floor = document.getElementById('room-form-floor').value.trim();
    const categoryId = parseInt(document.getElementById('room-form-category-id').value, 10);
    const description = document.getElementById('room-form-description').value.trim();

    const isEdit = Boolean(id);
    const action = isEdit ? 'update' : 'create';

    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: action,
                room_id: id ? parseInt(id, 10) : undefined,
                room_category_id: categoryId,
                number: number,
                floor: floor,
                description: description,
            })
        });

        if (res.ok) {
            showToast(res.data.message || (isEdit ? 'Habitación actualizada exitosamente' : 'Habitación registrada exitosamente'));
            closeModal('modal-room-form');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'Error al guardar habitación', 'error');
        }
    } catch (err) {
        showToast('Error de comunicación con el servidor', 'error');
    }
}

async function setRoomMaintenance(roomId) {
    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: 'update_status',
                room_id: roomId,
                status: 'maintenance',
            })
        });

        if (res.ok) {
            showToast('🔧 Habitación puesta en estado de mantenimiento / avería');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'No se pudo cambiar el estado', 'error');
        }
    } catch (e) {
        showToast('Error de comunicación', 'error');
    }
}

async function releaseRoomMaintenance(roomId) {
    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: 'update_status',
                room_id: roomId,
                status: 'free',
            })
        });

        if (res.ok) {
            showToast('✨ Habitación habilitada y disponible para hospedaje');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'Error al habilitar habitación', 'error');
        }
    } catch (e) {
        showToast('Error al habilitar habitación', 'error');
    }
}

// ==========================================
// 🏷️ CATEGORIES CRUD
// ==========================================
function openCreateCategoryModal() {
    document.getElementById('modal-cat-title').textContent = '🏷️ Registrar Nueva Categoría';
    document.getElementById('cat-form-id').value = '';
    document.getElementById('cat-form-name').value = '';
    document.getElementById('cat-form-desc').value = '';
    document.getElementById('group-cat-active').style.display = 'none';
    document.getElementById('btn-save-cat').textContent = 'Guardar Categoría';
    openModal('modal-category-form');
}

function openEditCategoryModal(id, name, description, isActive) {
    document.getElementById('modal-cat-title').textContent = `✏️ Editar Categoría: ${name}`;
    document.getElementById('cat-form-id').value = id;
    document.getElementById('cat-form-name').value = name;
    document.getElementById('cat-form-desc').value = description || '';
    document.getElementById('cat-form-active').value = isActive;
    document.getElementById('group-cat-active').style.display = 'block';
    document.getElementById('btn-save-cat').textContent = 'Actualizar Categoría';
    openModal('modal-category-form');
}

async function handleCategoryFormSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('cat-form-id').value;
    const name = document.getElementById('cat-form-name').value.trim();
    const description = document.getElementById('cat-form-desc').value.trim();
    const isActive = document.getElementById('cat-form-active').value;

    try {
        const res = await apiFetch('/api/categories', {
            method: 'POST',
            body: JSON.stringify({
                id: id ? parseInt(id, 10) : undefined,
                name: name,
                description: description,
                is_active: parseInt(isActive, 10)
            })
        });

        if (res.ok) {
            showToast(res.data.message || 'Categoría guardada exitosamente');
            closeModal('modal-category-form');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'Error al guardar categoría', 'error');
        }
    } catch (err) {
        showToast('Error de comunicación con el servidor', 'error');
    }
}

// ==========================================
// 💵 TARIFFS CRUD
// ==========================================
function openCreateTariffModal() {
    document.getElementById('modal-tariff-title').textContent = '💵 Registrar Nueva Tarifa';
    document.getElementById('tariff-form-id').value = '';
    document.getElementById('tariff-form-name').value = '';
    document.getElementById('tariff-form-hours').value = '12';
    document.getElementById('tariff-form-price').value = '45.00';
    document.getElementById('group-tariff-active').style.display = 'none';
    document.getElementById('btn-save-tariff').textContent = 'Guardar Tarifa';
    openModal('modal-tariff-form');
}

function openEditTariffModal(id, categoryId, name, hours, price, isActive) {
    document.getElementById('modal-tariff-title').textContent = `✏️ Editar Tarifa: ${name}`;
    document.getElementById('tariff-form-id').value = id;
    document.getElementById('tariff-form-cat-id').value = categoryId;
    document.getElementById('tariff-form-name').value = name;
    document.getElementById('tariff-form-hours').value = hours;
    document.getElementById('tariff-form-price').value = parseFloat(price).toFixed(2);
    document.getElementById('tariff-form-active').value = isActive;
    document.getElementById('group-tariff-active').style.display = 'block';
    document.getElementById('btn-save-tariff').textContent = 'Actualizar Tarifa';
    openModal('modal-tariff-form');
}

async function handleTariffFormSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('tariff-form-id').value;
    const categoryId = parseInt(document.getElementById('tariff-form-cat-id').value, 10);
    const name = document.getElementById('tariff-form-name').value.trim();
    const hours = parseInt(document.getElementById('tariff-form-hours').value, 10);
    const price = parseFloat(document.getElementById('tariff-form-price').value);
    const isActive = document.getElementById('tariff-form-active').value;

    try {
        const res = await apiFetch('/api/tariffs', {
            method: 'POST',
            body: JSON.stringify({
                id: id ? parseInt(id, 10) : undefined,
                room_category_id: categoryId,
                name: name,
                duration_hours: hours,
                price: price,
                is_active: parseInt(isActive, 10)
            })
        });

        if (res.ok) {
            showToast(res.data.message || 'Tarifa guardada exitosamente');
            closeModal('modal-tariff-form');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'Error al guardar tarifa', 'error');
        }
    } catch (err) {
        showToast('Error de comunicación con el servidor', 'error');
    }
}
