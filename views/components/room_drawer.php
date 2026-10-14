<?php declare(strict_types=1); ?>
<div id="room-drawer-overlay" class="ns-drawer" style="display: none;" onclick="closeRoomDrawer(event)">
    <div class="ns-drawer__panel" onclick="event.stopPropagation()">
        <!-- Header -->
        <div class="ns-drawer__header">
            <div class="ns-drawer__title-group">
                <h2 id="drawer-room-number" class="ns-drawer__title">Habitación ---</h2>
                <div class="ns-drawer__badges">
                    <span id="drawer-room-status-badge" class="ns-drawer__badge">---</span>
                    <span id="drawer-room-category" class="ns-drawer__badge ns-drawer__badge--category">---</span>
                </div>
            </div>
            <button type="button" class="ns-drawer__close" onclick="closeRoomDrawer()">&times;</button>
        </div>

        <!-- Tab Navigation -->
        <div class="ns-drawer__tabs">
            <button class="ns-drawer__tab active" onclick="switchDrawerTab('tab-summary')">📋 Resumen</button>
            <button class="ns-drawer__tab" onclick="switchDrawerTab('tab-action')">🔑 Operación</button>
            <button class="ns-drawer__tab" onclick="switchDrawerTab('tab-store')">🛒 POS Consumos</button>
            <button class="ns-drawer__tab" onclick="switchDrawerTab('tab-manage')">⚙️ Gestión</button>
        </div>

        <!-- Drawer Content Body -->
        <div class="ns-drawer__body">
            <!-- TAB 1: SUMMARY -->
            <div id="tab-summary" class="ns-drawer__content active">
                <div id="drawer-summary-free" class="ns-drawer__section" style="display: none;">
                    <div class="ns-drawer__alert ns-drawer__alert--success">
                        <h4>🟢 Habitación Lista para Hospedar</h4>
                        <p>Pasa a la pestaña <strong>Operación</strong> o haz clic abajo para realizar el Check-in inmediato.</p>
                    </div>
                    <button class="ns-drawer__btn ns-drawer__btn--primary" onclick="switchToImmediateCheckinForm()">⚡ Iniciar Check-In Ahora</button>
                    <button id="drawer-btn-reserve-future" type="button" class="ns-drawer__btn ns-drawer__btn--secondary" style="margin-top: 10px;" onclick="switchToFutureReservationForm()">📅 Reservar a Futuro</button>

                    <!-- Upcoming Reservations Box for Free Room -->
                    <div id="drawer-room-reservations-box" style="display: none; margin-top: 14px;">
                        <div class="ns-drawer__card" style="border-left: 4px solid #f59e0b;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <h4 class="ns-drawer__card-title" style="margin: 0; color: #fde68a;">📅 Reserva Próxima</h4>
                                <span class="ns-drawer__badge ns-drawer__badge--cleaning" id="drawer-res-status-badge">PENDIENTE</span>
                            </div>
                            <div class="ns-drawer__info-grid">
                                <div class="ns-drawer__info-item"><label>Huésped:</label> <strong id="drawer-res-guest-name">---</strong></div>
                                <div class="ns-drawer__info-item"><label>Documento:</label> <strong id="drawer-res-guest-doc">---</strong></div>
                                <div class="ns-drawer__info-item"><label>Ingreso:</label> <strong id="drawer-res-checkin-time">---</strong></div>
                                <div class="ns-drawer__info-item"><label>Anticipo:</label> <strong id="drawer-res-advance-amount" style="color: #34d399;">S/ 0.00</strong></div>
                            </div>
                            <div style="margin-top: 14px;">
                                <button type="button" class="ns-drawer__btn ns-drawer__btn--success" id="drawer-btn-execute-res-checkin" onclick="executeDrawerReservationCheckin()">🏨 Check-In de esta Reserva</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="drawer-summary-occupied" class="ns-drawer__section" style="display: none;">
                    <!-- Guest Card Summary -->
                    <div class="ns-drawer__card">
                        <h4 class="ns-drawer__card-title">👤 Datos del Huésped Alojado</h4>
                        <div class="ns-drawer__info-grid">
                            <div class="ns-drawer__info-item"><label>Nombre:</label> <strong id="drawer-guest-name">---</strong></div>
                            <div class="ns-drawer__info-item"><label>Documento:</label> <strong id="drawer-guest-doc">---</strong></div>
                            <div class="ns-drawer__info-item"><label>Ingreso:</label> <strong id="drawer-checkin-time">---</strong></div>
                            <div class="ns-drawer__info-item"><label>Salida Prevista:</label> <strong id="drawer-checkout-time">---</strong></div>
                        </div>
                    </div>

                    <!-- Live Countdown Card -->
                    <div class="ns-drawer__timer-box">
                        <div class="ns-drawer__timer-label">⏳ TIEMPO RESTANTE / EXCEDIDO</div>
                        <div id="drawer-timer-display" class="ns-drawer__timer-value">00h 00m 00s</div>
                    </div>

                    <!-- Real Itemized Folio Breakdown Table -->
                    <div class="ns-drawer__card">
                        <h4 class="ns-drawer__card-title">💳 Balance de Cuenta (Folio #<span id="drawer-folio-id">1</span>)</h4>
                        <table class="ns-drawer__table">
                            <thead>
                                <tr>
                                    <th>Concepto</th>
                                    <th>Cant.</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="drawer-folio-charges-body">
                                <tr>
                                    <td colspan="3" style="color: #94a3b8; text-align: center;">Cargando desglose...</td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <div class="ns-drawer__balance-summary">
                            <div class="ns-drawer__balance-row">
                                <span>Total Cargos:</span>
                                <strong id="drawer-folio-total-amount">S/ 0.00</strong>
                            </div>
                            <div class="ns-drawer__balance-row" style="color: #34d399;">
                                <span>Total Pagos/Adelanto:</span>
                                <strong id="drawer-folio-total-payments" style="color: #34d399;">S/ 0.00</strong>
                            </div>
                            <div class="ns-drawer__balance-total">
                                <span>SALDO PENDIENTE:</span>
                                <strong id="drawer-folio-pending-balance" style="color: #f87171; font-size: 1.25rem;">S/ 0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: ACTION (Check-in or Checkout) -->
            <div id="tab-action" class="ns-drawer__content">
                <!-- Check-in / Reservation Form -->
                <div id="drawer-form-checkin" class="ns-drawer__section">
                    <h3 id="drawer-action-form-title" class="ns-drawer__card-title" style="font-size: 1.15rem; color: #ffffff;">🏨 Registro de Hospedaje / Reserva</h3>

                    <!-- Selector de Modo Operativo: Check-In Inmediato vs Reserva Futura -->
                    <div class="ns-drawer__form-group">
                        <label class="ns-drawer__label">Tipo de Operación:</label>
                        <div class="ns-drawer__pill-group">
                            <label class="ns-drawer__pill-option active" id="drawer-pill-op-checkin">
                                <input type="radio" name="operation_mode" value="checkin" checked onchange="toggleDrawerOperationMode('checkin')"> ⚡ Check-In Inmediato
                            </label>
                            <label class="ns-drawer__pill-option" id="drawer-pill-op-reservation">
                                <input type="radio" name="operation_mode" value="reservation" onchange="toggleDrawerOperationMode('reservation')"> 📅 Reserva a Futuro
                            </label>
                        </div>
                    </div>

                    <form onsubmit="handleDrawerActionSubmit(event)">
                        <input type="hidden" id="drawer-checkin-room-id">
                        <input type="hidden" id="drawer-checkin-room-cat-id">
                        <input type="hidden" id="drawer-checkin-reservation-id" value="">

                        <!-- Campo de Fecha de Llegada Futura (Condicional si es Reserva) -->
                        <div id="field-future-arrival" class="ns-drawer__card" style="display: none; border-color: rgba(245, 158, 11, 0.4); margin-bottom: 1rem;">
                            <label class="ns-drawer__label" style="color: #fde68a;">📅 Fecha y Hora de Llegada Prevista:</label>
                            <input type="datetime-local" id="drawer-future-checkin-at" class="ns-drawer__input" onchange="recalculateFutureReservationDates()">
                            <small style="color: #94a3b8; display: block; margin-top: 4px; font-size: 0.8rem;">La salida se calculará automáticamente según la tarifa elegida.</small>
                        </div>

                        <!-- Selector de Huésped Existente (Padrón Central) -->
                        <div class="ns-drawer__form-group">
                            <label class="ns-drawer__label">Elegir de Huéspedes Registrados:</label>
                            <select id="drawer-guest-select" class="ns-drawer__select" onchange="handleDrawerGuestSelectChange(this)">
                                <option value="">-- Seleccionar Huésped Registrado (Opcional) --</option>
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

                        <!-- Tipo de Documento Switcher -->
                        <div class="ns-drawer__form-group">
                            <label class="ns-drawer__label">Tipo de Huésped:</label>
                            <div class="ns-drawer__pill-group">
                                <label class="ns-drawer__pill-option active" id="drawer-pill-dni">
                                    <input type="radio" name="doc_type_mode" value="DNI" checked onchange="toggleGuestDocType('DNI')"> 🪪 Persona (DNI)
                                </label>
                                <label class="ns-drawer__pill-option" id="drawer-pill-ruc">
                                    <input type="radio" name="doc_type_mode" value="RUC" onchange="toggleGuestDocType('RUC')"> 🏢 Empresa (RUC)
                                </label>
                            </div>
                        </div>
                        
                        <div class="ns-drawer__form-group">
                            <label id="lbl-doc-number" class="ns-drawer__label">DNI del Huésped (8 dígitos):</label>
                            <div class="ns-drawer__input-group">
                                <input type="text" id="drawer-checkin-doc-number" class="ns-drawer__input" placeholder="Ingrese DNI" maxlength="8" required>
                                <button type="button" class="ns-drawer__btn ns-drawer__btn--secondary" style="width: auto; min-width: 100px;" onclick="lookupDrawerGuestDocument()">🔍 Buscar</button>
                            </div>
                        </div>

                        <div class="ns-drawer__form-group">
                            <label id="lbl-guest-name" class="ns-drawer__label">Nombre Completo:</label>
                            <input type="text" id="drawer-checkin-guest-name" class="ns-drawer__input" placeholder="Nombres y apellidos del huésped" required>
                        </div>

                        <!-- Campo Condicional Empresa (RUC) -->
                        <div id="field-company-address" class="ns-drawer__form-group" style="display: none;">
                            <label class="ns-drawer__label">Dirección Fiscal de la Empresa:</label>
                            <input type="text" id="drawer-checkin-company-address" class="ns-drawer__input" placeholder="Ej: Av. Principal 123, Lima">
                        </div>

                        <!-- Modo de Hospedaje Switcher -->
                        <div class="ns-drawer__form-group">
                            <label class="ns-drawer__label">Modo de Estancia:</label>
                            <div class="ns-drawer__pill-group">
                                <label class="ns-drawer__pill-option active">
                                    <input type="radio" name="stay_mode" value="tariff" checked onchange="toggleStayMode('tariff')"> ⚡ Tarifa Estándar
                                </label>
                                <label class="ns-drawer__pill-option">
                                    <input type="radio" name="stay_mode" value="custom" onchange="toggleStayMode('custom')"> 📅 Horario Manual Libre
                                </label>
                            </div>
                        </div>

                        <div id="field-tariff-select" class="ns-drawer__form-group">
                            <label class="ns-drawer__label">Tarifa de Hospedaje (<span id="drawer-tariff-category-label">Categoría</span>):</label>
                            <select id="drawer-checkin-tariff-id" class="ns-drawer__select" onchange="updateDrawerTariffPrice()">
                                <option value="">Cargando tarifas...</option>
                            </select>
                            <div id="box-calculated-checkout-preview" style="margin-top: 6px; color: #38bdf8; font-size: 0.88rem; display: none;">
                                ⌛ <strong>Salida Estimada:</strong> <span id="drawer-calculated-checkout-text">---</span>
                            </div>
                        </div>

                        <!-- Campos Condicionales Horario Manual -->
                        <div id="fields-custom-datetime" style="display: none;">
                            <div class="ns-drawer__form-group">
                                <label class="ns-drawer__label">📅 Fecha/Hora de Entrada Manual:</label>
                                <input type="datetime-local" id="drawer-checkin-custom-in" class="ns-drawer__input">
                            </div>

                            <div class="ns-drawer__form-group">
                                <label class="ns-drawer__label">⌛ Fecha/Hora de Salida Libre:</label>
                                <input type="datetime-local" id="drawer-checkin-custom-out" class="ns-drawer__input">
                            </div>
                        </div>

                        <!-- Modo de Precio Switcher -->
                        <div class="ns-drawer__form-group">
                            <label class="ns-drawer__label">Modo de Precio:</label>
                            <div class="ns-drawer__pill-group">
                                <label class="ns-drawer__pill-option active">
                                    <input type="radio" name="price_mode" value="normal" checked onchange="togglePriceMode('normal')"> 💵 Precio Oficial
                                </label>
                                <label class="ns-drawer__pill-option">
                                    <input type="radio" name="price_mode" value="custom" onchange="togglePriceMode('custom')"> ✏️ Modificar Precio
                                </label>
                            </div>
                        </div>

                        <div class="ns-drawer__form-group">
                            <label class="ns-drawer__label">Precio de Hospedaje (S/):</label>
                            <input type="number" step="0.50" id="drawer-checkin-applied-price" class="ns-drawer__input" value="35.00" oninput="calculateAdvanceBalance()" required>
                        </div>

                        <!-- Campo Condicional Motivo Descuento/Modificación -->
                        <div id="field-discount-reason" class="ns-drawer__form-group" style="display: none;">
                            <label class="ns-drawer__label" style="color: #fbbf24;">Motivo del Ajuste de Precio:</label>
                            <input type="text" id="drawer-checkin-discount-reason" class="ns-drawer__input" placeholder="Ej: Tarifa corporativa, cliente frecuente...">
                        </div>

                        <!-- Sección de Cobro de Adelanto Inicial -->
                        <div class="ns-drawer__card" style="margin-top: 1rem;">
                            <h4 class="ns-drawer__card-title" style="color: #38bdf8;">💳 Pago / Adelanto en Recepción</h4>
                            
                            <div class="ns-drawer__form-group">
                                <label class="ns-drawer__label">Monto de Adelanto Pagado (S/):</label>
                                <input type="number" step="0.50" id="drawer-checkin-advance-amount" class="ns-drawer__input" value="0.00" min="0" oninput="calculateAdvanceBalance()">
                                <small style="color: #94a3b8; display: block; margin-top: 4px; font-size: 0.8rem;">Ingrese 0.00 si paga el 100% al salir, o el total si cancela completo.</small>
                            </div>

                            <div class="ns-drawer__form-group">
                                <label class="ns-drawer__label">Método de Pago del Adelanto:</label>
                                <select id="drawer-checkin-advance-method" class="ns-drawer__select">
                                    <option value="cash">💵 Efectivo</option>
                                    <option value="yape">📱 Yape / Plin</option>
                                    <option value="card">💳 Tarjeta Crédito/Débito</option>
                                    <option value="transfer">🏦 Transferencia Bancaria</option>
                                </select>
                            </div>

                            <div class="ns-drawer__balance-total" style="margin-top: 1rem;">
                                <span>SALDO RESTANTE A PAGAR:</span>
                                <strong id="drawer-checkin-remaining-balance" style="color: #f87171; font-size: 1.15rem;">S/ 35.00</strong>
                            </div>
                        </div>

                        <button type="submit" id="btn-drawer-action-submit" class="ns-drawer__btn ns-drawer__btn--success" style="margin-top: 1rem;">✅ Confirmar Check-In y Registrar</button>
                    </form>
                </div>

                <!-- Checkout Form -->
                <div id="drawer-form-checkout" class="ns-drawer__section" style="display: none;">
                    <h3 class="ns-drawer__card-title" style="font-size: 1.15rem; color: #ffffff;">🚪 Checkout & Cobro Final</h3>
                    <form onsubmit="handleDrawerCheckout(event)">
                        <input type="hidden" id="drawer-checkout-room-id">
                        
                        <div id="checkout-paid-box" class="ns-drawer__alert ns-drawer__alert--success" style="display: none;">
                            <h4>✅ Estancia 100% Liquidada</h4>
                            <p>El huésped no tiene deudas pendientes. Saldo: <strong>S/ 0.00</strong>.</p>
                        </div>

                        <div id="checkout-pending-box" class="ns-drawer__card" style="text-align: center; margin-bottom: 1rem;">
                            <span style="font-size: 0.85rem; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Saldo Pendiente a Cobrar:</span>
                            <h2 id="drawer-checkout-amount" style="color: #f87171; font-size: 2rem; margin: 6px 0 0 0; font-weight: 800;">S/ 0.00</h2>
                        </div>

                        <div class="ns-drawer__form-group">
                            <label class="ns-drawer__label">Emitir Comprobante SUNAT:</label>
                            <select id="drawer-checkout-doc-type" class="ns-drawer__select" onchange="toggleCheckoutInvoiceFields()">
                                <option value="NOTA_VENTA">Nota de Venta Interna</option>
                                <option value="BOLETA">Boleta de Venta Electrónica (B001)</option>
                                <option value="FACTURA">Factura Electrónica (F001)</option>
                            </select>
                        </div>

                        <!-- Campo Condicional Factura SUNAT -->
                        <div id="field-factura-ruc" class="ns-drawer__form-group" style="display: none;">
                            <label class="ns-drawer__label" style="color: #38bdf8;">RUC del Cliente Factura (11 dígitos):</label>
                            <input type="text" id="drawer-checkout-ruc" class="ns-drawer__input" placeholder="Ej: 20601234567" maxlength="11">
                            
                            <label class="ns-drawer__label" style="color: #38bdf8; margin-top: 8px;">Razón Social:</label>
                            <input type="text" id="drawer-checkout-company" class="ns-drawer__input" placeholder="Razón social empresa">
                        </div>

                        <div id="checkout-payment-fields">
                            <div class="ns-drawer__form-group">
                                <label class="ns-drawer__label">Método de Pago de Liquidación:</label>
                                <select id="drawer-checkout-payment-method" class="ns-drawer__select">
                                    <option value="cash">💵 Efectivo</option>
                                    <option value="yape">📱 Yape / Plin</option>
                                    <option value="card">💳 Tarjeta de Crédito/Débito</option>
                                    <option value="transfer">🏦 Transferencia Bancaria</option>
                                </select>
                            </div>

                            <div class="ns-drawer__form-group">
                                <label class="ns-drawer__label">N° de Operación / Referencia (Opcional):</label>
                                <input type="text" id="drawer-checkout-reference" class="ns-drawer__input" placeholder="Ej: 981240 / POS-4412">
                            </div>
                        </div>

                        <button type="submit" id="btn-checkout-submit" class="ns-drawer__btn ns-drawer__btn--danger" style="margin-top: 1rem;">💵 Procesar Pago y Finalizar Checkout</button>
                    </form>
                </div>
            </div>

            <!-- TAB 3: POS STORE CHARGES -->
            <div id="tab-store" class="ns-drawer__content">
                <h3 class="ns-drawer__card-title" style="color: #ffffff;">🛒 Cargar Productos a la Habitación</h3>
                <div class="ns-drawer__products-grid" id="drawer-products-container">
                    <p style="color: #94a3b8;">Cargando catálogo...</p>
                </div>
            </div>

            <!-- TAB 4: MANAGEMENT (Transfer, Extension, Cleaning) -->
            <div id="tab-manage" class="ns-drawer__content">
                <h3 class="ns-drawer__card-title" style="color: #ffffff;">⚙️ Opciones de Gestión</h3>

                <div class="ns-drawer__card">
                    <h4 class="ns-drawer__card-title">🔄 Transferir Habitación</h4>
                    <p style="color: #94a3b8; font-size: 0.88rem; margin: 0 0 10px 0;">Mover el huésped actual a otra habitación libre.</p>
                    <div class="ns-drawer__form-group">
                        <select id="drawer-transfer-target-room" class="ns-drawer__select">
                            <option value="">Seleccione habitación libre...</option>
                        </select>
                    </div>
                    <button class="ns-drawer__btn ns-drawer__btn--warning" onclick="handleDrawerTransfer()">Realizar Transferencia</button>
                </div>

                <div class="ns-drawer__card" style="margin-top: 1rem;">
                    <h4 class="ns-drawer__card-title">⏰ Extender Estancia (Horas Extra)</h4>
                    <p style="color: #94a3b8; font-size: 0.88rem; margin: 0 0 10px 0;">Presione un acceso rápido o ingrese horas y precio manualmente:</p>
                    
                    <div class="ns-drawer__presets">
                        <button type="button" class="ns-drawer__preset-btn" onclick="setExtensionPreset(1, 10)">+1h (S/ 10)</button>
                        <button type="button" class="ns-drawer__preset-btn" onclick="setExtensionPreset(2, 15)">+2h (S/ 15)</button>
                        <button type="button" class="ns-drawer__preset-btn" onclick="setExtensionPreset(4, 25)">+4h (S/ 25)</button>
                        <button type="button" class="ns-drawer__preset-btn" onclick="setExtensionPreset(12, 35)">+12h (S/ 35)</button>
                    </div>

                    <div class="ns-drawer__form-group">
                        <label class="ns-drawer__label">Horas a adicionar:</label>
                        <input type="number" id="drawer-extension-hours" class="ns-drawer__input" value="2" min="1" max="24">
                    </div>
                    <div class="ns-drawer__form-group">
                        <label class="ns-drawer__label">Monto Adicional (S/):</label>
                        <input type="number" step="1.00" id="drawer-extension-price" class="ns-drawer__input" value="15.00">
                    </div>
                    <button class="ns-drawer__btn ns-drawer__btn--primary" onclick="handleDrawerExtension()">Adicionar Horas</button>
                </div>

                <div class="ns-drawer__card" style="margin-top: 1rem;">
                    <h4 class="ns-drawer__card-title">🧹 Estado de Limpieza / Mantenimiento</h4>
                    <button class="ns-drawer__btn ns-drawer__btn--secondary" onclick="handleDrawerSetCleaning()">Marcar para Limpieza</button>
                    <button class="ns-drawer__btn ns-drawer__btn--success" style="margin-top: 8px;" onclick="handleDrawerReleaseCleaning()">Liberar Habitación (Limpia)</button>
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
