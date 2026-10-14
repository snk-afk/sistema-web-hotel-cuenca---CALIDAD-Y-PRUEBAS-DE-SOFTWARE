/**
 * CS-Hotel: Users & Roles RBAC Frontend Logic
 */

let systemUsersList = [];

document.addEventListener('DOMContentLoaded', () => {
    loadUsersData();
});

async function loadUsersData() {
    try {
        const result = await apiFetch('/api/users');

        if (result.data.status === 'success' && Array.isArray(result.data.data)) {
            systemUsersList = result.data.data;
            updateUsersMetrics();
            renderUsersTable();
        }
    } catch (e) {
        console.error('Error cargando usuarios:', e);
    }
}

function updateUsersMetrics() {
    const total = systemUsersList.length;
    const active = systemUsersList.filter(u => u.is_active).length;

    document.getElementById('metric-users-total').textContent = total;
    document.getElementById('metric-users-active').textContent = active;
}

function renderUsersTable() {
    const tbody = document.getElementById('table-users-body');

    if (systemUsersList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">No hay usuarios registrados aún.</td></tr>';
        return;
    }

    let html = '';
    systemUsersList.forEach((user, idx) => {
        const roleName = user.role?.name || (user.role_id === 1 ? 'Administrador' : (user.role_id === 2 ? 'Recepcionista' : (user.role_id === 3 ? 'Limpieza' : (user.role_id === 4 ? 'Mantenimiento' : 'Usuario'))));

        let roleBadge = '';
        if (roleName.toLowerCase().includes('admin')) {
            roleBadge = `<span class="badge-role-admin">🛡️ ${escapeHtml(roleName)}</span>`;
        } else if (roleName.toLowerCase().includes('recep')) {
            roleBadge = `<span class="badge-role-receptionist">🔑 ${escapeHtml(roleName)}</span>`;
        } else if (roleName.toLowerCase().includes('limpieza') || roleName.toLowerCase().includes('house')) {
            roleBadge = `<span class="badge-role-housekeeping">🧹 ${escapeHtml(roleName)}</span>`;
        } else if (roleName.toLowerCase().includes('manten')) {
            roleBadge = `<span class="badge-role-maintenance">🔧 ${escapeHtml(roleName)}</span>`;
        } else {
            roleBadge = `<span class="badge-role-default">👤 ${escapeHtml(roleName)}</span>`;
        }

        const statusBadge = user.is_active
            ? '<span class="badge badge-success">🟢 Activo</span>'
            : '<span class="badge badge-danger">🔴 Inactivo</span>';

        const searchStr = `${user.id} ${user.username} ${user.full_name} ${user.email} ${roleName}`.toLowerCase();

        html += `
            <tr data-search="${searchStr}">
                <td><strong>#${user.id || (idx + 1)}</strong></td>
                <td><strong>${escapeHtml(user.username)}</strong></td>
                <td>${escapeHtml(user.full_name)}</td>
                <td>${escapeHtml(user.email)}</td>
                <td>${roleBadge}</td>
                <td>${statusBadge}</td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
    filterUsersTable();
}

function filterUsersTable() {
    const term = document.getElementById('filter-users-input').value.toLowerCase().trim();
    const rows = document.querySelectorAll('#table-users-body tr');

    rows.forEach(row => {
        const rowSearch = row.getAttribute('data-search') || '';
        row.style.display = (term === '' || rowSearch.includes(term)) ? '' : 'none';
    });
}

// ─── CREATE USER ───────────────────────────────────────────

async function handleCreateUserSubmit(event) {
    event.preventDefault();

    const fullName = document.getElementById('user-fullname').value.trim();
    const username = document.getElementById('user-username').value.trim();
    const roleId = parseInt(document.getElementById('user-role-id').value, 10);
    const email = document.getElementById('user-email').value.trim();
    const password = document.getElementById('user-password').value;

    if (!fullName || !username || !email || !password) {
        if (typeof showToast === 'function') {
            showToast('Complete todos los campos requeridos.', 'warning');
        }
        return;
    }

    const btn = document.getElementById('btn-submit-user');
    if (btn) {
        btn.disabled = true;
        btn.textContent = '⏳ Registrando...';
    }

    try {
        const result = await apiFetch('/api/users', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                full_name: fullName,
                username: username,
                role_id: roleId,
                email: email,
                password: password,
            }),
        });


        if (result.ok && result.data.status === 'success') {
            closeModal('modal-create-user');
            document.getElementById('form-create-user').reset();
            if (typeof showToast === 'function') {
                showToast('✅ Usuario creado exitosamente.', 'success');
            }
            loadUsersData();
        } else {
            if (typeof showToast === 'function') {
                showToast(`❌ ${result.data.message || 'Error al crear usuario.'}`, 'error');
            }
        }
    } catch (e) {
        if (typeof showToast === 'function') {
            showToast('❌ Error de conexión.', 'error');
        }
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.textContent = '💾 Crear Usuario y Asignar Rol';
        }
    }
}

// ─── UTILITIES ─────────────────────────────────────────────

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
