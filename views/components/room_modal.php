<?php declare(strict_types=1); ?>
<div id="room-drawer-overlay" class="ns-room-modal" style="display: none;" onclick="closeRoomDrawer(event)">
    <div class="ns-room-modal__dialog" onclick="event.stopPropagation()">
        <!-- Header con Título, Badges de Estado y Categoría y Botón de Cierre -->
        <div class="ns-room-modal__header">
            <div class="ns-room-modal__title-group">
                <h2 id="drawer-room-number" class="ns-room-modal__title">Habitación ---</h2>
                <div class="ns-room-modal__badges">
                    <span id="drawer-room-status-badge" class="ns-room-modal__badge">---</span>
                    <span id="drawer-room-category" class="ns-room-modal__badge ns-room-modal__badge--category">---</span>
                </div>
            </div>
            <button type="button" class="ns-room-modal__close" onclick="closeRoomDrawer()">&times;</button>
        </div>

        <!-- Barra de Pestañas Contextuales Rigurosas por Estado -->
        <div class="ns-room-modal__tabs" id="room-modal-tabs-bar">
            <!-- PESTAÑAS PARA ESTADO: LIBRE -->
            <button class="ns-room-modal__tab tab-context-free" id="tab-btn-free-checkin" onclick="switchDrawerTab('tab-free-checkin')">🏨 Registrar Hospedaje</button>
            <button class="ns-room-modal__tab tab-context-free" id="tab-btn-room-calendar" onclick="switchDrawerTab('tab-room-calendar'); renderRoomCalendarList();">📅 Agenda de Reservas</button>
            <button class="ns-room-modal__tab tab-context-free" id="tab-btn-free-to-cleaning" onclick="switchDrawerTab('tab-free-to-cleaning')">🧹 Enviar a Limpieza / Avería</button>

            <!-- PESTAÑAS PARA ESTADO: LIMPIEZA -->
            <button class="ns-room-modal__tab tab-context-cleaning" id="tab-btn-cleaning-action" onclick="switchDrawerTab('tab-cleaning-action')">🧹 Habitación en Limpieza</button>

            <!-- PESTAÑAS PARA ESTADO: AVERÍA / MANTENIMIENTO -->
            <button class="ns-room-modal__tab tab-context-maintenance" id="tab-btn-maint-action" onclick="switchDrawerTab('tab-maint-action')">⚠️ Habitación Fuera de Servicio</button>

            <!-- PESTAÑAS PARA ESTADO: RESERVADA -->
            <button class="ns-room-modal__tab tab-context-reserved" id="tab-btn-reserved-action" onclick="switchDrawerTab('tab-free-checkin')">🏨 Ocupar Reserva Programada</button>
            <button class="ns-room-modal__tab tab-context-reserved" id="tab-btn-reserved-calendar" onclick="switchDrawerTab('tab-room-calendar'); renderRoomCalendarList();">📅 Ver Todas las Reservas</button>

            <!-- PESTAÑAS PARA ESTADO: OCUPADA -->
            <button class="ns-room-modal__tab tab-context-occupied" id="tab-btn-occ-summary" onclick="switchDrawerTab('tab-occ-summary')">📋 Estado & Cuenta Folio</button>
            <button class="ns-room-modal__tab tab-context-occupied" id="tab-btn-occ-checkout" onclick="switchDrawerTab('tab-occ-checkout')">🚪 Liquidar Checkout (SUNAT)</button>
            <button class="ns-room-modal__tab tab-context-occupied" id="tab-btn-occ-store" onclick="switchDrawerTab('tab-occ-store')">🛒 Consumos Tienda POS</button>
            <button class="ns-room-modal__tab tab-context-occupied" id="tab-btn-occ-manage" onclick="switchDrawerTab('tab-occ-manage')">⚙️ Horas Extras & Transferencia</button>
        </div>

        <!-- Contenedor Principal de Vistas -->
        <div class="ns-room-modal__body">
            
            <!-- ====================================================
                 1. CONTEXTO: HABITACIÓN LIBRE / RESERVADA
                 ==================================================== -->
            <div id="tab-free-checkin" class="ns-room-modal__tab-content tab-context-free tab-context-reserved active">
                <!-- Reserva Pendiente Programada para esta habitación -->
                <div id="drawer-room-reservations-box" style="display: none; margin-bottom: 1.25rem;">
                    <div class="ns-room-modal__card" style="border-left: 4px solid #f59e0b; background: rgba(245, 158, 11, 0.08);">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <h4 class="ns-room-modal__card-title" style="margin: 0; color: #fde68a;">📅 Reserva Pendiente para esta Habitación</h4>
                            <span class="ns-room-modal__badge ns-room-modal__badge--cleaning" id="drawer-res-status-badge">PENDIENTE</span>
                        </div>
                        <div class="ns-room-modal__info-grid">
                            <div class="ns-room-modal__info-item"><label>Huésped:</label> <strong id="drawer-res-guest-name">---</strong></div>
                            <div class="ns-room-modal__info-item"><label>Documento:</label> <strong id="drawer-res-guest-doc">---</strong></div>
                            <div class="ns-room-modal__info-item"><label>Fecha Llegada:</label> <strong id="drawer-res-checkin-time">---</strong></div>
                            <div class="ns-room-modal__info-item"><label>Adelanto:</label> <strong id="drawer-res-advance-amount" style="color: #34d399;">S/ 0.00</strong></div>
                        </div>
                        <div style="margin-top: 12px;">
                            <button type="button" class="ns-room-modal__btn ns-room-modal__btn--success" id="drawer-btn-execute-res-checkin" onclick="executeDrawerReservationCheckin()">🏨 Ocupar Habitación con esta Reserva</button>
                        </div>
                    </div>
                </div>

                <!-- Selector de Modalidad -->
                <div class="ns-room-modal__form-group">
                    <label class="ns-room-modal__label">Modalidad de Registro:</label>
                    <div class="ns-room-modal__pill-group">
                        <label class="ns-room-modal__pill-option active" id="drawer-pill-op-checkin">
                            <input type="radio" name="operation_mode" value="checkin" checked onchange="toggleDrawerOperationMode('checkin')"> ⚡ Check-In Inmediato
                        </label>
                        <label class="ns-room-modal__pill-option" id="drawer-pill-op-reservation">
                            <input type="radio" name="operation_mode" value="reservation" onchange="toggleDrawerOperationMode('reservation')"> 📅 Reservar a Futuro
                        </label>
                    </div>
                </div>

                <form onsubmit="handleDrawerActionSubmit(event)">
                    <input type="hidden" id="drawer-checkin-room-id">
                    <input type="hidden" id="drawer-checkin-room-cat-id">
                    <input type="hidden" id="drawer-checkin-reservation-id" value="">

                    <!-- Fecha de Llegada Futura (Solo si es Reserva Futura) -->
                    <div id="field-future-arrival" class="ns-room-modal__card" style="display: none; border-color: rgba(245, 158, 11, 0.4); margin-bottom: 1rem;">
                        <label class="ns-room-modal__label" style="color: #fde68a;">📅 Fecha y Hora de Llegada Prevista:</label>
                        <input type="datetime-local" id="drawer-future-checkin-at" class="ns-room-modal__input" onchange="recalculateFutureReservationDates()">
                        <small style="color: #94a3b8; display: block; margin-top: 4px; font-size: 0.8rem;">La salida estimada se calculará automáticamente con la tarifa elegida.</small>
                    </div>

                    <!-- Selector de Huésped Existente -->
                    <div class="ns-room-modal__form-group">
                        <label class="ns-room-modal__label">Huésped Registrado en Padrón (Opcional):</label>
                        <select id="drawer-guest-select" class="ns-room-modal__select" onchange="handleDrawerGuestSelectChange(this)">
                            <option value="">-- Seleccionar de la lista o digitar abajo --</option>
                            <?php if (!empty($guests)): ?>
                                <?php foreach ($guests as $g): ?>
                                    <option value="<?= (int) $g['id'] ?>"
                                            data-doc-type="<?= htmlspecialchars($g['document_type'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                            data-doc-number="<?= htmlspecialchars($g['document_number'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                            data-name="<?= htmlspecialchars($g['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                            data-phone="<?= htmlspecialchars($g['phone'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>"
                                            data-address="<?= htmlspecialchars($g['address'] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
                                        <?= htmlspecialchars($g['name'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> (<?= htmlspecialchars($g['document_type'] . ': ' . $g['document_number'], ENT_QUOTES | ENT_HTML5, 'UTF-8') ?><?= !empty($g['phone']) ? ' | 📞 ' . htmlspecialchars($g['phone'], ENT_QUOTES | ENT_HTML5, 'UTF-8') : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Datos del Huésped en Grid de 2 Columnas -->
                    <div class="ns-room-modal__form-grid-2">
                        <div class="ns-room-modal__form-group">
                            <label class="ns-room-modal__label">Tipo de Huésped:</label>
                            <div class="ns-room-modal__pill-group">
                                <label class="ns-room-modal__pill-option active" id="drawer-pill-dni">
                                    <input type="radio" name="doc_type_mode" value="DNI" checked onchange="toggleGuestDocType('DNI')"> 🪪 Persona (DNI)
                                </label>
                                <label class="ns-room-modal__pill-option" id="drawer-pill-ruc">
                                    <input type="radio" name="doc_type_mode" value="RUC" onchange="toggleGuestDocType('RUC')"> 🏢 Empresa (RUC)
                                </label>
                            </div>
                        </div>

                        <div class="ns-room-modal__form-group">
                            <label id="lbl-doc-number" class="ns-room-modal__label">DNI del Huésped (8 dígitos):</label>
                            <div class="ns-room-modal__input-group">
                                <input type="text" id="drawer-checkin-doc-number" class="ns-room-modal__input" placeholder="Ingrese DNI" maxlength="8" required>
                                <button type="button" class="ns-room-modal__btn ns-room-modal__btn--secondary" style="width: auto; min-width: 110px;" onclick="lookupDrawerGuestDocument()">🔍 Buscar</button>
                            </div>
                        </div>
                    </div>

                    <div class="ns-room-modal__form-group">
                        <label id="lbl-guest-name" class="ns-room-modal__label">Nombre Completo:</label>
                        <input type="text" id="drawer-checkin-guest-name" class="ns-room-modal__input" placeholder="Nombres y apellidos" required>
                    </div>

                    <!-- Campo Condicional Empresa (RUC) -->
                    <div id="field-company-address" class="ns-room-modal__form-group" style="display: none;">
                        <label class="ns-room-modal__label">Dirección Fiscal de la Empresa:</label>
                        <input type="text" id="drawer-checkin-company-address" class="ns-room-modal__input" placeholder="Ej: Av. Principal 123, Lima">
                    </div>

                    <!-- Régimen y Tarifa en Grid de 2 Columnas -->
                    <div class="ns-room-modal__form-grid-2">
                        <div class="ns-room-modal__form-group">
                            <label class="ns-room-modal__label">Régimen de Tiempo:</label>
                            <div class="ns-room-modal__pill-group">
                                <label class="ns-room-modal__pill-option active">
                                    <input type="radio" name="stay_mode" value="tariff" checked onchange="toggleStayMode('tariff')"> ⚡ Tarifa Estándar
                                </label>
                                <label class="ns-room-modal__pill-option">
                                    <input type="radio" name="stay_mode" value="custom" onchange="toggleStayMode('custom')"> 📅 Horario Libre
                                </label>
                            </div>
                        </div>

                        <div id="field-tariff-select" class="ns-room-modal__form-group">
                            <label class="ns-room-modal__label">Tarifa de Hospedaje (<span id="drawer-tariff-category-label">Categoría</span>):</label>
                            <select id="drawer-checkin-tariff-id" class="ns-room-modal__select" onchange="updateDrawerTariffPrice()">
                                <option value="">Cargando tarifas...</option>
                            </select>
                            <div id="box-calculated-checkout-preview" style="margin-top: 6px; color: #38bdf8; font-size: 0.88rem; display: none;">
                                ⌛ <strong>Salida Estimada:</strong> <span id="drawer-calculated-checkout-text">---</span>
                            </div>
                        </div>
                    </div>

                    <!-- Campos Condicionales Horario Manual -->
                    <div id="fields-custom-datetime" style="display: none;">
                        <div class="ns-room-modal__form-group">
                            <label class="ns-room-modal__label">📅 Fecha/Hora de Entrada:</label>
                            <input type="datetime-local" id="drawer-checkin-custom-in" class="ns-room-modal__input">
                        </div>

                        <div class="ns-room-modal__form-group">
                            <label class="ns-room-modal__label">⌛ Fecha/Hora de Salida:</label>
                            <input type="datetime-local" id="drawer-checkin-custom-out" class="ns-room-modal__input">
                        </div>
                    </div>

                    <!-- Modo de Precio y Monto en Grid de 2 Columnas -->
                    <div class="ns-room-modal__form-grid-2">
                        <div class="ns-room-modal__form-group">
                            <label class="ns-room-modal__label">Modalidad de Precio:</label>
                            <div class="ns-room-modal__pill-group">
                                <label class="ns-room-modal__pill-option active">
                                    <input type="radio" name="price_mode" value="normal" checked onchange="togglePriceMode('normal')"> 💵 Precio Oficial
                                </label>
                                <label class="ns-room-modal__pill-option">
                                    <input type="radio" name="price_mode" value="custom" onchange="togglePriceMode('custom')"> ✏️ Modificar
                                </label>
                            </div>
                        </div>

                        <div class="ns-room-modal__form-group">
                            <label class="ns-room-modal__label">Precio de Hospedaje (S/):</label>
                            <input type="number" step="0.50" id="drawer-checkin-applied-price" class="ns-room-modal__input" value="35.00" oninput="calculateAdvanceBalance()" required>
                        </div>
                    </div>

                    <!-- Campo Condicional Motivo Descuento -->
                    <div id="field-discount-reason" class="ns-room-modal__form-group" style="display: none;">
                        <label class="ns-room-modal__label" style="color: #fbbf24;">Motivo del Ajuste de Precio:</label>
                        <input type="text" id="drawer-checkin-discount-reason" class="ns-room-modal__input" placeholder="Ej: Tarifa corporativa, cliente frecuente...">
                    </div>

                    <!-- Adelanto Inicial en Card Amplio -->
                    <div class="ns-room-modal__card" style="margin-top: 1rem;">
                        <h4 class="ns-room-modal__card-title" style="color: #38bdf8;">💳 Pago / Adelanto Inicial</h4>
                        
                        <div class="ns-room-modal__form-grid-2">
                            <div class="ns-room-modal__form-group">
                                <label class="ns-room-modal__label">Monto de Adelanto Pagado (S/):</label>
                                <input type="number" step="0.50" id="drawer-checkin-advance-amount" class="ns-room-modal__input" value="0.00" min="0" oninput="calculateAdvanceBalance()">
                                <small style="color: #94a3b8; display: block; margin-top: 4px; font-size: 0.8rem;">Ingrese 0.00 si paga el 100% al salir, o el total si cancela completo.</small>
                            </div>

                            <div class="ns-room-modal__form-group">
                                <label class="ns-room-modal__label">Método de Pago del Adelanto:</label>
                                <select id="drawer-checkin-advance-method" class="ns-room-modal__select">
                                    <option value="cash">💵 Efectivo</option>
                                    <option value="yape">📱 Yape / Plin</option>
                                    <option value="card">💳 Tarjeta Crédito/Débito</option>
                                    <option value="transfer">🏦 Transferencia Bancaria</option>
                                </select>
                            </div>
                        </div>

                        <div class="ns-room-modal__balance-total" style="margin-top: 1rem;">
                            <span>SALDO RESTANTE A PAGAR:</span>
                            <strong id="drawer-checkin-remaining-balance" style="color: #f87171; font-size: 1.25rem;">S/ 35.00</strong>
                        </div>
                    </div>

                    <button type="submit" id="btn-drawer-action-submit" class="ns-room-modal__btn ns-room-modal__btn--success" style="margin-top: 1.25rem;">✅ Confirmar Check-In y Registrar</button>
                </form>
            </div>

            <!-- PESTAÑA: AGENDA / FULLCALENDAR INTERACTIVO HORARIO Y MULTIDÍA -->
            <div id="tab-room-calendar" class="ns-room-modal__tab-content tab-context-free tab-context-reserved">
                <div class="ns-room-modal__card" style="padding: 1.25rem;">
                    <!-- Cabecera de Agenda -->
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <h4 class="ns-room-modal__card-title" style="margin: 0; color: #38bdf8;">📅 Agenda Horaria y Multidía de la Habitación</h4>
                            <p style="color: #94a3b8; font-size: 0.85rem; margin: 2px 0 0 0;">Visualiza ocupación por mes, semana y horas exactas (00:00 - 24:00).</p>
                        </div>
                        <button type="button" class="ns-room-modal__btn ns-room-modal__btn--success" style="width: auto; padding: 6px 14px; font-size: 0.85rem;" onclick="switchToCheckinForm(); toggleDrawerOperationMode('reservation');">➕ Agendar Reserva</button>
                    </div>

                    <!-- Leyenda de Colores -->
                    <div style="display: flex; gap: 14px; margin-bottom: 1rem; font-size: 0.78rem; flex-wrap: wrap; color: #94a3b8; background: rgba(0,0,0,0.25); padding: 8px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05);">
                        <span style="display: flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: #8b5cf6; display: inline-block;"></span> 🟣 Reserva Confirmada</span>
                        <span style="display: flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: #f59e0b; display: inline-block;"></span> 🟡 Reserva Pendiente</span>
                        <span style="display: flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: #3b82f6; display: inline-block;"></span> 🔵 Hospedaje Activo (Stay)</span>
                        <span style="display: flex; align-items: center; gap: 6px;"><span style="width: 12px; height: 12px; border-radius: 3px; background: rgba(255,255,255,0.08); border: 1px dashed rgba(255,255,255,0.2); display: inline-block;"></span> 🟢 Franja Libre (Clic para reservar)</span>
                    </div>

                    <!-- Contenedor del Calendario FullCalendar -->
                    <div id="drawer-fullcalendar-mount" style="min-height: 520px; background: #0f172a; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 12px;"></div>

                    <!-- Panel de Lista Resumen de Reservas -->
                    <div id="calendar-day-details-panel" style="margin-top: 1.25rem; background: rgba(15, 23, 42, 0.6); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 12px 14px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <strong id="calendar-selected-day-title" style="color: #ffffff; font-size: 0.92rem;">📋 Próximos compromisos agendados:</strong>
                        </div>
                        <div id="drawer-room-calendar-list" style="display: flex; flex-direction: column; gap: 8px;">
                            <p style="color: #94a3b8; text-align: center; margin: 0;">Cargando reservas...</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- LIBRE: PESTAÑA PARA ENVIAR A LIMPIEZA O AVERÍA -->
            <div id="tab-free-to-cleaning" class="ns-room-modal__tab-content tab-context-free">
                <div class="ns-room-modal__card">
                    <h4 class="ns-room-modal__card-title">🧹 Bloquear Habitación para Limpieza / Mantenimiento</h4>
                    <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 1.25rem;">Si la habitación requiere retoques, limpieza profunda o revisión técnica, márquela aquí para evitar asignarla:</p>
                    <button class="ns-room-modal__btn ns-room-modal__btn--warning" onclick="handleDrawerSetCleaning()">🧹 Marcar Habitación en Limpieza</button>
                </div>
            </div>

            <!-- ====================================================
                 2. CONTEXTO: HABITACIÓN EN LIMPIEZA
                 ==================================================== -->
            <div id="tab-cleaning-action" class="ns-room-modal__tab-content tab-context-cleaning">
                <div class="ns-room-modal__card" style="border-left: 4px solid #f59e0b; text-align: center; padding: 2rem 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 10px;">🧹</div>
                    <h3 style="color: #fbbf24; margin: 0 0 8px 0;">Habitación en Proceso de Limpieza</h3>
                    <p style="color: #94a3b8; font-size: 0.95rem; max-width: 480px; margin: 0 auto 1.5rem auto;">
                        El personal de housekeeping está aseando o acondicionando esta habitación. Una vez lista e inspeccionada, libérela para dejarla disponible en el rack.
                    </p>
                    <button class="ns-room-modal__btn ns-room-modal__btn--success" style="max-width: 320px; margin: 0 auto;" onclick="handleDrawerReleaseCleaning()">✨ Terminar Limpieza y Dejar Libre</button>
                </div>
            </div>

            <!-- ====================================================
                 3. CONTEXTO: HABITACIÓN EN AVERÍA / MANTENIMIENTO
                 ==================================================== -->
            <div id="tab-maint-action" class="ns-room-modal__tab-content tab-context-maintenance">
                <div class="ns-room-modal__card" style="border-left: 4px solid #ef4444; text-align: center; padding: 2rem 1.5rem;">
                    <div style="font-size: 3rem; margin-bottom: 10px;">⚠️</div>
                    <h3 style="color: #f87171; margin: 0 0 8px 0;">Habitación Fuera de Servicio (Avería / Mantenimiento)</h3>
                    <p style="color: #94a3b8; font-size: 0.95rem; max-width: 480px; margin: 0 auto 1.5rem auto;">
                        Esta habitación se encuentra bloqueada temporalmente por mantenimiento técnico.
                    </p>
                    <button class="ns-room-modal__btn ns-room-modal__btn--success" style="max-width: 320px; margin: 0 auto;" onclick="handleDrawerReleaseCleaning()">✨ Reparada y Habilitar a Libre</button>
                </div>
            </div>

            <!-- ====================================================
                 4. CONTEXTO: HABITACIÓN OCUPADA
                 ==================================================== -->
            <!-- OCUPADA: RESUMEN DE HUÉSPED Y BALANCE FOLIO -->
            <div id="tab-occ-summary" class="ns-room-modal__tab-content tab-context-occupied">
                <div class="ns-room-modal__card">
                    <h4 class="ns-room-modal__card-title">👤 Datos del Huésped Actual</h4>
                    <div class="ns-room-modal__info-grid">
                        <div class="ns-room-modal__info-item"><label>Huésped:</label> <strong id="drawer-guest-name">---</strong></div>
                        <div class="ns-room-modal__info-item"><label>Documento:</label> <strong id="drawer-guest-doc">---</strong></div>
                        <div class="ns-room-modal__info-item"><label>Ingreso:</label> <strong id="drawer-checkin-time">---</strong></div>
                        <div class="ns-room-modal__info-item"><label>Salida Prevista:</label> <strong id="drawer-checkout-time">---</strong></div>
                    </div>
                </div>

                <!-- Temporizador Countdown Live -->
                <div class="ns-room-modal__timer-box">
                    <div class="ns-room-modal__timer-label">⏳ TIEMPO RESTANTE / EXCEDIDO</div>
                    <div id="drawer-timer-display" class="ns-room-modal__timer-value">00h 00m 00s</div>
                </div>

                <!-- Desglose Itemizado de Folio -->
                <div class="ns-room-modal__card">
                    <h4 class="ns-room-modal__card-title">💳 Estado de Cuenta (Folio #<span id="drawer-folio-id">1</span>)</h4>
                    <table class="ns-room-modal__table">
                        <thead>
                            <tr>
                                <th>Concepto</th>
                                <th>Cantidad</th>
                                <th>Importe</th>
                            </tr>
                        </thead>
                        <tbody id="drawer-folio-charges-body">
                            <tr>
                                <td colspan="3" style="color: #94a3b8; text-align: center;">Cargando balance...</td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="ns-room-modal__balance-summary">
                        <div class="ns-room-modal__balance-row">
                            <span>Total de Cargos:</span>
                            <strong id="drawer-folio-total-amount">S/ 0.00</strong>
                        </div>
                        <div class="ns-room-modal__balance-row" style="color: #34d399;">
                            <span>Total Pagos/Adelanto:</span>
                            <strong id="drawer-folio-total-payments" style="color: #34d399;">S/ 0.00</strong>
                        </div>
                        <div class="ns-room-modal__balance-total">
                            <span>SALDO PENDIENTE A COBRAR:</span>
                            <strong id="drawer-folio-pending-balance" style="color: #f87171; font-size: 1.25rem;">S/ 0.00</strong>
                        </div>
                    </div>
                </div>
            </div>

            <!-- OCUPADA: LIQUIDAR CHECKOUT (SUNAT) -->
            <div id="tab-occ-checkout" class="ns-room-modal__tab-content tab-context-occupied">
                <div class="ns-room-modal__card">
                    <h3 class="ns-room-modal__card-title" style="font-size: 1.15rem; color: #ffffff;">🚪 Liquidación de Salida & Checkout</h3>
                    <form onsubmit="handleDrawerCheckout(event)">
                        <input type="hidden" id="drawer-checkout-room-id">
                        
                        <div id="checkout-paid-box" class="ns-room-modal__card" style="display: none; border-color: rgba(16, 185, 129, 0.4); margin-bottom: 1rem;">
                            <h4 style="color: #34d399; margin: 0 0 4px 0;">✅ Estancia 100% Liquidada</h4>
                            <p style="color: #a7f3d0; margin: 0; font-size: 0.9rem;">El huésped no tiene deudas pendientes. Saldo: <strong>S/ 0.00</strong>.</p>
                        </div>

                        <div id="checkout-pending-box" class="ns-room-modal__card" style="text-align: center; margin-bottom: 1rem;">
                            <span style="font-size: 0.85rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Saldo Pendiente a Cobrar:</span>
                            <h2 id="drawer-checkout-amount" style="color: #f87171; font-size: 2.2rem; margin: 6px 0 0 0; font-weight: 800;">S/ 0.00</h2>
                        </div>

                        <div class="ns-room-modal__form-group">
                            <label class="ns-room-modal__label">Tipo de Comprobante SUNAT:</label>
                            <select id="drawer-checkout-doc-type" class="ns-room-modal__select" onchange="toggleCheckoutInvoiceFields()">
                                <option value="NOTA_VENTA">Nota de Venta Interna</option>
                                <option value="BOLETA">Boleta de Venta Electrónica (B001)</option>
                                <option value="FACTURA">Factura Electrónica (F001)</option>
                            </select>
                        </div>

                        <!-- Campo Condicional Factura SUNAT -->
                        <div id="field-factura-ruc" class="ns-room-modal__form-group" style="display: none;">
                            <label class="ns-room-modal__label" style="color: #38bdf8;">RUC del Cliente Factura (11 dígitos):</label>
                            <input type="text" id="drawer-checkout-ruc" class="ns-room-modal__input" placeholder="Ej: 20601234567" maxlength="11">
                            
                            <label class="ns-room-modal__label" style="color: #38bdf8; margin-top: 8px;">Razón Social:</label>
                            <input type="text" id="drawer-checkout-company" class="ns-room-modal__input" placeholder="Razón social empresa">
                        </div>

                        <div id="checkout-payment-fields">
                            <div class="ns-room-modal__form-group">
                                <label class="ns-room-modal__label">Método de Pago de Liquidación:</label>
                                <select id="drawer-checkout-payment-method" class="ns-room-modal__select">
                                    <option value="cash">💵 Efectivo</option>
                                    <option value="yape">📱 Yape / Plin</option>
                                    <option value="card">💳 Tarjeta de Crédito/Débito</option>
                                    <option value="transfer">🏦 Transferencia Bancaria</option>
                                </select>
                            </div>

                            <div class="ns-room-modal__form-group">
                                <label class="ns-room-modal__label">N° de Operación / Referencia (Opcional):</label>
                                <input type="text" id="drawer-checkout-reference" class="ns-room-modal__input" placeholder="Ej: 981240 / POS-4412">
                            </div>
                        </div>

                        <button type="submit" id="btn-checkout-submit" class="ns-room-modal__btn ns-room-modal__btn--danger" style="margin-top: 1rem;">💵 Procesar Cobro y Finalizar Checkout</button>
                    </form>
                </div>
            </div>

            <!-- OCUPADA: CONSUMOS TIENDA POS -->
            <div id="tab-occ-store" class="ns-room-modal__tab-content tab-context-occupied">
                <h3 class="ns-room-modal__card-title" style="color: #ffffff;">🛒 Cargar Productos de Tienda a la Habitación</h3>
                <div class="ns-room-modal__products-grid" id="drawer-products-container">
                    <p style="color: #94a3b8;">Cargando catálogo...</p>
                </div>
            </div>

            <!-- OCUPADA: HORAS EXTRAS & TRANSFERENCIA -->
            <div id="tab-occ-manage" class="ns-room-modal__tab-content tab-context-occupied">
                <div class="ns-room-modal__card">
                    <h4 class="ns-room-modal__card-title">⏰ Adicionar Horas Extras</h4>
                    <p style="color: #94a3b8; font-size: 0.88rem; margin: 0 0 10px 0;">Seleccione un preset rápido o digite las horas:</p>
                    
                    <div class="ns-room-modal__presets">
                        <button type="button" class="ns-room-modal__preset-btn" onclick="setExtensionPreset(1, 10)">+1h (S/ 10)</button>
                        <button type="button" class="ns-room-modal__preset-btn" onclick="setExtensionPreset(2, 15)">+2h (S/ 15)</button>
                        <button type="button" class="ns-room-modal__preset-btn" onclick="setExtensionPreset(4, 25)">+4h (S/ 25)</button>
                        <button type="button" class="ns-room-modal__preset-btn" onclick="setExtensionPreset(12, 35)">+12h (S/ 35)</button>
                    </div>

                    <div class="ns-room-modal__form-group">
                        <label class="ns-room-modal__label">Horas a adicionar:</label>
                        <input type="number" id="drawer-extension-hours" class="ns-room-modal__input" value="2" min="1" max="24">
                    </div>
                    <div class="ns-room-modal__form-group">
                        <label class="ns-room-modal__label">Importe Adicional (S/):</label>
                        <input type="number" step="1.00" id="drawer-extension-price" class="ns-room-modal__input" value="15.00">
                    </div>
                    <button class="ns-room-modal__btn ns-room-modal__btn--primary" onclick="handleDrawerExtension()">Confirmar y Adicionar Horas</button>
                </div>

                <div class="ns-room-modal__card" style="margin-top: 1.25rem;">
                    <h4 class="ns-room-modal__card-title">🔄 Transferencia de Habitación</h4>
                    <p style="color: #94a3b8; font-size: 0.88rem; margin: 0 0 10px 0;">Mover al huésped actual con todo su folio a otra habitación libre disponible.</p>
                    <div class="ns-room-modal__form-group">
                        <select id="drawer-transfer-target-room" class="ns-room-modal__select">
                            <option value="">Seleccione habitación libre...</option>
                        </select>
                    </div>
                    <button class="ns-room-modal__btn ns-room-modal__btn--warning" onclick="handleDrawerTransfer()">Realizar Transferencia</button>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- MODAL: RECIBO / VOUCHER TÉRMICO TRAS CHECKOUT DE HABITACIÓN -->
<div id="modal-checkout-receipt" class="modal">
    <div class="modal-content" style="max-width: 520px; background: var(--bg-card);">
        <span class="close" onclick="closeCheckoutReceiptModal()">&times;</span>
        <h3 style="color: #ffffff; margin-bottom: 0.8rem; font-size: 1.1rem;">🧾 Liquidación de Salida / Comprobante</h3>

        <iframe id="checkout-receipt-pdf-frame" src="" style="width: 100%; height: 60vh; border: 1px solid var(--border-color); border-radius: var(--radius-sm); background: #ffffff;"></iframe>

        <div class="d-flex justify-content-between align-items-center gap-2 mt-3">
            <div class="d-flex gap-2" id="checkout-receipt-actions">
                <!-- Botones dinámicos de voucher y ESC/POS -->
            </div>
            <button class="btn btn-secondary" onclick="closeCheckoutReceiptModal()">Listo / Finalizar</button>
        </div>
    </div>
</div>
