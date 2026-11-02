<?php
declare(strict_types=1);

$page_title = 'Rack de Habitaciones';
$page_styles = ['/assets/css/pages/dashboard.css'];
require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<div class="dashboard-header-bar">
    <div class="search-box">
        <span class="search-icon">🔍</span>
        <input type="text" id="rack-search-input" class="search-input" placeholder="Buscar por N° habitación, DNI, RUC o Nombre..." onkeyup="filterRackRooms()">
    </div>

    <div class="rack-stats-pills">
        <span class="stat-pill pill-all active-filter" onclick="filterRackByStatus('all', this)">🏨 Todos (<strong id="count-all"><?= count($rooms) ?></strong>)</span>
        <span class="stat-pill pill-free" onclick="filterRackByStatus('free', this)">🟢 Libres (<strong id="count-free">0</strong>)</span>
        <span class="stat-pill pill-occupied" onclick="filterRackByStatus('occupied', this)">🔵 Ocupadas (<strong id="count-occupied">0</strong>)</span>
        <span class="stat-pill pill-overdue" onclick="filterRackByStatus('overdue', this)">⚠️ Vencidas (<strong id="count-overdue">0</strong>)</span>
        <span class="stat-pill pill-cleaning" onclick="filterRackByStatus('cleaning', this)">🧹 Limpieza (<strong id="count-cleaning">0</strong>)</span>
        <span class="stat-pill pill-reserved" onclick="filterRackByStatus('reserved', this)">📅 Reservadas (<strong id="count-reserved">0</strong>)</span>
        <span class="stat-pill pill-maintenance" onclick="filterRackByStatus('maintenance', this)">🔧 Avería (<strong id="count-maintenance">0</strong>)</span>
    </div>
</div>

