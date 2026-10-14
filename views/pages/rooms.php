<?php
declare(strict_types=1);

$page_title = 'Administración de Habitaciones';
$page_styles = ['/assets/css/pages/rooms.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>🏨 Gestión de Habitaciones y Tarifas</h2>
        <p class="text-muted">Control integral de habitaciones, categorías y tarifas por categoría.</p>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="card p-0 mb-3">
    <div class="drawer-tabs rooms-nav-tabs">
        <button class="drawer-tab active" id="btn-tab-rooms" onclick="switchRoomsModuleTab('tab-rooms-list', this)">🏨 Habitaciones (<?= count($rooms) ?>)</button>
        <button class="drawer-tab" id="btn-tab-cats" onclick="switchRoomsModuleTab('tab-categories-list', this)">🏷️ Categorías (<?= count($categories) ?>)</button>
        <button class="drawer-tab" id="btn-tab-tariffs" onclick="switchRoomsModuleTab('tab-tariffs-list', this)">💵 Tarifas & Precios (<?= count($tariffs) ?>)</button>
    </div>
</div>

<!-- TAB 1: HABITACIONES -->
<div id="tab-rooms-list" class="rooms-tab-content active">
    <div class="card">
        <div class="rooms-filter-bar mb-3">
            <div class="search-box flex-1">
                <span class="search-icon">🔍</span>
                <input type="text" id="filter-rooms-input" class="search-input" placeholder="Buscar por número de habitación, piso o categoría..." onkeyup="filterRoomsTable()">
            </div>
            <div class="d-flex gap-2">
                <select id="filter-rooms-category" class="form-control" onchange="filterRoomsTable()" style="width: auto;">
                    <option value="">Todas las Categorías</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" onclick="openCreateRoomModal()">➕ Nueva Habitación</button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="table-rooms-inventory">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>N° Habitación</th>
                        <th>Piso / Nivel</th>
                        <th>Categoría</th>
                        <th>Estado Operativo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rooms as $room): ?>
                        <?php
                            $r_id = (int) ($room['id'] ?? 0);
                            $r_num = (string) ($room['number'] ?? '');
                            $r_floor = (string) ($room['floor'] ?? '1');
                            $r_cat_name = (string) ($room['category'] ?? $room['category_name'] ?? 'General');
                            $r_cat_id = (int) ($room['room_category_id'] ?? 1);
                            $r_status = (string) ($room['status'] ?? 'free');
                            $r_desc = (string) ($room['description'] ?? '');
                        ?>
                        <tr class="room-row-item" data-number="<?= htmlspecialchars($r_num, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" data-floor="<?= htmlspecialchars($r_floor, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" data-category="<?= htmlspecialchars($r_cat_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" data-status="<?= htmlspecialchars($r_status, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                            <td>#<?= $r_id ?></td>
                            <td><strong style="font-size: 1.05rem; color: #ffffff;">Hab. <?= htmlspecialchars($r_num, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong></td>
                            <td>Piso <?= htmlspecialchars($r_floor, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></td>
                            <td><span class="category-chip">🏷️ <?= htmlspecialchars($r_cat_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span></td>
                            <td>
                                <?php if ($r_status === 'free'): ?>
                                    <span class="badge badge-success">🟢 LIBRE</span>
                                <?php elseif ($r_status === 'occupied'): ?>
                                    <span class="badge badge-primary">🔵 OCUPADA</span>
                                <?php elseif ($r_status === 'cleaning'): ?>
                                    <span class="badge badge-warning">🧹 LIMPIEZA</span>
                                <?php elseif ($r_status === 'maintenance'): ?>
                                    <span class="badge badge-secondary">🔧 AVERÍA</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary"><?= strtoupper($r_status) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="/reservas?search=<?= urlencode($r_num) ?>" class="btn btn-sm btn-outline-info" title="Ver Historial de Reservas de esta Habitación">📅 Reservas</a>
                                    <button class="btn btn-sm btn-secondary" onclick="openEditRoomModal(<?= $r_id ?>, '<?= htmlspecialchars($r_num, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', '<?= htmlspecialchars($r_floor, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', <?= $r_cat_id ?>, '<?= htmlspecialchars($r_desc, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>')">✏️ Editar</button>
                                    <?php if ($r_status !== 'maintenance'): ?>
                                        <button class="btn btn-sm btn-outline-warning" onclick="setRoomMaintenance(<?= $r_id ?>)">🛠️ Avería</button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success" onclick="releaseRoomMaintenance(<?= $r_id ?>)">✨ Habilitar</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB 2: CATEGORÍAS -->
<div id="tab-categories-list" class="rooms-tab-content" style="display: none;">
    <div class="card">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="search-box" style="max-width: 400px;">
                <span class="search-icon">🔍</span>
                <input type="text" id="filter-cats-input" class="search-input" placeholder="Buscar categoría..." onkeyup="filterCategoriesTable()">
            </div>
            <button class="btn btn-primary" onclick="openCreateCategoryModal()">➕ Nueva Categoría</button>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="table-categories">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre de Categoría</th>
                        <th>Descripción</th>
                        <th>Habitaciones Asociadas</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr class="category-row-item">
                            <td>#<?= $cat['id'] ?></td>
                            <td><strong style="font-size: 1.05rem; color: #ffffff;"><?= htmlspecialchars($cat['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong></td>
                            <td><?= htmlspecialchars($cat['description'] ?? '---', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></td>
                            <td><span class="badge badge-info"><?= $cat['rooms_count'] ?? 0 ?> Habitaciones</span></td>
                            <td>
                                <?php if (!empty($cat['is_active'])): ?>
                                    <span class="badge badge-success">ACTIVA</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">INACTIVA</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick="openEditCategoryModal(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', '<?= htmlspecialchars($cat['description'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', <?= !empty($cat['is_active']) ? 1 : 0 ?>)">✏️ Editar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- TAB 3: TARIFAS -->
<div id="tab-tariffs-list" class="rooms-tab-content" style="display: none;">
    <div class="card">
        <div class="rooms-filter-bar mb-3">
            <div class="search-box flex-1">
                <span class="search-icon">🔍</span>
                <input type="text" id="filter-tariffs-input" class="search-input" placeholder="Buscar tarifa o precio..." onkeyup="filterTariffsTable()">
            </div>
            <button class="btn btn-primary" onclick="openCreateTariffModal()">➕ Nueva Tarifa</button>
        </div>

        <div class="table-responsive">
            <table class="data-table" id="table-tariffs">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Categoría</th>
                        <th>Nombre de Tarifa</th>
                        <th>Duración</th>
                        <th>Precio Base</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tariffs as $tariff): ?>
                        <tr class="tariff-row-item">
                            <td>#<?= $tariff['id'] ?></td>
                            <td><span class="category-chip">🏷️ <?= htmlspecialchars($tariff['category_name'] ?? 'General', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span></td>
                            <td><strong><?= htmlspecialchars($tariff['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong></td>
                            <td><span class="badge badge-secondary">⏱️ <?= $tariff['duration_hours'] ?> Horas</span></td>
                            <td><strong class="text-success" style="font-size: 1.05rem;">S/ <?= number_format((float) $tariff['price'], 2) ?></strong></td>
                            <td>
                                <?php if (!empty($tariff['is_active'])): ?>
                                    <span class="badge badge-success">ACTIVA</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">INACTIVA</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-secondary" onclick="openEditTariffModal(<?= $tariff['id'] ?>, <?= $tariff['room_category_id'] ?>, '<?= htmlspecialchars($tariff['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', <?= $tariff['duration_hours'] ?>, <?= $tariff['price'] ?>, <?= !empty($tariff['is_active']) ? 1 : 0 ?>)">✏️ Editar</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL 1: CREAR / EDITAR HABITACIÓN -->
<div id="modal-room-form" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('modal-room-form')">&times;</span>
        <h3 id="modal-room-title">➕ Registrar Nueva Habitación</h3>
        <form onsubmit="handleRoomFormSubmit(event)">
            <input type="hidden" id="room-form-id">

            <div class="form-group">
                <label>Número de Habitación:</label>
                <input type="text" id="room-form-number" class="form-control" placeholder="Ej: 101, 204, 305" required>
            </div>

            <div class="form-group">
                <label>Piso / Nivel:</label>
                <input type="text" id="room-form-floor" class="form-control" placeholder="Ej: 1, 2, 3" required>
            </div>

            <div class="form-group">
                <label>Categoría de Habitación:</label>
                <select id="room-form-category-id" class="form-control" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Descripción / Características (Opcional):</label>
                <input type="text" id="room-form-description" class="form-control" placeholder="Ej: Cama King, vista a la calle, TV 55'">
            </div>

            <button type="submit" class="btn btn-success btn-block mt-3" id="btn-save-room">Guardar Habitación</button>
        </form>
    </div>
</div>

<!-- MODAL 2: CREAR / EDITAR CATEGORÍA -->
<div id="modal-category-form" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('modal-category-form')">&times;</span>
        <h3 id="modal-cat-title">🏷️ Registrar Nueva Categoría</h3>
        <form onsubmit="handleCategoryFormSubmit(event)">
            <input type="hidden" id="cat-form-id">

            <div class="form-group">
                <label>Nombre de la Categoría:</label>
                <input type="text" id="cat-form-name" class="form-control" placeholder="Ej: Matrimonial Deluxe" required>
            </div>

            <div class="form-group">
                <label>Descripción:</label>
                <input type="text" id="cat-form-desc" class="form-control" placeholder="Detalles o equipamiento de esta categoría">
            </div>

            <div class="form-group" id="group-cat-active" style="display: none;">
                <label>Estado:</label>
                <select id="cat-form-active" class="form-control">
                    <option value="1">Activa</option>
                    <option value="0">Inactiva</option>
                </select>
            </div>

            <button type="submit" class="btn btn-success btn-block mt-3" id="btn-save-cat">Guardar Categoría</button>
        </form>
    </div>
</div>

<!-- MODAL 3: CREAR / EDITAR TARIFA -->
<div id="modal-tariff-form" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('modal-tariff-form')">&times;</span>
        <h3 id="modal-tariff-title">💵 Registrar Nueva Tarifa</h3>
        <form onsubmit="handleTariffFormSubmit(event)">
            <input type="hidden" id="tariff-form-id">

            <div class="form-group">
                <label>Categoría Asociada:</label>
                <select id="tariff-form-cat-id" class="form-control" required>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Nombre de la Tarifa:</label>
                <input type="text" id="tariff-form-name" class="form-control" placeholder="Ej: Tarifa Noche, Estándar 12h" required>
            </div>

            <div class="form-group">
                <label>Duración en Horas:</label>
                <input type="number" id="tariff-form-hours" class="form-control" value="12" min="1" max="72" required>
            </div>

            <div class="form-group">
                <label>Precio Base Oficial (S/):</label>
                <input type="number" step="0.50" id="tariff-form-price" class="form-control" value="45.00" min="1" required>
            </div>

            <div class="form-group" id="group-tariff-active" style="display: none;">
                <label>Estado:</label>
                <select id="tariff-form-active" class="form-control">
                    <option value="1">Activa</option>
                    <option value="0">Inactiva</option>
                </select>
            </div>

            <button type="submit" class="btn btn-success btn-block mt-3" id="btn-save-tariff">Guardar Tarifa</button>
        </form>
    </div>
</div>

<script src="/assets/js/pages/rooms.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
