<?php
declare(strict_types=1);

$page_title = 'Directorio Maestro de Huéspedes';
$page_styles = ['/assets/css/pages/guests.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>🪪 Directorio Maestro de Huéspedes & CRM</h2>
        <p class="text-muted">Padrón central de clientes, historial de visitas y métricas de consumo acumulado.</p>
    </div>
    <button class="btn btn-primary" onclick="openCreateGuestModal()">➕ Nuevo Huésped</button>
</div>

<!-- Metrics Cards -->
<div class="guest-metrics-grid mb-3">
    <div class="guest-metric-card">
        <div class="metric-icon bg-primary-soft">👥</div>
        <div class="metric-info">
            <span class="metric-title">Total Huéspedes</span>
            <span class="metric-value"><?= (int) ($metrics['total_guests'] ?? 0) ?></span>
        </div>
    </div>

    <div class="guest-metric-card">
        <div class="metric-icon bg-warning-soft">⭐</div>
        <div class="metric-info">
            <span class="metric-title">Clientes Frecuentes / VIP</span>
            <span class="metric-value"><?= (int) ($metrics['vip_count'] ?? 0) ?></span>
        </div>
    </div>

    <div class="guest-metric-card">
        <div class="metric-icon bg-info-soft">🏨</div>
        <div class="metric-info">
            <span class="metric-title">Visitas Registradas</span>
            <span class="metric-value"><?= (int) ($metrics['total_stays'] ?? 0) ?></span>
        </div>
    </div>

    <div class="guest-metric-card">
        <div class="metric-icon bg-success-soft">💳</div>
        <div class="metric-info">
            <span class="metric-title">Facturación Acumulada</span>
            <span class="metric-value">S/ <?= number_format((float) ($metrics['total_spent'] ?? 0), 2) ?></span>
        </div>
    </div>
</div>

<!-- Guests Table Card -->
<div class="card">
    <div class="guests-filter-bar mb-3">
        <div class="search-box flex-1">
            <span class="search-icon">🔍</span>
            <input type="text" id="filter-guests-input" class="search-input" placeholder="Buscar por DNI/RUC, nombre del cliente, teléfono o notas..." onkeyup="filterGuestsTable()">
        </div>
        <div class="d-flex gap-2">
            <select id="filter-guests-type" class="form-control" onchange="filterGuestsTable()" style="width: auto;">
                <option value="">Todos los Documentos</option>
                <option value="DNI">Solo DNI</option>
                <option value="RUC">Solo RUC</option>
                <option value="CE">Carnet Extranjería</option>
                <option value="PASAPORTE">Pasaporte</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="data-table" id="table-guests-directory">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Documento</th>
                    <th>Huésped / Razón Social</th>
                    <th>Contacto (Tel / Correo)</th>
                    <th>Visitas</th>
                    <th>Total Acumulado</th>
                    <th>Notas / Perfil</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($guests)): ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No hay huéspedes registrados en el sistema.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($guests as $g): ?>
                        <?php
                            $g_id = (int) $g['id'];
                            $g_doc_type = (string) ($g['document_type'] ?? 'DNI');
                            $g_doc_num = (string) ($g['document_number'] ?? '');
                            $g_name = (string) ($g['name'] ?? 'Huésped Registrado');
                            $g_phone = (string) ($g['phone'] ?? '');
                            $g_email = (string) ($g['email'] ?? '');
                            $g_address = (string) ($g['address'] ?? '');
                            $g_notes = (string) ($g['notes'] ?? '');
                            $g_stays = (int) ($g['stays_count'] ?? 0);
                            $g_spent = (float) ($g['total_spent'] ?? 0.0);
                            $is_vip = $g_stays >= 2 || str_contains(strtoupper($g_notes), 'VIP');
                        ?>
                        <tr class="guest-row-item" data-doc-type="<?= htmlspecialchars($g_doc_type, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" data-doc-num="<?= htmlspecialchars($g_doc_num, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" data-name="<?= htmlspecialchars($g_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" data-phone="<?= htmlspecialchars($g_phone, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>" data-notes="<?= htmlspecialchars($g_notes, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                            <td>#<?= $g_id ?></td>
                            <td>
                                <span class="badge badge-info"><?= htmlspecialchars($g_doc_type, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                                <strong style="color: #38bdf8;"><?= htmlspecialchars($g_doc_num, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong>
                            </td>
                            <td>
                                <strong style="font-size: 1rem; color: #ffffff;"><?= htmlspecialchars($g_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong>
                                <?php if ($is_vip): ?>
                                    <span class="badge badge-warning" style="margin-left: 4px;">⭐ VIP</span>
                                <?php endif; ?>
                                <?php if ($g_address !== ''): ?>
                                    <div class="text-muted" style="font-size: 0.78rem;">📍 <?= htmlspecialchars($g_address, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div>📱 <?= $g_phone !== '' ? htmlspecialchars($g_phone, ENT_QUOTES | ENT_HTML5, 'UTF-8') : '<span class="text-muted">---</span>' ?></div>
                                <?php if ($g_email !== ''): ?>
                                    <div class="text-muted" style="font-size: 0.78rem;">✉️ <?= htmlspecialchars($g_email, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-secondary">🏨 <?= $g_stays ?> Estancias</span>
                            </td>
                            <td>
                                <strong class="text-success" style="font-size: 1rem;">S/ <?= number_format($g_spent, 2) ?></strong>
                            </td>
                            <td>
                                <?php if ($g_notes !== ''): ?>
                                    <span class="guest-notes-tag" title="<?= htmlspecialchars($g_notes, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">📝 <?= htmlspecialchars(mb_strimwidth($g_notes, 0, 28, '...'), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Sin notas</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="/reservas?search=<?= urlencode($g_doc_num) ?>" class="btn btn-sm btn-outline-info" title="Ver Historial de Reservas de este Huésped">📅 Reservas</a>
                                    <button class="btn btn-sm btn-outline-info" onclick="openGuestProfileModal(<?= $g_id ?>)">📜 Historial</button>
                                    <button class="btn btn-sm btn-secondary" onclick="openEditGuestModal(<?= $g_id ?>, '<?= htmlspecialchars($g_doc_type, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', '<?= htmlspecialchars($g_doc_num, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', '<?= htmlspecialchars($g_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', '<?= htmlspecialchars($g_phone, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', '<?= htmlspecialchars($g_email, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', '<?= htmlspecialchars($g_address, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>', '<?= htmlspecialchars($g_notes, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>')">✏️</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL 1: CREAR / EDITAR HUÉSPED -->
<div id="modal-guest-form" class="modal">
    <div class="modal-content" style="max-width: 540px;">
        <span class="close" onclick="closeModal('modal-guest-form')">&times;</span>
        <h3 id="modal-guest-title">➕ Registrar Nuevo Huésped</h3>
        <form onsubmit="handleGuestFormSubmit(event)">
            <input type="hidden" id="guest-form-id">

            <div class="form-row d-flex gap-2">
                <div class="form-group flex-1">
                    <label>Tipo Documento:</label>
                    <select id="guest-form-doc-type" class="form-control" onchange="toggleGuestModalDocType()">
                        <option value="DNI">DNI (8 dígitos)</option>
                        <option value="RUC">RUC (11 dígitos)</option>
                        <option value="CE">Carnet Extranjería</option>
                        <option value="PASAPORTE">Pasaporte</option>
                    </select>
                </div>
                <div class="form-group flex-1">
                    <label id="lbl-modal-doc-num">Número de Documento:</label>
                    <div class="d-flex gap-1">
                        <input type="text" id="guest-form-doc-num" class="form-control" placeholder="Ej: 74819203" required>
                        <button type="button" class="btn btn-secondary" onclick="lookupGuestDocumentInModal()" title="Consultar Padrón RENIEC/SUNAT">🔍</button>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label id="lbl-modal-name">Nombre Completo / Razón Social:</label>
                <input type="text" id="guest-form-name" class="form-control" placeholder="Nombres y Apellidos" required>
            </div>

            <div class="form-row d-flex gap-2">
                <div class="form-group flex-1">
                    <label>Teléfono / Celular:</label>
                    <input type="tel" id="guest-form-phone" class="form-control" placeholder="Ej: 987654321">
                </div>
                <div class="form-group flex-1">
                    <label>Correo Electrónico:</label>
                    <input type="email" id="guest-form-email" class="form-control" placeholder="cliente@correo.com">
                </div>
            </div>

            <div class="form-group">
                <label>Dirección / Procedencia:</label>
                <input type="text" id="guest-form-address" class="form-control" placeholder="Ej: Av. Las Flores 123, Lima">
            </div>

            <div class="form-group">
                <label>Notas Internas / Clasificación (VIP, Preferencias, etc.):</label>
                <input type="text" id="guest-form-notes" class="form-control" placeholder="Ej: Cliente VIP - solicita toallas extra">
            </div>

            <button type="submit" class="btn btn-success btn-block mt-3" id="btn-save-guest">Guardar Huésped</button>
        </form>
    </div>
</div>

<!-- MODAL 2: FICHA HISTÓRICA DEL HUÉSPED -->
<div id="modal-guest-profile" class="modal">
    <div class="modal-content" style="max-width: 680px;">
        <span class="close" onclick="closeModal('modal-guest-profile')">&times;</span>
        <div class="profile-header mb-3 d-flex justify-content-between align-items-center">
            <div>
                <h3 id="profile-guest-name" style="color: #ffffff; margin-bottom: 4px;">Ficha del Huésped</h3>
                <div class="d-flex gap-2 align-items-center">
                    <span class="badge badge-info" id="profile-guest-doc">DNI: ---</span>
                    <span class="badge badge-secondary" id="profile-guest-phone">📱 ---</span>
                    <span class="badge badge-success" id="profile-guest-vip" style="display: none;">⭐ VIP</span>
                </div>
            </div>
            <button type="button" class="btn btn-outline-info" id="btn-profile-view-res" onclick="goToReservationsHistoryForGuest()">📅 Historial Reservas</button>
        </div>

        <div class="profile-stats-grid mb-3">
            <div class="profile-stat-box">
                <span class="stat-label">Total Visitas:</span>
                <strong class="stat-val" id="profile-total-visits">0</strong>
            </div>
            <div class="profile-stat-box">
                <span class="stat-label">Gasto Histórico:</span>
                <strong class="stat-val text-success" id="profile-total-spent">S/ 0.00</strong>
            </div>
            <div class="profile-stat-box">
                <span class="stat-label">Dirección:</span>
                <span class="stat-val" id="profile-guest-address" style="font-size: 0.85rem;">---</span>
            </div>
        </div>

        <h4 class="mb-2">📋 Historial Cronológico de Estancias</h4>
        <div class="table-responsive" style="max-height: 260px; overflow-y: auto;">
            <table class="data-table" id="table-guest-history">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Habitación</th>
                        <th>Categoría</th>
                        <th>Ingreso</th>
                        <th>Salida</th>
                        <th>Tarifa</th>
                        <th>Monto</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody id="guest-history-body">
                    <tr><td colspan="8" class="text-center text-muted">Cargando historial...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="/assets/js/pages/guests.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
