<?php
declare(strict_types=1);

$page_title = 'Limpieza y Mantenimiento';
$page_styles = ['/assets/css/pages/housekeeping.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>🧹 Tareas de Limpieza y Mantenimiento</h2>
        <p class="text-muted">Tareas de Limpieza (Housekeeping) y Averías: gestión de turnos de aseo, reportes y liberación automática de habitaciones.</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-warning" onclick="openModal('modal-create-task')">➕ Nueva Tarea / Avería</button>
        <a href="/" class="btn btn-primary">📊 Ir al Mapa de Habitaciones</a>
    </div>
</div>

<!-- 4 Metrics Cards -->
<div class="housekeeping-metrics-grid mb-3">
    <div class="housekeeping-metric-card">
        <div class="metric-icon bg-warning-soft">⏳</div>
        <div class="metric-info">
            <span class="metric-title">Tareas Pendientes</span>
            <span class="metric-value" id="metric-tasks-pending">0</span>
        </div>
    </div>

    <div class="housekeeping-metric-card">
        <div class="metric-icon bg-primary-soft">🧼</div>
        <div class="metric-info">
            <span class="metric-title">En Progreso</span>
            <span class="metric-value" id="metric-tasks-progress">0</span>
        </div>
    </div>

    <div class="housekeeping-metric-card">
        <div class="metric-icon bg-danger-soft">🧹</div>
        <div class="metric-info">
            <span class="metric-title">Habitaciones en Limpieza</span>
            <span class="metric-value" id="metric-rooms-cleaning">0</span>
        </div>
    </div>

    <div class="housekeeping-metric-card">
        <div class="metric-icon bg-success-soft">🔧</div>
        <div class="metric-info">
            <span class="metric-title">En Mantenimiento</span>
            <span class="metric-value" id="metric-rooms-maintenance">0</span>
        </div>
    </div>
</div>

<!-- Main Table Card with Tabs & Search -->
<div class="card">
    <div class="housekeeping-tabs-bar">
        <button class="tab-btn active" data-status="all" onclick="filterTasksByTab('all', this)">
            Todas <span class="tab-counter" id="tab-count-all">0</span>
        </button>
        <button class="tab-btn" data-status="pending" onclick="filterTasksByTab('pending', this)">
            ⏳ Pendientes <span class="tab-counter" id="tab-count-pending">0</span>
        </button>
        <button class="tab-btn" data-status="in_progress" onclick="filterTasksByTab('in_progress', this)">
            🧼 En Curso <span class="tab-counter" id="tab-count-progress">0</span>
        </button>
        <button class="tab-btn" data-status="completed" onclick="filterTasksByTab('completed', this)">
            ✅ Completadas <span class="tab-counter" id="tab-count-completed">0</span>
        </button>
    </div>

    <!-- Search Bar -->
    <div class="reservations-filter-bar mb-3">
        <div class="search-box flex-1">
            <span class="search-icon">🔍</span>
            <input type="text" id="filter-tasks-input" class="search-input" placeholder="Buscar por habitación, notas, tipo o prioridad..." onkeyup="filterTasksTable()">
        </div>
    </div>

    <!-- Tasks Table -->
    <div class="table-responsive">
        <table class="table" id="table-housekeeping">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Habitación</th>
                    <th>Tipo de Tarea</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Reporte / Notas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="table-housekeeping-body">
                <tr><td colspan="7" class="text-center text-muted py-3">Cargando tareas de limpieza...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: NUEVA TAREA / AVERÍA -->
<div id="modal-create-task" class="modal">
    <div class="modal-content" style="max-width: 540px;">
        <span class="close" onclick="closeModal('modal-create-task')">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 1rem;">➕ Registrar Tarea / Reporte de Avería</h3>

        <form id="form-create-task" onsubmit="handleCreateTaskSubmit(event)">
            <div class="form-group">
                <label>Habitación Destino *</label>
                <select id="task-room-id" class="form-control" required>
                    <option value="">Cargando habitaciones...</option>
                </select>
            </div>

            <div class="form-group">
                <label>Tipo de Tarea *</label>
                <select id="task-type" class="form-control" required>
                    <option value="checkout_cleaning">🧹 Limpieza Post-Checkout</option>
                    <option value="deep_cleaning">🧼 Limpieza Profunda / Desinfección</option>
                    <option value="maintenance_repair">🔧 Reparación de Mantenimiento / Avería</option>
                    <option value="routine_inspection">🔍 Inspección de Rutina</option>
                </select>
            </div>

            <div class="form-group">
                <label>Nivel de Prioridad *</label>
                <select id="task-priority" class="form-control" required>
                    <option value="normal">⚪ Normal</option>
                    <option value="high">🟡 Alta</option>
                    <option value="urgent">🔴 Urgente / Bloqueante</option>
                    <option value="low">🔵 Baja</option>
                </select>
            </div>

            <div class="form-group">
                <label>Descripción / Observaciones del Trabajo:</label>
                <textarea id="task-notes" class="form-control" rows="3" placeholder="Ej: Grifería de ducha gotea, cambiar empaque..."></textarea>
            </div>

            <button type="submit" id="btn-submit-task" class="btn btn-warning btn-block mt-3">💾 Asignar y Registrar Tarea</button>
        </form>
    </div>
</div>

<script src="/assets/js/pages/housekeeping.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
