/**
 * CS-Hotel: Housekeeping & Maintenance Frontend Logic
 */

let activeHkTab = 'all';
let housekeepingTasks = [];
let allRoomsList = [];

document.addEventListener('DOMContentLoaded', () => {
    loadHousekeepingData();
    loadRoomsCatalog();
});

async function loadHousekeepingData() {
    try {
        const [tasksRes, roomsRes] = await Promise.all([
            fetch('/api/housekeeping/tasks'),
            fetch('/api/rooms')
        ]);

        const tasksData = await tasksRes.json();
        const roomsData = await roomsRes.json();

        housekeepingTasks = (tasksData.status === 'success' && Array.isArray(tasksData.data)) ? tasksData.data : [];
        allRoomsList = (roomsData.status === 'success' && Array.isArray(roomsData.data)) ? roomsData.data : [];

        updateHousekeepingMetrics();
        renderHousekeepingTable();
    } catch (e) {
        console.error('Error cargando tareas de limpieza:', e);
    }
}

async function loadRoomsCatalog() {
    try {
        const result = await apiFetch('/api/rooms');

        if (result.data.status === 'success' && Array.isArray(result.data.data)) {
            const select = document.getElementById('task-room-id');
            if (select) {
                select.innerHTML = '<option value="">-- Seleccionar Habitación --</option>';
                result.data.data.forEach(r => {
                    const opt = document.createElement('option');
                    opt.value = r.id;
                    opt.textContent = `Habitación ${r.number} (${r.category_name || r.category}) [${r.status.toUpperCase()}]`;
                    select.appendChild(opt);
                });
            }
        }
    } catch (e) {
        console.error('Error cargando habitaciones para modal:', e);
    }
}

function updateHousekeepingMetrics() {
    const pendingCount = housekeepingTasks.filter(t => t.status === 'pending').length;
    const progressCount = housekeepingTasks.filter(t => t.status === 'in_progress').length;
    const completedCount = housekeepingTasks.filter(t => t.status === 'completed').length;

    const cleaningRooms = allRoomsList.filter(r => r.status === 'cleaning').length;
    const maintenanceRooms = allRoomsList.filter(r => r.status === 'maintenance').length;

    document.getElementById('metric-tasks-pending').textContent = pendingCount;
    document.getElementById('metric-tasks-progress').textContent = progressCount;
    document.getElementById('metric-rooms-cleaning').textContent = cleaningRooms;
    document.getElementById('metric-rooms-maintenance').textContent = maintenanceRooms;

    document.getElementById('tab-count-all').textContent = housekeepingTasks.length;
    document.getElementById('tab-count-pending').textContent = pendingCount;
    document.getElementById('tab-count-progress').textContent = progressCount;
    document.getElementById('tab-count-completed').textContent = completedCount;
}

function filterTasksByTab(status, btn) {
    activeHkTab = status;

    const allButtons = document.querySelectorAll('.housekeeping-tabs-bar .tab-btn');
    allButtons.forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');

    filterTasksTable();
}