<div class="rooms-grid" id="rack-rooms-grid">
    <?php foreach ($rooms as $room): ?>
        <?php
            $room_id = (int) ($room['id'] ?? 0);
            $room_number = (string) ($room['number'] ?? '');
            $room_status = (string) ($room['status'] ?? 'free');
            $category_name = (string) ($room['category'] ?? $room['category_name'] ?? 'General');
            $category_id = (int) ($room['room_category_id'] ?? 1);
            $floor = (string) ($room['floor'] ?? '1');

            /** @var array<string, mixed>|null $current_stay */
            $current_stay = is_array($room['current_stay'] ?? null) ? $room['current_stay'] : null;
            $guest_name = is_string($current_stay['guest_name'] ?? null) ? $current_stay['guest_name'] : null;
            $guest_doc = is_string($current_stay['guest_doc'] ?? null) ? $current_stay['guest_doc'] : null;
            $guest_doc_type = is_string($current_stay['guest_doc_type'] ?? null) ? $current_stay['guest_doc_type'] : 'DNI';
            $check_in_at = is_string($current_stay['check_in_at'] ?? null) ? $current_stay['check_in_at'] : null;
            $expected_check_out_at = is_string($current_stay['expected_check_out_at'] ?? null) ? $current_stay['expected_check_out_at'] : null;
            $seconds_remaining = is_numeric($current_stay['seconds_remaining'] ?? null) ? (int) $current_stay['seconds_remaining'] : null;
            $is_overdue = (bool) ($current_stay['is_overdue'] ?? ($seconds_remaining !== null && $seconds_remaining <= 0));
            $pending_balance = is_numeric($current_stay['pending_balance'] ?? null) ? (float) $current_stay['pending_balance'] : 0.0;

            // Buscar todas las reservas activas/futuras para esta habitación ordenadas por fecha más próxima
            $room_active_reservations = [];
            if (!empty($reservations) && is_array($reservations)) {
                foreach ($reservations as $r) {
                    if ((int) $r['room_id'] === (int) $room_id && in_array($r['status'], ['pending', 'confirmed'], true)) {
                        $room_active_reservations[] = $r;
                    }
                }
            }

            // Ordenar por hora de llegada ascendente
            usort($room_active_reservations, function ($a, $b) {
                $timeA = strtotime($a['check_in_date'] ?? $a['reserved_check_in_at'] ?? 'now');
                $timeB = strtotime($b['check_in_date'] ?? $b['reserved_check_in_at'] ?? 'now');
                return $timeA <=> $timeB;
            });

            $upcoming_reservation = $room_active_reservations[0] ?? null;
            $total_future_reservations = count($room_active_reservations);

            $status_class = 'room-card-free';
            $status_badge_class = 'badge-free';
            $status_label = 'LIBRE';

            if ($room_status === 'occupied') {
                if ($is_overdue) {
                    $status_class = 'room-card-occupied room-card-overdue';
                    $status_badge_class = 'badge-overdue';
                    $status_label = '⚠️ TIEMPO CUMPLIDO';
                } else {
                    $status_class = 'room-card-occupied';
                    $status_badge_class = 'badge-occupied';
                    $status_label = 'OCUPADA';
                }
            } elseif ($room_status === 'cleaning') {
                $status_class = 'room-card-cleaning';
                $status_badge_class = 'badge-cleaning';
                $status_label = 'LIMPIEZA';
            } elseif ($room_status === 'maintenance') {
                $status_class = 'room-card-maintenance';
                $status_badge_class = 'badge-danger';
                $status_label = '⚠️ AVERÍA';
            } elseif ($room_status === 'reserved') {
                $status_class = 'room-card-reserved';
                $status_badge_class = 'badge-info';
                $status_label = '📅 RESERVADA';
            }

            $initial_timer_str = '00h 00m 00s';
            if ($seconds_remaining !== null) {
                $abs_secs = abs($seconds_remaining);
                $hrs = str_pad((string) floor($abs_secs / 3600), 2, '0', STR_PAD_LEFT);
                $mins = str_pad((string) floor(($abs_secs % 3600) / 60), 2, '0', STR_PAD_LEFT);
                $secs = str_pad((string) ($abs_secs % 60), 2, '0', STR_PAD_LEFT);
                $initial_timer_str = ($seconds_remaining < 0 ? '⚠️ Excedido: +' : '⏱️ ') . "{$hrs}h {$mins}m {$secs}s";
            }
        ?>

        <div class="room-card <?= $status_class ?>" 
             data-room-id="<?= $room_id ?>" 
             data-room-number="<?= htmlspecialchars($room_number, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
             data-category-id="<?= $category_id ?>"
             data-category-name="<?= htmlspecialchars($category_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
             data-status="<?= htmlspecialchars($room_status, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
             data-is-overdue="<?= $is_overdue ? '1' : '0' ?>"
             data-guest-name="<?= htmlspecialchars($guest_name ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
             data-guest-doc="<?= htmlspecialchars($guest_doc ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
             data-checkin-at="<?= htmlspecialchars($check_in_at ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
             data-checkout-at="<?= htmlspecialchars($expected_check_out_at ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
             data-seconds-remaining="<?= $seconds_remaining ?? '' ?>"
             onclick="openRoomDrawer(<?= $room_id ?>)">

            <div class="room-card-header">
                <div class="room-number-box">
                    <h3>Hab. <?= htmlspecialchars($room_number, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></h3>
                    <span class="room-floor-tag">Piso <?= htmlspecialchars($floor, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                </div>
                <span class="badge-status <?= $status_badge_class ?>"><?= $status_label ?></span>
            </div>

            <div class="room-card-body">
                <div class="room-meta-row">
                    <span class="category-chip">🏷️ <?= htmlspecialchars($category_name, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                </div>

                <?php if ($room_status === 'occupied' && $current_stay !== null): ?>
                    <div class="guest-card-box">
                        <div class="guest-name-row">
                            <span>👤</span>
                            <strong><?= htmlspecialchars($guest_name ?? 'Huésped Registrado', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong>
                        </div>
                        <?php if ($guest_doc && $guest_doc !== '---'): ?>
                            <span class="guest-doc-tag">🪪 <?= htmlspecialchars($guest_doc_type . ': ' . $guest_doc, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if ($seconds_remaining !== null): ?>
                        <div class="card-timer-row timer-container <?= $seconds_remaining < 0 ? 'card-timer-overdue' : '' ?>" data-seconds="<?= $seconds_remaining ?>">
                            <span>⏳ Salida:</span>
                            <span class="timer-display"><?= $initial_timer_str ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="card-balance-row">
                        <span>Saldo:</span>
                        <?php if ($pending_balance > 0): ?>
                            <span class="balance-debt">⚠️ Deuda: S/ <?= number_format($pending_balance, 2) ?></span>
                        <?php else: ?>
                            <span class="balance-paid">✅ Pagado (S/ 0.00)</span>
                        <?php endif; ?>
                    </div>
                <?php elseif ($room_status === 'cleaning'): ?>
                    <div class="room-cleaning-placeholder">
                        <span>🧹 Habitación en Limpieza</span>
                        <small class="text-muted mt-1">Requiere desinfección</small>
                    </div>
                <?php elseif ($room_status === 'maintenance'): ?>
                    <div class="room-cleaning-placeholder" style="background: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.2);">
                        <span style="color: #f87171;">⚠️ Fuera de Servicio</span>
                        <small class="text-muted mt-1">Bloqueada por mantenimiento</small>
                    </div>
                <?php elseif ($room_status === 'reserved'): ?>
                    <div class="guest-card-box" style="border-left: 3px solid #8b5cf6;">
                        <div class="guest-name-row">
                            <span>📅</span>
                            <strong style="color: #c4b5fd;"><?= htmlspecialchars($upcoming_reservation['guest_name'] ?? 'Reserva Agendada', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></strong>
                        </div>
                        <span class="guest-doc-tag" style="color: #a78bfa;">Llegada: <?= htmlspecialchars($upcoming_reservation['check_in_date'] ?? $upcoming_reservation['reserved_check_in_at'] ?? 'Hoy', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
                    </div>
                <?php else: ?>
                    <!-- Estado FREE: Si tiene reserva futura programada, mostrar aviso destacado -->
                    <?php if ($upcoming_reservation !== null): ?>
                        <div class="guest-card-box" style="border-left: 3px solid #f59e0b; background: rgba(245, 158, 11, 0.08); padding: 8px 10px; margin-bottom: 6px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: #fbbf24; font-size: 0.78rem; font-weight: 700;">📅 PROX. RESERVA</span>
                                <span style="font-size: 0.75rem; color: #fde68a;">
                                    <?= htmlspecialchars(date('d/m H:i', strtotime($upcoming_reservation['check_in_date'] ?? $upcoming_reservation['reserved_check_in_at'] ?? 'now')), ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                                </span>
                            </div>
                            <div style="font-size: 0.85rem; color: #ffffff; font-weight: 600; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                👤 <?= htmlspecialchars($upcoming_reservation['guest_name'] ?? 'Huésped', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                            </div>
                            <?php if ($total_future_reservations > 1): ?>
                                <div style="margin-top: 4px; font-size: 0.75rem; color: #38bdf8; font-weight: 600;">
                                    ➕ <?= ($total_future_reservations - 1) ?> reserva(s) más en agenda
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="room-ready-placeholder">
                        <span>🟢 Disponible para hospedar</span>
                        <?php if ($upcoming_reservation !== null): ?>
                            <small style="color: #f59e0b; display: block; margin-top: 2px; font-size: 0.76rem;">(Hasta la próxima reserva)</small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="room-card-footer" onclick="event.stopPropagation()">
                <?php if ($room_status === 'free'): ?>
                    <button class="btn btn-success btn-card-action" onclick="openRoomDrawer(<?= $room_id ?>); switchToCheckinForm();">
                        ⚡ Check-In Express
                    </button>
                <?php elseif ($room_status === 'occupied'): ?>
                    <?php if ($is_overdue): ?>
                        <button class="btn btn-danger btn-card-action" onclick="openRoomDrawer(<?= $room_id ?>)">
                            ⚠️ Atender Salida / Extender
                        </button>
                    <?php else: ?>
                        <button class="btn btn-primary btn-card-action" onclick="openRoomDrawer(<?= $room_id ?>)">
                            ⚡ Ver Folio & Opciones
                        </button>
                    <?php endif; ?>
                <?php elseif ($room_status === 'cleaning'): ?>
                    <button class="btn btn-warning btn-card-action" onclick="quickReleaseCleaningRoom(<?= $room_id ?>, event)">
                        ✨ Liberar Habitación
                    </button>
                <?php elseif ($room_status === 'maintenance'): ?>
                    <button class="btn btn-secondary btn-card-action" onclick="openRoomDrawer(<?= $room_id ?>)">
                        ⚙️ Administrar Avería
                    </button>
                <?php elseif ($room_status === 'reserved'): ?>
                    <button class="btn btn-primary btn-card-action" onclick="openRoomDrawer(<?= $room_id ?>)">
                        🏨 Ocupar Reserva
                    </button>
                <?php else: ?>
                    <button class="btn btn-secondary btn-card-action" onclick="openRoomDrawer(<?= $room_id ?>)">
                        ⚙️ Opciones
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Contextual Slide-Over Drawer / Adaptive Room Modal -->
<?php require __DIR__ . '/../components/room_modal.php'; ?>

<script>
    window.allReservationsCatalog = <?= json_encode($reservations ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>
<script src="/assets/js/pages/dashboard.js"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
