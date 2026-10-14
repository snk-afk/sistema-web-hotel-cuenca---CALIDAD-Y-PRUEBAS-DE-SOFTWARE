<?php

declare(strict_types=1);

$page_title = 'Libro Maestro de Reservas';
$page_styles = ['/assets/css/pages/reservations.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="page-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h2>📜 Libro Maestro & Historial de Reservas</h2>
        <p class="text-muted">Gestión de Reservas: auditoría, trazabilidad y control de reservas históricas y futuras.</p>
    </div>
    <a href="/" class="btn btn-primary">📊 Ir al Mapa de Habitaciones</a>
</div>

<!-- Metrics Cards -->
<div class="reservation-metrics-grid mb-3">
    <div class="reservation-metric-card">
        <div class="metric-icon bg-primary-soft">📌</div>
        <div class="metric-info">
            <span class="metric-title">Reservas Activas</span>
            <span class="metric-value"><?= (int) ($metrics['active_reservations'] ?? 0) ?></span>
        </div>
    </div>

    <div class="reservation-metric-card">
        <div class="metric-icon bg-warning-soft">⏳</div>
        <div class="metric-info">
            <span class="metric-title">Pendientes (Sin Anticipo)</span>
            <span class="metric-value"><?= (int) ($metrics['pending_count'] ?? 0) ?></span>
        </div>
    </div>

    <div class="reservation-metric-card">
        <div class="metric-icon bg-success-soft">✅</div>
        <div class="metric-info">
            <span class="metric-title">Confirmadas (Con Anticipo)</span>
            <span class="metric-value"><?= (int) ($metrics['confirmed_count'] ?? 0) ?></span>
        </div>
    </div>

    <div class="reservation-metric-card">
        <div class="metric-icon bg-info-soft">💰</div>
        <div class="metric-info">
            <span class="metric-title">Anticipos Recaudados</span>
            <span class="metric-value">S/ <?= number_format((float) ($metrics['total_advance_amount'] ?? 0), 2) ?></span>
        </div>
    </div>
</div>

<!-- Reservations Main Card -->
<div class="card">
    <!-- Status Filter Tabs -->
    <div class="reservations-tabs-bar">
        <button class="tab-btn active" data-status="all" onclick="filterByTab('all', this)">
            Todos <span class="tab-counter"><?= (int) ($metrics['total_reservations'] ?? 0) ?></span>
        </button>
        <button class="tab-btn" data-status="pending" onclick="filterByTab('pending', this)">
            ⏳ Pendientes <span class="tab-counter"><?= (int) ($metrics['pending_count'] ?? 0) ?></span>
        </button>
        <button class="tab-btn" data-status="confirmed" onclick="filterByTab('confirmed', this)">
            ✅ Confirmadas <span class="tab-counter"><?= (int) ($metrics['confirmed_count'] ?? 0) ?></span>
        </button>
        <button class="tab-btn" data-status="checked_in" onclick="filterByTab('checked_in', this)">
            🏨 Hospedados <span class="tab-counter"><?= (int) ($metrics['checked_in_count'] ?? 0) ?></span>
        </button>
            <button class="tab-btn" data-status="cancelled" onclick="filterByTab('cancelled', this)">
                ❌ Canceladas <span class="tab-counter"><?= (int) ($metrics['cancelled_count'] ?? 0) ?></span>
            </button>
            <button class="tab-btn" data-status="no_show" onclick="filterByTab('no_show', this)">
                🚫 No-show
            </button>
    </div>

    <!-- Filter & Search Bar -->
    <div class="reservations-filter-bar mb-3">
        <div class="search-box flex-1">
            <span class="search-icon">🔍</span>
            <input type="text" id="filter-reservations-input" class="search-input" placeholder="Buscar por huésped, documento, habitación o ID de reserva..." onkeyup="filterReservationsTable()">
        </div>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="data-table" id="table-reservations">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Huésped</th>
                    <th>Habitación</th>
                    <th>Rango de Fechas</th>
                    <th>Tarifa</th>
                    <th>Anticipo</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reservations)): ?>
                    <tr class="empty-row">
                        <td colspan="8" class="text-center py-4 text-muted">
                            No hay reservas registradas en este momento. Haz clic en <strong>➕ Nueva Reserva</strong> para crear una.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($reservations as $res): ?>
                        <tr data-status="<?= htmlspecialchars($res['status'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                            data-search="<?= htmlspecialchars(strtolower($res['guest_name'] . ' ' . $res['guest_document_number'] . ' ' . $res['room_number'] . ' ' . $res['id']), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                            <td><strong>#<?= (int) $res['id'] ?></strong></td>
                            <td>
                                <div class="guest-cell-title"><?= htmlspecialchars($res['guest_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
                                <div class="guest-cell-meta">
                                    <span class="badge badge-secondary"><?= htmlspecialchars($res['guest_document_type'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>: <?= htmlspecialchars($res['guest_document_number'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                                    <?php if (!empty($res['guest_phone'])): ?>
                                        <span>📞 <?= htmlspecialchars($res['guest_phone'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="room-tag">
                                    🚪 Hab. <?= htmlspecialchars($res['room_number'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </div>
                                <div class="text-muted" style="font-size: 0.75rem; margin-top: 2px;">
                                    <?= htmlspecialchars($res['category_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> (Piso <?= htmlspecialchars($res['room_floor'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>)
                                </div>
                            </td>
                            <td>
                                <div class="date-range-box">
                                    <div class="date-row">
                                        <span class="date-label">IN:</span>
                                        <span class="date-value"><?= date('d/m/Y H:i', strtotime($res['reserved_check_in_at'])) ?></span>
                                    </div>
                                    <div class="date-row">
                                        <span class="date-label">OUT:</span>
                                        <span class="date-value"><?= date('d/m/Y H:i', strtotime($res['reserved_check_out_at'])) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><strong><?= htmlspecialchars($res['tariff_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong></div>
                                <div class="text-muted" style="font-size: 0.8rem;">S/ <?= number_format((float) $res['tariff_price'], 2) ?> (<?= (int) $res['tariff_duration_hours'] ?>h)</div>
                            </td>
                            <td>
                                <?php if ((float) $res['advance_payment'] > 0): ?>
                                    <span class="advance-amount-pill">S/ <?= number_format((float) $res['advance_payment'], 2) ?></span>
                                <?php else: ?>
                                    <span class="advance-zero-pill">Sin anticipo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                    $status_map = [
                                        'pending' => ['label' => 'Pendiente', 'class' => 'status-badge-pending', 'icon' => '⏳'],
                                        'confirmed' => ['label' => 'Confirmada', 'class' => 'status-badge-confirmed', 'icon' => '✅'],
                                        'checked_in' => ['label' => 'Hospedado', 'class' => 'status-badge-checked_in', 'icon' => '🏨'],
                                        'cancelled' => ['label' => 'Cancelada', 'class' => 'status-badge-cancelled', 'icon' => '❌'],
                                        'no_show' => ['label' => 'No-show', 'class' => 'status-badge-no_show', 'icon' => '🚫'],
                                        'pending_refund' => ['label' => 'Devolución pendiente', 'class' => 'status-badge-pending_refund', 'icon' => '💸'],
                                    ];
                                    $current_status = $status_map[$res['status']] ?? ['label' => $res['status'], 'class' => 'status-badge-pending', 'icon' => '📌'];
                                ?>
                                <span class="reservation-status-badge <?= $current_status['class'] ?>">
                                    <?= $current_status['icon'] ?> <?= $current_status['label'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-secondary" onclick='openViewReservationModal(<?= json_encode($res, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>👁️ Ver</button>
                                    <?php if (in_array($res['status'], ['pending', 'confirmed'], true)): ?>
                                        <button class="btn btn-sm btn-success" onclick="quickCheckinReservation(<?= (int) $res['id'] ?>, '<?= htmlspecialchars($res['room_number'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>')">🏨 Check-in</button>
                                        <button class="btn btn-sm btn-danger" onclick="quickCancelReservation(<?= (int) $res['id'] ?>)">❌ Cancelar</button>
                                        <button class="btn btn-sm btn-warning" onclick="quickNoShowReservation(<?= (int) $res['id'] ?>)">🚫 No-show</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: FICHA, AUDITORÍA Y DETALLE DE RESERVA -->
<div id="modal-view-reservation" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <span class="close" onclick="closeModal('modal-view-reservation'); clearModalUrlParams();">&times;</span>
        <h3 id="view-res-title" style="color: #ffffff; margin-bottom: 1rem;">📜 Detalle de la Reserva</h3>
        
        <input type="hidden" id="view-res-id">
        <input type="hidden" id="view-res-room-number">

        <div class="card mb-3 p-3" style="background: rgba(15, 23, 42, 0.7); border: 1px solid var(--border-color);">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="font-bold text-muted">ESTADO ACTUAL:</span>
                <span id="view-res-status-badge"></span>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <span class="font-bold text-muted">ANTICIPO ABONADO:</span>
                <span id="view-res-advance" class="font-bold text-success" style="font-size: 1.2rem;"></span>
            </div>
        </div>

        <div class="mb-3">
            <h5 class="mb-1 text-primary">👤 Datos del Huésped</h5>
            <p class="mb-0 font-bold" id="view-res-guest-name" style="color: #ffffff; font-size: 1.05rem;"></p>
            <p class="text-muted mb-0" id="view-res-guest-doc"></p>
            <p class="text-muted mb-0" id="view-res-guest-phone"></p>
        </div>

        <hr style="border-color: var(--border-color);">

        <div class="mb-3">
            <h5 class="mb-1 text-primary">🚪 Habitación & Tarifa</h5>
            <p class="mb-0 font-bold" id="view-res-room" style="color: #ffffff;"></p>
            <p class="text-muted mb-0" id="view-res-tariff"></p>
        </div>

        <hr style="border-color: var(--border-color);">

        <div class="mb-3">
            <h5 class="mb-1 text-primary">🕒 Horario Reservado</h5>
            <p class="mb-0"><strong>Entrada:</strong> <span id="view-res-checkin"></span></p>
            <p class="mb-0"><strong>Salida Estimada:</strong> <span id="view-res-checkout"></span></p>
        </div>

        <div class="mb-3" id="view-res-notes-container" style="display: none;">
            <hr style="border-color: var(--border-color);">
            <h5 class="mb-1 text-primary">📝 Notas / Requerimientos</h5>
            <p class="mb-0 fst-italic" id="view-res-notes" style="color: #94a3b8;"></p>
        </div>

        <div class="d-flex justify-content-between align-items-center mt-3 pt-2" style="border-top: 1px solid var(--border-color);">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-view-reservation'); clearModalUrlParams();">Cerrar</button>
            <div class="d-flex gap-2" id="view-res-actions">
                <button type="button" class="btn btn-danger" id="btn-modal-cancel-res" onclick="executeModalCancelReservation()">❌ Cancelar Reserva</button>
                <button type="button" class="btn btn-warning" id="btn-modal-no-show-res" onclick="executeModalNoShowReservation()">🚫 Registrar No-show</button>
                <button type="button" class="btn btn-success" id="btn-modal-checkin-res" onclick="executeModalCheckinReservation()">🏨 Ejecutar Check-In</button>
            </div>
        </div>
    </div>
</div>

<script>
    window.allReservationsCatalog = <?= json_encode($reservations ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="/assets/js/pages/reservations.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