function renderHousekeepingTable() {
    const tbody = document.getElementById('table-housekeeping-body');

    if (housekeepingTasks.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">No hay tareas de limpieza o averías registradas.</td></tr>';
        return;
    }

    const typeLabels = {
        'checkout_cleaning': '🧹 Limpieza Post-Checkout',
        'deep_cleaning': '🧼 Limpieza Profunda',
        'maintenance_repair': '🔧 Reparación / Avería',
        'routine_inspection': '🔍 Inspección de Rutina',
    };

    let html = '';
    housekeepingTasks.forEach((task, idx) => {
        const roomNum = task.room?.number || task.room_id;
        const taskType = typeLabels[task.task_type] || task.task_type;

        let statusBadge = '';
        if (task.status === 'pending') {
            statusBadge = '<span class="badge-hk-pending">⏳ PENDIENTE</span>';
        } else if (task.status === 'in_progress') {
            statusBadge = '<span class="badge-hk-in-progress">🧼 EN CURSO</span>';
        } else {
            statusBadge = '<span class="badge-hk-completed">✅ COMPLETADA</span>';
        }

        const priorityBadge = `<span class="badge priority-${task.priority}">${task.priority.toUpperCase()}</span>`;

        let actionBtns = '';
        if (task.status === 'pending') {
            actionBtns += `<button class="btn btn-sm btn-info mr-1" onclick="startHousekeepingTask(${task.id})">▶️ Iniciar</button> `;
        }
        if (task.status !== 'completed') {
            actionBtns += `<button class="btn btn-sm btn-success" onclick="completeHousekeepingTask(${task.id})">✅ Completar y Liberar</button>`;
        } else {
            actionBtns = '<span class="text-muted" style="font-size: 0.8rem;">Finalizada</span>';
        }

        const searchStr = `${task.id} ${roomNum} ${task.task_type} ${task.priority} ${task.notes || ''}`.toLowerCase();

        html += `
            <tr data-status="${task.status}" data-search="${searchStr}">
                <td><strong>#${task.id || (idx + 1)}</strong></td>
                <td><strong>Habitación ${roomNum}</strong></td>
                <td>${taskType}</td>
                <td>${priorityBadge}</td>
                <td>${statusBadge}</td>
                <td>${escapeHtml(task.notes || '—')}</td>
                <td>${actionBtns}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    filterTasksTable();
}

function filterTasksTable() {
    const term = document.getElementById('filter-tasks-input').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#table-housekeeping-body tr');

    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        const rowSearch = row.getAttribute('data-search') || '';

        const matchesTab = activeHkTab === 'all' || rowStatus === activeHkTab;
        const matchesSearch = term === '' || rowSearch.includes(term);

        row.style.display = (matchesTab && matchesSearch) ? '' : 'none';
    });
}

// ─── ACTIONS: START & COMPLETE ─────────────────────────────

async function startHousekeepingTask(taskId) {
    try {
        const result = await apiFetch('/api/housekeeping/tasks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'start',
                task_id: taskId,
            })
        });


        if (result.ok && result.data.status === 'success') {
            if (typeof showToast === 'function') {
                showToast('✅ Tarea de limpieza iniciada.', 'success');
            }
            loadHousekeepingData();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al iniciar tarea.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión al iniciar tarea.', 'error');
        }
    }
}

async function completeHousekeepingTask(taskId) {
    try {
        const result = await apiFetch('/api/housekeeping/tasks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'complete',
                task_id: taskId,
            })
        });


        if (result.ok && result.data.status === 'success') {
            if (typeof showToast === 'function') {
                showToast('✅ Tarea completada y habitación liberada a DISPONIBLE.', 'success');
            }
            loadHousekeepingData();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al completar tarea.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión al completar tarea.', 'error');
        }
    }
}

// ─── CREATE TASK ───────────────────────────────────────────

async function handleCreateTaskSubmit(event) {
    event.preventDefault();

    const roomId = parseInt(document.getElementById('task-room-id').value, 10);
    const taskType = document.getElementById('task-type').value;
    const priority = document.getElementById('task-priority').value;
    const notes = document.getElementById('task-notes').value.trim() || null;

    if (!roomId) {
        if (typeof showToast === 'function') {
            showToast('Seleccione una habitación.', 'warning');
        }
        return;
    }

    const btn = document.getElementById('btn-submit-task');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Registrando...';
    }

    try {
        const result = await apiFetch('/api/housekeeping/tasks', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                room_id: roomId,
                task_type: taskType,
                priority: priority,
                notes: notes,
            }),
        });


        if (result.ok && result.data.status === 'success') {
            closeModal('modal-create-task');
            document.getElementById('form-create-task').reset();
            if (typeof showToast === 'function') {
                showToast('✅ Tarea registrada exitosamente.', 'success');
            }
            loadHousekeepingData();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al registrar tarea.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '💾 Asignar y Registrar Tarea';
        }
    }
}

// ─── UTILITIES ─────────────────────────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
