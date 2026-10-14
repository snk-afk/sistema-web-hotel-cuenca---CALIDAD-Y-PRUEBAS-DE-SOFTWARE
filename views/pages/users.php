<?php
declare(strict_types=1);

$page_title = 'Usuarios y Roles';
$page_styles = ['/assets/css/pages/users.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>👤 Usuarios, Roles y Bitácora de Auditoría</h2>
        <p class="text-muted">Usuarios del Sistema, Permisos (RBAC) y Seguridad: gestión de credenciales, roles y control de accesos.</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="openModal('modal-create-user')">➕ Nuevo Usuario</button>
    </div>
</div>

<!-- 3 Metrics Cards -->
<div class="users-metrics-grid mb-3">
    <div class="users-metric-card">
        <div class="metric-icon bg-primary-soft">👥</div>
        <div class="metric-info">
            <span class="metric-title">Usuarios Registrados</span>
            <span class="metric-value" id="metric-users-total">0</span>
        </div>
    </div>

    <div class="users-metric-card">
        <div class="metric-icon bg-success-soft">🟢</div>
        <div class="metric-info">
            <span class="metric-title">Usuarios Activos</span>
            <span class="metric-value text-success" id="metric-users-active">0</span>
        </div>
    </div>

    <div class="users-metric-card">
        <div class="metric-icon bg-warning-soft">🛡️</div>
        <div class="metric-info">
            <span class="metric-title">Roles del Sistema</span>
            <span class="metric-value" id="metric-roles-total">4</span>
        </div>
    </div>
</div>

<!-- Main Table Card with Search -->
<div class="card">
    <!-- Search Bar -->
    <div class="reservations-filter-bar mb-3">
        <div class="search-box flex-1">
            <span class="search-icon">🔍</span>
            <input type="text" id="filter-users-input" class="search-input" placeholder="Buscar usuario por nombre, login, correo o rol..." onkeyup="filterUsersTable()">
        </div>
    </div>

    <!-- Users Table -->
    <div class="table-responsive">
        <table class="table" id="table-users">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Usuario (Login)</th>
                    <th>Nombre Completo</th>
                    <th>Correo Electrónico</th>
                    <th>Rol de Acceso</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody id="table-users-body">
                <tr><td colspan="6" class="text-center text-muted py-3">Cargando usuarios del sistema...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: CREAR USUARIO -->
<div id="modal-create-user" class="modal">
    <div class="modal-content" style="max-width: 520px;">
        <span class="close" onclick="closeModal('modal-create-user')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">➕ Registrar Nuevo Usuario</h3>

        <form id="form-create-user" onsubmit="handleCreateUserSubmit(event)">
            <div class="form-group">
                <label>Nombre Completo *</label>
                <input type="text" id="user-fullname" class="form-control" placeholder="Ej: Carlos Mendoza Ramos" required>
            </div>

            <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group">
                    <label>Nombre de Usuario (Login) *</label>
                    <input type="text" id="user-username" class="form-control" placeholder="Ej: cmendoza" required>
                </div>
                <div class="form-group">
                    <label>Rol del Sistema *</label>
                    <select id="user-role-id" class="form-control" required>
                        <option value="1">🛡️ Administrador</option>
                        <option value="2" selected>🔑 Recepcionista</option>
                        <option value="3">🧹 Limpieza / Housekeeping</option>
                        <option value="4">🔧 Mantenimiento</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label>Correo Electrónico *</label>
                <input type="email" id="user-email" class="form-control" placeholder="Ej: cmendoza@cshotel.com" required>
            </div>

            <div class="form-group">
                <label>Contraseña de Acceso *</label>
                <input type="password" id="user-password" class="form-control" placeholder="••••••••" required minlength="6">
            </div>

            <button type="submit" id="btn-submit-user" class="btn btn-primary btn-block mt-3">💾 Crear Usuario y Asignar Rol</button>
        </form>
    </div>
</div>

<script src="/assets/js/pages/users.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
