let currentDrawerRoomId = null;
let currentRoomCategoryId = 1;
let globalTariffsCatalog = [];
let drawerTimerInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    loadTariffsCatalog();
});

async function loadTariffsCatalog() {
    try {
        const res = await apiFetch('/api/tariffs');
        if (res.ok && res.data.data) {
            globalTariffsCatalog = res.data.data;
        }
    } catch (e) {
        console.error('Error cargando catálogo de tarifas:', e);
    }
}

function calculateAdvanceBalance() {
    const priceInput = document.getElementById('drawer-checkin-applied-price');
    const advanceInput = document.getElementById('drawer-checkin-advance-amount');
    const balanceDisplay = document.getElementById('drawer-checkin-remaining-balance');

    const price = parseFloat(priceInput.value) || 0.0;
    const advance = parseFloat(advanceInput.value) || 0.0;
    const remaining = Math.max(0, price - advance);

    if (balanceDisplay) {
        balanceDisplay.textContent = `S/ ${remaining.toFixed(2)}`;
        if (remaining === 0) {
            balanceDisplay.className = 'text-success';
        } else {
            balanceDisplay.className = 'text-danger';
        }
    }
}

function setExtensionPreset(hours, price) {
    const hoursInput = document.getElementById('drawer-extension-hours');
    const priceInput = document.getElementById('drawer-extension-price');

    if (hoursInput) hoursInput.value = hours;
    if (priceInput) priceInput.value = parseFloat(price).toFixed(2);
}

function populateTariffSelectForCategory(categoryId) {
    const select = document.getElementById('drawer-checkin-tariff-id');
    const label = document.getElementById('drawer-tariff-category-label');
    if (!select) return;

    select.innerHTML = '';
    const matchingTariffs = globalTariffsCatalog.filter(t => t.room_category_id === categoryId);

    if (matchingTariffs.length > 0) {
        if (label) label.textContent = matchingTariffs[0].category_name;
        matchingTariffs.forEach(t => {
            const opt = document.createElement('option');
            opt.value = t.id;
            opt.setAttribute('data-price', t.price);
            opt.textContent = `${t.category_name} - ${t.name} (${t.duration_hours}h) - S/ ${parseFloat(t.price).toFixed(2)}`;
            select.appendChild(opt);
        });
        updateDrawerTariffPrice();
    } else {
        if (label) label.textContent = 'General';
        select.innerHTML = '<option value="1" data-price="35.00">Tarifa General (S/ 35.00)</option>';
        updateDrawerTariffPrice();
    }
}

let currentDrawerReservation = null;

function handleDrawerGuestSelectChange(selectElem) {
    if (!selectElem) return;
    const selectedOption = selectElem.options[selectElem.selectedIndex];
    if (!selectedOption || !selectedOption.value) {
        return;
    }

    const docType = selectedOption.getAttribute('data-doc-type') || 'DNI';
    const docNumber = selectedOption.getAttribute('data-doc-number') || '';
    const name = selectedOption.getAttribute('data-name') || '';
    const address = selectedOption.getAttribute('data-address') || '';

    toggleGuestDocType(docType);

    const docInput = document.getElementById('drawer-checkin-doc-number');
    const nameInput = document.getElementById('drawer-checkin-guest-name');
    const addressInput = document.getElementById('drawer-checkin-company-address');

    if (docInput) docInput.value = docNumber;
    if (nameInput) nameInput.value = name;
    if (addressInput && address) addressInput.value = address;
}

function toggleGuestDocType(mode) {
    const lblDoc = document.getElementById('lbl-doc-number');
    const lblName = document.getElementById('lbl-guest-name');
    const inputDoc = document.getElementById('drawer-checkin-doc-number');
    const companyField = document.getElementById('field-company-address');

    const pills = document.querySelectorAll('input[name="doc_type_mode"]');
    pills.forEach(p => {
        const parent = p.closest('.ns-room-modal__pill-option, .ns-drawer__pill-option, .pill-option');
        if (parent) {
            if (p.value === mode) parent.classList.add('active');
            else parent.classList.remove('active');
        }
    });

    if (mode === 'RUC') {
        if (lblDoc) lblDoc.textContent = 'RUC de la Empresa (11 dígitos):';
        if (lblName) lblName.textContent = 'Razón Social:';
        if (inputDoc) {
            inputDoc.placeholder = 'Ingrese RUC (11 dígitos)';
            inputDoc.maxLength = 11;
        }
        if (companyField) companyField.style.display = 'block';
    } else {
        if (lblDoc) lblDoc.textContent = 'DNI del Huésped (8 dígitos):';
        if (lblName) lblName.textContent = 'Nombre Completo:';
        if (inputDoc) {
            inputDoc.placeholder = 'Ingrese DNI (8 dígitos)';
            inputDoc.maxLength = 8;
        }
        if (companyField) companyField.style.display = 'none';
    }
}

function toggleStayMode(mode) {
    const tariffField = document.getElementById('field-tariff-select');
    const customFields = document.getElementById('fields-custom-datetime');

    const pills = document.querySelectorAll('input[name="stay_mode"]');
    pills.forEach(p => {
        const parent = p.closest('.ns-room-modal__pill-option, .ns-drawer__pill-option, .pill-option');
        if (parent) {
            if (p.value === mode) parent.classList.add('active');
            else parent.classList.remove('active');
        }
    });

    if (mode === 'custom') {
        if (tariffField) tariffField.style.display = 'none';
        if (customFields) customFields.style.display = 'block';
    } else {
        if (tariffField) tariffField.style.display = 'block';
        if (customFields) customFields.style.display = 'none';
    }
}

function togglePriceMode(mode) {
    const discountField = document.getElementById('field-discount-reason');
    const priceInput = document.getElementById('drawer-checkin-applied-price');

    const pills = document.querySelectorAll('input[name="price_mode"]');
    pills.forEach(p => {
        const parent = p.closest('.ns-room-modal__pill-option, .ns-drawer__pill-option, .pill-option');
        if (parent) {
            if (p.value === mode) parent.classList.add('active');
            else parent.classList.remove('active');
        }
    });

    if (mode === 'custom') {
        if (discountField) discountField.style.display = 'block';
        if (priceInput) priceInput.focus();
    } else {
        if (discountField) discountField.style.display = 'none';
        updateDrawerTariffPrice();
    }
}

let currentDrawerOperationMode = 'checkin';

function switchToImmediateCheckinForm() {
    switchDrawerTab('tab-free-checkin');
    toggleDrawerOperationMode('checkin');
}

function switchToFutureReservationForm() {
    switchDrawerTab('tab-free-checkin');
    toggleDrawerOperationMode('reservation');
}

function toggleDrawerOperationMode(mode) {
    currentDrawerOperationMode = mode;

    const pills = document.querySelectorAll('input[name="operation_mode"]');
    pills.forEach(p => {
        const parent = p.closest('.ns-room-modal__pill-option, .ns-drawer__pill-option, .pill-option');
        if (parent) {
            if (p.value === mode) parent.classList.add('active');
            else parent.classList.remove('active');
        }
    });

    const formTitle = document.getElementById('drawer-action-form-title');
    const futureArrivalBox = document.getElementById('field-future-arrival');
    const btnSubmit = document.getElementById('btn-drawer-action-submit');
    const previewBox = document.getElementById('box-calculated-checkout-preview');
    const futureInput = document.getElementById('drawer-future-checkin-at');

    if (mode === 'reservation') {
        if (formTitle) formTitle.textContent = '📅 Agendar Reserva Futura';
        if (futureArrivalBox) futureArrivalBox.style.display = 'block';
        if (previewBox) previewBox.style.display = 'block';
        if (btnSubmit) {
            btnSubmit.textContent = '📅 Confirmar y Agendar Reserva Futura';
            btnSubmit.className = 'ns-drawer__btn ns-drawer__btn--warning';
            btnSubmit.style.marginTop = '1rem';
        }

        // Set default future date if empty (tomorrow 14:00)
        if (futureInput && !futureInput.value) {
            const tmrw = new Date();
            tmrw.setDate(tmrw.getDate() + 1);
            tmrw.setHours(14, 0, 0, 0);
            const y = tmrw.getFullYear();
            const m = String(tmrw.getMonth() + 1).padStart(2, '0');
            const d = String(tmrw.getDate()).padStart(2, '0');
            const h = String(tmrw.getHours()).padStart(2, '0');
            const min = String(tmrw.getMinutes()).padStart(2, '0');
            futureInput.value = `${y}-${m}-${d}T${h}:${min}`;
        }
        recalculateFutureReservationDates();
    } else {
        if (formTitle) formTitle.textContent = '🏨 Registro de Check-In Express';
        if (futureArrivalBox) futureArrivalBox.style.display = 'none';
        if (previewBox) previewBox.style.display = 'none';
        if (btnSubmit) {
            btnSubmit.textContent = '✅ Confirmar Check-In y Registrar';
            btnSubmit.className = 'ns-drawer__btn ns-drawer__btn--success';
            btnSubmit.style.marginTop = '1rem';
        }
    }
}

function recalculateFutureReservationDates() {
    const tariffSelect = document.getElementById('drawer-checkin-tariff-id');
    const futureInput = document.getElementById('drawer-future-checkin-at');
    const previewText = document.getElementById('drawer-calculated-checkout-text');

    if (!tariffSelect || !futureInput || !futureInput.value || !previewText) return;

    const tariffId = parseInt(tariffSelect.value, 10);
    const tariff = globalTariffsCatalog.find(t => t.id === tariffId);

    const arrivalDate = new Date(futureInput.value);
    if (isNaN(arrivalDate.getTime())) {
        previewText.textContent = 'Fecha inválida';
        return;
    }

    const durationHours = tariff ? (parseInt(tariff.duration_hours, 10) || 24) : 24;
    const checkoutDate = new Date(arrivalDate.getTime() + (durationHours * 60 * 60 * 1000));

    const y = checkoutDate.getFullYear();
    const m = String(checkoutDate.getMonth() + 1).padStart(2, '0');
    const d = String(checkoutDate.getDate()).padStart(2, '0');
    const h = String(checkoutDate.getHours()).padStart(2, '0');
    const min = String(checkoutDate.getMinutes()).padStart(2, '0');

    previewText.textContent = `${d}/${m}/${y} ${h}:${min} (${durationHours} horas)`;
}

function openRoomDrawer(roomId) {
    currentDrawerRoomId = roomId;
    const card = document.querySelector(`.room-card[data-room-id="${roomId}"]`);
    if (!card) return;

    const roomNumber = card.getAttribute('data-room-number') || '---';
    const status = card.getAttribute('data-status') || 'free';
    const categoryName = card.getAttribute('data-category-name') || 'General';
    const categoryId = parseInt(card.getAttribute('data-category-id') || '1', 10);
    const isOverdue = card.getAttribute('data-is-overdue') === '1' || parseInt(card.getAttribute('data-seconds-remaining') || '1', 10) <= 0;
    currentRoomCategoryId = categoryId;

    const elRoomNum = document.getElementById('drawer-room-number');
    if (elRoomNum) elRoomNum.textContent = `Habitación ${roomNumber}`;

    const elRoomCat = document.getElementById('drawer-room-category');
    if (elRoomCat) elRoomCat.textContent = `Categoría ${categoryName}`;

    const elCheckinRoomId = document.getElementById('drawer-checkin-room-id');
    if (elCheckinRoomId) elCheckinRoomId.value = roomId;

    const elCheckinRoomCatId = document.getElementById('drawer-checkin-room-cat-id');
    if (elCheckinRoomCatId) elCheckinRoomCatId.value = categoryId;

    const elCheckoutRoomId = document.getElementById('drawer-checkout-room-id');
    if (elCheckoutRoomId) elCheckoutRoomId.value = roomId;

    const badge = document.getElementById('drawer-room-status-badge');
    if (badge) badge.className = 'ns-drawer__badge';

    const freeSec = document.getElementById('drawer-summary-free');
    const occSec = document.getElementById('drawer-summary-occupied');
    const formCheckin = document.getElementById('drawer-form-checkin');
    const formCheckout = document.getElementById('drawer-form-checkout');

    if (drawerTimerInterval) {
        clearInterval(drawerTimerInterval);
        drawerTimerInterval = null;
    }

    if (freeSec) freeSec.style.display = status === 'free' ? 'block' : 'none';
    if (occSec) occSec.style.display = status === 'occupied' ? 'block' : 'none';
    if (formCheckin) formCheckin.style.display = status === 'free' ? 'block' : 'none';
    if (formCheckout) formCheckout.style.display = status === 'occupied' ? 'block' : 'none';

    if (status === 'free') {
        if (badge) {
            badge.textContent = 'LIBRE';
            badge.classList.add('ns-drawer__badge--free');
        }

        const btnReserveFuture = document.getElementById('drawer-btn-reserve-future');
        if (btnReserveFuture) {
            btnReserveFuture.href = `/reservas?action=new&room_id=${roomId}`;
        }

        // Check for upcoming active reservations for this room
        const resBox = document.getElementById('drawer-room-reservations-box');
        currentDrawerReservation = null;
        if (resBox) {
            let upcomingRes = null;
            if (window.allReservationsCatalog && Array.isArray(window.allReservationsCatalog)) {
                upcomingRes = window.allReservationsCatalog.find(r => 
                    parseInt(r.room_id, 10) === parseInt(roomId, 10) && 
                    (r.status === 'confirmed' || r.status === 'pending')
                );
            }

            if (upcomingRes) {
                currentDrawerReservation = upcomingRes;
                resBox.style.display = 'block';
                const guestName = upcomingRes.guest_name || 'Huésped';
                const guestDoc = upcomingRes.guest_doc || '---';
                const checkinTime = upcomingRes.check_in_date ? upcomingRes.check_in_date.replace('T', ' ') : '---';

                const gNameEl = document.getElementById('drawer-res-guest-name');
                if (gNameEl) gNameEl.textContent = guestName;

                const gDocEl = document.getElementById('drawer-res-guest-doc');
                if (gDocEl) gDocEl.textContent = guestDoc;

                const gInEl = document.getElementById('drawer-res-checkin-time');
                if (gInEl) gInEl.textContent = checkinTime;

                const advAmount = document.getElementById('drawer-res-advance-amount');
                if (advAmount) advAmount.textContent = parseFloat(upcomingRes.advance_payment) > 0 ? `S/ ${parseFloat(upcomingRes.advance_payment).toFixed(2)}` : 'S/ 0.00 (Sin adelanto)';
            } else {
                resBox.style.display = 'none';
            }
        }

        populateTariffSelectForCategory(categoryId);
    } else if (status === 'occupied') {
        if (badge) {
            if (isOverdue) {
                badge.textContent = '⚠️ TIEMPO CUMPLIDO';
                badge.classList.add('ns-drawer__badge--overdue');
            } else {
                badge.textContent = 'OCUPADA';
                badge.classList.add('ns-drawer__badge--occupied');
            }
        }
        if (freeSec) freeSec.style.display = 'none';
        if (occSec) occSec.style.display = 'block';
        if (formCheckin) formCheckin.style.display = 'none';
        if (formCheckout) formCheckout.style.display = 'block';

        const guestName = card.getAttribute('data-guest-name') || 'Huésped Registrado';
        const guestDoc = card.getAttribute('data-guest-doc') || '---';
        const checkinAt = card.getAttribute('data-checkin-at') || '---';
        const checkoutAt = card.getAttribute('data-checkout-at') || '---';
        const secondsRemaining = parseInt(card.getAttribute('data-seconds-remaining') || '0', 10);

        const elGuestName = document.getElementById('drawer-guest-name');
        if (elGuestName) elGuestName.textContent = guestName;

        const elGuestDoc = document.getElementById('drawer-guest-doc');
        if (elGuestDoc) elGuestDoc.textContent = guestDoc;

        const elCheckinTime = document.getElementById('drawer-checkin-time');
        if (elCheckinTime) elCheckinTime.textContent = checkinAt;

        const elCheckoutTime = document.getElementById('drawer-checkout-time');
        if (elCheckoutTime) elCheckoutTime.textContent = checkoutAt;

        initDrawerTimer(secondsRemaining);
        loadDrawerFolioData(roomId);
    } else if (status === 'reserved') {
        if (badge) {
            badge.textContent = 'RESERVADA';
            badge.classList.add('ns-drawer__badge--free');
            badge.style.background = '#8b5cf6';
        }
        if (freeSec) freeSec.style.display = 'block';
        if (occSec) occSec.style.display = 'none';
        renderRoomCalendarList();
    } else if (status === 'cleaning') {
        if (badge) {
            badge.textContent = 'LIMPIEZA';
            badge.classList.add('ns-drawer__badge--cleaning');
        }
        if (freeSec) freeSec.style.display = 'block';
        if (occSec) occSec.style.display = 'none';
    } else if (status === 'maintenance') {
        if (badge) {
            badge.textContent = 'AVERÍA';
            badge.classList.add('ns-drawer__badge--category');
            badge.style.background = '#ef4444';
        }
        if (freeSec) freeSec.style.display = 'block';
        if (occSec) occSec.style.display = 'none';
    } else {
        if (badge) {
            badge.textContent = status.toUpperCase();
            badge.classList.add('ns-drawer__badge--category');
        }
        if (freeSec) freeSec.style.display = 'block';
        if (occSec) occSec.style.display = 'none';
    }

    populateTransferTargetRooms(roomId);
    if (typeof loadDrawerPOSProducts === 'function') {
        loadDrawerPOSProducts();
    }

    // Ocultar o mostrar exclusivamente los BOTONES de pestañas en la barra superior
    const tabButtons = document.querySelectorAll('.ns-room-modal__tabs .ns-room-modal__tab');
    tabButtons.forEach(btn => {
        btn.style.display = 'none';
    });

    if (status === 'occupied') {
        document.querySelectorAll('.ns-room-modal__tabs .tab-context-occupied').forEach(btn => { btn.style.display = ''; });
        switchDrawerTab('tab-occ-summary');
    } else if (status === 'cleaning') {
        document.querySelectorAll('.ns-room-modal__tabs .tab-context-cleaning').forEach(btn => { btn.style.display = ''; });
        switchDrawerTab('tab-cleaning-action');
    } else if (status === 'maintenance') {
        document.querySelectorAll('.ns-room-modal__tabs .tab-context-maintenance').forEach(btn => { btn.style.display = ''; });
        switchDrawerTab('tab-maint-action');
    } else if (status === 'reserved') {
        document.querySelectorAll('.ns-room-modal__tabs .tab-context-reserved').forEach(btn => { btn.style.display = ''; });
        switchDrawerTab('tab-free-checkin');
    } else {
        // Estado 'free'
        document.querySelectorAll('.ns-room-modal__tabs .tab-context-free').forEach(btn => { btn.style.display = ''; });
        switchDrawerTab('tab-free-checkin');
    }

    const overlay = document.getElementById('room-drawer-overlay');
    if (overlay) {
        overlay.style.display = 'flex';
    }
}

let roomFullCalendarInstance = null;

function renderRoomCalendarList() {
    const calendarEl = document.getElementById('drawer-fullcalendar-mount');
    const listContainer = document.getElementById('drawer-room-calendar-list');
    const dayTitleEl = document.getElementById('calendar-selected-day-title');

    if (!calendarEl || !listContainer) return;

    if (!currentDrawerRoomId) {
        listContainer.innerHTML = '<p style="color: #94a3b8; text-align: center;">Seleccione una habitación.</p>';
        return;
    }

    // Filtrar reservas activas y confirmadas de la habitación actual
    const roomReservations = (window.allReservationsCatalog || []).filter(r => 
        parseInt(r.room_id, 10) === parseInt(currentDrawerRoomId, 10) && 
        (r.status === 'confirmed' || r.status === 'pending')
    );

    // Mapear reservas a eventos de FullCalendar
    const events = roomReservations.map(r => {
        const isConfirmed = r.status === 'confirmed';
        const start = r.check_in_date || r.reserved_check_in_at;
        const end = r.check_out_date || r.reserved_check_out_at;
        const advance = parseFloat(r.advance_payment || 0);

        return {
            id: 'res-' + r.id,
            title: `👤 ${r.guest_name || 'Reserva'} (S/ ${advance.toFixed(2)})`,
            start: start,
            end: end,
            backgroundColor: isConfirmed ? '#8b5cf6' : '#f59e0b',
            borderColor: isConfirmed ? '#7c3aed' : '#d97706',
            textColor: '#ffffff',
            extendedProps: {
                guest_name: r.guest_name,
                guest_doc: r.guest_doc || (r.guest_document_type ? `${r.guest_document_type}: ${r.guest_document_number}` : '---'),
                status: r.status,
                advance_payment: advance,
                notes: r.notes || ''
            }
        };
    });

    // Destruir instancia previa si existe para evitar duplicidad o fugas
    if (roomFullCalendarInstance) {
        try {
            roomFullCalendarInstance.destroy();
        } catch (e) {
            console.error('Error destroying previous calendar instance:', e);
        }
        roomFullCalendarInstance = null;
    }

    if (typeof FullCalendar !== 'undefined') {
        roomFullCalendarInstance = new FullCalendar.Calendar(calendarEl, {
            initialView: 'timeGridWeek',
            locale: 'es',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            buttonText: {
                today: 'Hoy',
                month: 'Mes',
                week: 'Semana',
                day: 'Día'
            },
            slotMinTime: '00:00:00',
            slotMaxTime: '24:00:00',
            slotDuration: '01:00:00',
            slotLabelInterval: '02:00:00',
            slotLabelFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            nowIndicator: true,
            allDaySlot: false,
            selectable: true,
            selectMirror: true,
            select: function(info) {
                // Al seleccionar un rango horario libre, precargar formulario de reserva
                switchToCheckinForm();
                toggleDrawerOperationMode('reservation');
                const inInput = document.getElementById('drawer-future-checkin-at');
                if (inInput) {
                    const startLocal = info.startStr.substring(0, 16);
                    inInput.value = startLocal;
                    recalculateFutureReservationDates();
                }
            },
            eventClick: function(info) {
                const props = info.event.extendedProps;
                if (dayTitleEl) {
                    dayTitleEl.innerHTML = `📌 Detalle: <span style="color: #38bdf8;">${info.event.title}</span>`;
                }
            },
            events: events,
            height: 'auto',
            themeSystem: 'standard'
        });

        // Delay para renderizar correctamente una vez que la pestaña es visible
        setTimeout(() => {
            if (roomFullCalendarInstance) {
                roomFullCalendarInstance.render();
                roomFullCalendarInstance.updateSize();
            }
        }, 80);
    }

    // Renderizar lista resumen inferior
    if (roomReservations.length === 0) {
        listContainer.innerHTML = `
            <div style="text-align: center; padding: 1.25rem 1rem; background: rgba(255,255,255,0.02); border-radius: 8px; border: 1px dashed rgba(255,255,255,0.1);">
                <div style="font-size: 1.8rem; margin-bottom: 4px;">🟢</div>
                <h4 style="color: #34d399; margin: 0 0 4px 0; font-size: 0.92rem;">Sin Reservas en Agenda</h4>
                <p style="color: #94a3b8; font-size: 0.82rem; margin: 0;">Esta habitación tiene disponibilidad horaria total. Haz clic o arrastra en cualquier franja para agendar.</p>
            </div>
        `;
        return;
    }

    let html = '';
    roomReservations.forEach(r => {
        const isConfirmed = r.status === 'confirmed';
        const inDate = r.check_in_date ? r.check_in_date.replace('T', ' ') : (r.reserved_check_in_at || '---');
        const outDate = r.check_out_date ? r.check_out_date.replace('T', ' ') : (r.reserved_check_out_at || '---');
        const advance = parseFloat(r.advance_payment || 0);

        html += `
            <div style="background: #1e293b; border: 1px solid rgba(255,255,255,0.08); border-left: 4px solid ${isConfirmed ? '#8b5cf6' : '#f59e0b'}; border-radius: 8px; padding: 10px 12px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <strong style="color: #ffffff; font-size: 0.92rem;">👤 ${r.guest_name || 'Huésped Registrado'}</strong>
                    <span style="background: ${isConfirmed ? 'rgba(139,92,246,0.2)' : 'rgba(245,158,11,0.2)'}; color: ${isConfirmed ? '#c4b5fd' : '#fbbf24'}; padding: 2px 8px; border-radius: 9999px; font-size: 0.72rem; font-weight: 700;">
                        ${isConfirmed ? '🟣 CONFIRMADA' : '⏳ PENDIENTE'}
                    </span>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 6px; font-size: 0.82rem; color: #94a3b8;">
                    <div>📅 <strong>Llegada:</strong> ${inDate}</div>
                    <div>⌛ <strong>Salida:</strong> ${outDate}</div>
                    <div>🪪 <strong>Doc:</strong> ${r.guest_doc || (r.guest_document_type ? `${r.guest_document_type}: ${r.guest_document_number}` : '---')}</div>
                    <div>💳 <strong>Adelanto:</strong> <span style="color: #34d399; font-weight: 600;">S/ ${advance.toFixed(2)}</span></div>
                </div>
            </div>
        `;
    });

    listContainer.innerHTML = html;
}

function initDrawerTimer(initialSeconds) {
    const timerDisplay = document.getElementById('drawer-timer-display');
    if (!timerDisplay) return;

    let seconds = initialSeconds;

    const updateDisplay = () => {
        if (seconds < 0) {
            const abs = Math.abs(seconds);
            const hrs = String(Math.floor(abs / 3600)).padStart(2, '0');
            const mins = String(Math.floor((abs % 3600) / 60)).padStart(2, '0');
            const secs = String(abs % 60).padStart(2, '0');
            timerDisplay.textContent = `⚠️ EXCEDIDO: +${hrs}h ${mins}m ${secs}s`;
            timerDisplay.style.color = '#ef4444';
        } else {
            const hrs = String(Math.floor(seconds / 3600)).padStart(2, '0');
            const mins = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
            const secs = String(seconds % 60).padStart(2, '0');
            timerDisplay.textContent = `${hrs}h ${mins}m ${secs}s`;
            timerDisplay.style.color = seconds <= 1800 ? '#f59e0b' : '#38bdf8';
        }
        seconds--;
    };

    updateDisplay();
    drawerTimerInterval = setInterval(updateDisplay, 1000);
}

function closeRoomDrawer(e) {
    if (e && e.target !== e.currentTarget) return;
    if (drawerTimerInterval) {
        clearInterval(drawerTimerInterval);
        drawerTimerInterval = null;
    }
    const overlay = document.getElementById('room-drawer-overlay');
    overlay.style.display = 'none';
}

function switchDrawerTab(tabId) {
    const tabs = document.querySelectorAll('.ns-room-modal__tab, .drawer-tab, .ns-drawer__tab');
    const contents = document.querySelectorAll('.ns-room-modal__tab-content, .drawer-tab-content, .ns-drawer__content');

    tabs.forEach(t => {
        t.classList.remove('active');
    });
    contents.forEach(c => {
        c.classList.remove('active');
        c.style.display = 'none';
    });

    const activeContent = document.getElementById(tabId);
    if (activeContent) {
        activeContent.classList.add('active');
        activeContent.style.display = 'block';
    }

    const activeTabs = document.querySelectorAll(`[onclick*="${tabId}"]`);
    activeTabs.forEach(t => {
        if (t.style.display !== 'none') {
            t.classList.add('active');
        }
    });
}

function switchToCheckinForm() {
    switchDrawerTab('tab-free-checkin');
}

async function loadDrawerFolioData(roomId) {
    const tbody = document.getElementById('drawer-folio-charges-body');
    const totalChargesEl = document.getElementById('drawer-folio-total-amount');
    const totalPaymentsEl = document.getElementById('drawer-folio-total-payments');
    const pendingBalanceEl = document.getElementById('drawer-folio-pending-balance');
    const checkoutAmountEl = document.getElementById('drawer-checkout-amount');

    const paidBox = document.getElementById('checkout-paid-box');
    const pendingBox = document.getElementById('checkout-pending-box');
    const paymentFields = document.getElementById('checkout-payment-fields');
    const btnSubmit = document.getElementById('btn-checkout-submit');

    tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Cargando desglose...</td></tr>';

    try {
        const res = await apiFetch(`/api/rooms`);
        if (res.data && res.data.data) {
            const room = res.data.data.find(r => r.id === roomId);
            if (room && room.current_stay) {
                const stay = room.current_stay;
                document.getElementById('drawer-folio-id').textContent = stay.id || '1';

                const totalCharges = parseFloat(stay.total_charges || 0);
                const totalPayments = parseFloat(stay.total_payments || 0);
                const pendingBalance = parseFloat(stay.pending_balance || 0);

                if (totalChargesEl) totalChargesEl.textContent = `S/ ${totalCharges.toFixed(2)}`;
                if (totalPaymentsEl) totalPaymentsEl.textContent = `S/ ${totalPayments.toFixed(2)}`;
                if (pendingBalanceEl) pendingBalanceEl.textContent = `S/ ${pendingBalance.toFixed(2)}`;
                if (checkoutAmountEl) checkoutAmountEl.textContent = `S/ ${pendingBalance.toFixed(2)}`;

                if (pendingBalance <= 0) {
                    if (paidBox) paidBox.style.display = 'block';
                    if (pendingBox) pendingBox.style.display = 'none';
                    if (paymentFields) paymentFields.style.display = 'none';
                    if (btnSubmit) {
                        btnSubmit.textContent = '⚡ Confirmar Checkout (Pagado S/ 0.00)';
                        btnSubmit.className = 'ns-drawer__btn ns-drawer__btn--success';
                        btnSubmit.style.marginTop = '1rem';
                    }
                } else {
                    if (paidBox) paidBox.style.display = 'none';
                    if (pendingBox) pendingBox.style.display = 'block';
                    if (paymentFields) paymentFields.style.display = 'block';
                    if (btnSubmit) {
                        btnSubmit.textContent = `💵 Cobrar S/ ${pendingBalance.toFixed(2)} y Finalizar Checkout`;
                        btnSubmit.className = 'ns-drawer__btn ns-drawer__btn--danger';
                        btnSubmit.style.marginTop = '1rem';
                    }
                }

                let rowsHtml = '';
                if (stay.charges && stay.charges.length > 0) {
                    stay.charges.forEach(c => {
                        rowsHtml += `
                            <tr>
                                <td>${c.description}</td>
                                <td>${c.quantity}</td>
                                <td>S/ ${parseFloat(c.total_amount).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                } else {
                    rowsHtml = `
                        <tr>
                            <td>Hospedaje (${room.category_name || 'Habitación'})</td>
                            <td>1</td>
                            <td>S/ ${parseFloat(stay.applied_price || 0).toFixed(2)}</td>
                        </tr>
                    `;
                }

                if (stay.payments && stay.payments.length > 0) {
                    stay.payments.forEach(p => {
                        rowsHtml += `
                            <tr class="text-success">
                                <td>💳 Abono (${p.payment_method.toUpperCase()})</td>
                                <td>1</td>
                                <td>- S/ ${parseFloat(p.amount).toFixed(2)}</td>
                            </tr>
                        `;
                    });
                }

                tbody.innerHTML = rowsHtml;
            }
        }
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-muted">Error al cargar balance.</td></tr>';
    }
}

function populateTransferTargetRooms(currentRoomId) {
    const select = document.getElementById('drawer-transfer-target-room');
    if (!select) return;

    select.innerHTML = '<option value="">Seleccione habitación libre...</option>';
    const freeCards = document.querySelectorAll('#rack-rooms-grid .room-card-free');

    freeCards.forEach(card => {
        const id = card.getAttribute('data-room-id');
        const num = card.getAttribute('data-room-number');
        if (id !== String(currentRoomId)) {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = `Habitación ${num} (Libre)`;
            select.appendChild(opt);
        }
    });
}

function updateDrawerTariffPrice() {
    const select = document.getElementById('drawer-checkin-tariff-id');
    if (!select || select.selectedIndex < 0) return;
    const selectedOption = select.options[select.selectedIndex];
    const price = selectedOption.getAttribute('data-price');
    if (price) {
        document.getElementById('drawer-checkin-applied-price').value = price;
        calculateAdvanceBalance();
        recalculateFutureReservationDates();
    }
}

async function lookupDrawerGuestDocument() {
    const docNumber = document.getElementById('drawer-checkin-doc-number').value.trim();
    if (!docNumber) {
        showToast('Ingrese número de DNI o RUC', 'warning');
        return;
    }

    try {
        const res = await apiFetch(`/api/guests/lookup?document_number=${docNumber}`);
        if (res.ok && res.data.data && res.data.data.guest) {
            const g = res.data.data.guest;
            const fullName = `${g.first_name || ''} ${g.last_name || ''}`.trim() || g.company_name || '';
            document.getElementById('drawer-checkin-guest-name').value = fullName;
            showToast(`Huésped validado: ${fullName}`);
        } else {
            showToast('Documento no registrado previamente. Ingrese el nombre para registrarlo.', 'warning');
        }
    } catch (e) {
        showToast('Error de búsqueda en padrón', 'warning');
    }
}

async function handleDrawerActionSubmit(e) {
    e.preventDefault();
    const roomId = parseInt(document.getElementById('drawer-checkin-room-id').value, 10);
    const tariffId = parseInt(document.getElementById('drawer-checkin-tariff-id').value, 10);
    const appliedPrice = parseFloat(document.getElementById('drawer-checkin-applied-price').value);
    const discountReasonEl = document.getElementById('drawer-checkin-discount-reason');
    let discountReason = discountReasonEl ? discountReasonEl.value.trim() : '';

    const advanceAmount = parseFloat(document.getElementById('drawer-checkin-advance-amount').value) || 0.0;
    const advanceMethod = document.getElementById('drawer-checkin-advance-method').value;

    const docNumber = document.getElementById('drawer-checkin-doc-number').value.trim();
    const guestName = document.getElementById('drawer-checkin-guest-name').value.trim();
    const docTypeMode = docNumber.length === 11 ? 'RUC' : 'DNI';

    const customInEl = document.getElementById('drawer-checkin-custom-in');
    const customOutEl = document.getElementById('drawer-checkin-custom-out');

    const customIn = customInEl && customInEl.value ? customInEl.value.replace('T', ' ') + ':00' : null;
    const customOut = customOutEl && customOutEl.value ? customOutEl.value.replace('T', ' ') + ':00' : null;

    if (!tariffId) {
        showToast('Seleccione una tarifa válida para esta habitación', 'warning');
        return;
    }

    if (!discountReason) {
        discountReason = 'Tarifa y precio asignado en recepción';
    }

    let guestId = null;
    if (docNumber && guestName) {
        try {
            const guestRes = await apiFetch('/api/guests', {
                method: 'POST',
                body: JSON.stringify({
                    document_type: docTypeMode,
                    document_number: docNumber,
                    first_name: guestName,
                    last_name: ''
                })
            });
            if (guestRes.ok && guestRes.data && guestRes.data.data) {
                guestId = guestRes.data.data.id || guestRes.data.data.guest_id || null;
            }
        } catch (e) {
            console.error('Error registrando huésped:', e);
        }
    }

    // Dual Flow: Immediate Check-in vs Future Reservation
    if (currentDrawerOperationMode === 'reservation') {
        const futureInput = document.getElementById('drawer-future-checkin-at');
        const futureCheckinVal = futureInput && futureInput.value ? futureInput.value.replace('T', ' ') + ':00' : '';

        if (!futureCheckinVal) {
            showToast('Seleccione la fecha y hora de llegada prevista para la reserva', 'warning');
            return;
        }

        try {
            const res = await apiFetch('/api/reservations', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'create',
                    room_id: roomId,
                    guest_id: guestId,
                    guest_name: guestName,
                    guest_doc: docNumber,
                    guest_doc_type: docTypeMode,
                    tariff_id: tariffId,
                    check_in_at: futureCheckinVal,
                    advance_payment: advanceAmount,
                    payment_parts: advanceAmount > 0 ? [{
                        amount: advanceAmount,
                        payment_method: advanceMethod,
                    }] : [],
                    payment_method: advanceMethod,
                    notes: `Reserva creada desde Mapa de Habitaciones. ${discountReason}`,
                })
            });

            if (res.ok) {
                showToast('✅ Reserva agendada exitosamente');
                closeRoomDrawer();
                setTimeout(() => location.reload(), 600);
            } else {
                showToast(res.data?.message || 'Error al agendar la reserva', 'error');
            }
        } catch (err) {
            showToast('Error inesperado al agendar la reserva', 'error');
        }
        return;
    }

    // Default Immediate Check-in
    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            headers: { 'Idempotency-Key': crypto.randomUUID() },
            body: JSON.stringify({
                action: 'occupy',
                room_id: roomId,
                tariff_id: tariffId,
                applied_price: appliedPrice,
                discount_reason: discountReason,
                guest_id: guestId,
                guest_name: guestName,
                guest_doc: docNumber,
                guest_doc_type: docTypeMode,
                custom_check_in_time: customIn,
                custom_check_out_time: customOut,
                initial_payment_amount: advanceAmount,
                payment_parts: advanceAmount > 0 ? [{
                    amount: advanceAmount,
                    payment_method: advanceMethod,
                }] : [],
                payment_method: advanceMethod,
            })
        });

        if (res.ok) {
            showToast('✅ Check-In registrado exitosamente');
            closeRoomDrawer();
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'Error al procesar Check-In', 'error');
        }
    } catch (err) {
        showToast('Error inesperado al procesar Check-In', 'error');
    }
}

async function handleDrawerCheckout(e) {
    e.preventDefault();
    const roomId = parseInt(document.getElementById('drawer-checkout-room-id').value, 10);
    const docType = document.getElementById('drawer-checkout-doc-type').value;
    const paymentMethod = document.getElementById('drawer-checkout-payment-method').value;
    const reference = document.getElementById('drawer-checkout-reference') ? document.getElementById('drawer-checkout-reference').value.trim() : null;
    const pendingAmountText = document.getElementById('drawer-checkout-amount')?.textContent || '';
    const pendingAmount = parseFloat(pendingAmountText.replace(/[^0-9.-]/g, '')) || 0;
    const ruc = document.getElementById('drawer-checkout-ruc') ? document.getElementById('drawer-checkout-ruc').value.trim() : '';
    const company = document.getElementById('drawer-checkout-company') ? document.getElementById('drawer-checkout-company').value.trim() : '';

    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: 'checkout',
                room_id: roomId,
                document_type: docType,
                payment_method: paymentMethod,
                payment_reference: reference,
                payment_parts: pendingAmount > 0 ? [{
                    amount: pendingAmount,
                    payment_method: paymentMethod,
                    reference_number: reference,
                }] : [],
                customer_ruc: ruc,
                customer_company: company,
            })
        });

        if (res.ok) {
            showToast('✅ Checkout completado, saldo liquidado y habitación enviada a limpieza');
            closeRoomDrawer();

            const invoiceData = res.data.data?.invoice;
            const voucherUrl = res.data.data?.voucher_url || res.data.data?.pdf_url;

            if (voucherUrl) {
                const frame = document.getElementById('checkout-receipt-pdf-frame');
                if (frame) frame.src = voucherUrl;

                const actionsEl = document.getElementById('checkout-receipt-actions');
                if (actionsEl) {
                    let actHtml = `<a href="${escapeHtml(voucherUrl)}" target="_blank" class="btn btn-outline-success btn-sm">🖨️ Abrir Voucher</a> `;
                    if (invoiceData?.id) {
                        actHtml += `<button type="button" class="btn btn-warning btn-sm" onclick="triggerEscPosPrint(${invoiceData.id})">⚡ Imprimir ESC/POS</button>`;
                    }
                    actionsEl.innerHTML = actHtml;
                }

                openModal('modal-checkout-receipt');
            } else {
                setTimeout(() => location.reload(), 800);
            }
        } else {
            showToast(res.data.message || 'Error en Checkout', 'error');
        }
    } catch (err) {
        showToast('Error al procesar Checkout', 'error');
    }
}

function closeCheckoutReceiptModal() {
    closeModal('modal-checkout-receipt');
    location.reload();
}

function triggerEscPosPrint(invoiceId) {
    const printUrl = `/api/invoices/${invoiceId}/escpos?open_drawer=1`;
    const w = window.open(printUrl, '_blank');
    if (w) {
        setTimeout(() => w.close(), 1000);
    }
    if (typeof showToast === 'function') {
        showToast('Enviando comando de impresión a impresora térmica y apertura de gaveta...', 'info');
    }
}

async function handleDrawerTransfer() {
    const targetRoomId = document.getElementById('drawer-transfer-target-room').value;
    if (!targetRoomId || !currentDrawerRoomId) {
        showToast('Seleccione habitación destino libre', 'warning');
        return;
    }

    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: 'transfer',
                from_room_id: currentDrawerRoomId,
                to_room_id: parseInt(targetRoomId, 10),
                reason: 'Transferencia solicitada en recepción',
            })
        });

        if (res.ok) {
            showToast('✅ Habitación transferida exitosamente');
            closeRoomDrawer();
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'Error al transferir', 'error');
        }
    } catch (e) {
        showToast('Error al transferir habitación', 'error');
    }
}

async function handleDrawerExtension() {
    const hours = parseInt(document.getElementById('drawer-extension-hours').value, 10);
    const price = parseFloat(document.getElementById('drawer-extension-price').value);

    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: 'extend',
                room_id: currentDrawerRoomId,
                added_hours: hours,
                added_price: price,
            })
        });

        if (res.ok) {
            showToast(`✅ Adicionadas ${hours} horas a la estancia`);
            closeRoomDrawer();
            setTimeout(() => location.reload(), 600);
        } else {
            showToast(res.data.message || 'Error al extender estancia', 'error');
        }
    } catch (e) {
        showToast('Error al extender estancia', 'error');
    }
}

async function handleDrawerSetCleaning() {
    if (!currentDrawerRoomId) return;

    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: 'update_status',
                room_id: currentDrawerRoomId,
                status: 'cleaning',
            })
        });

        if (res.ok) {
            showToast('Habitación marcada para limpieza');
            closeRoomDrawer();
            setTimeout(() => location.reload(), 600);
        }
    } catch (e) {
        showToast('Error al actualizar estado', 'error');
    }
}

async function handleDrawerReleaseCleaning() {
    if (!currentDrawerRoomId) return;

    try {
        const res = await apiFetch('/api/rooms', {
            method: 'POST',
            body: JSON.stringify({
                action: 'update_status',
                room_id: currentDrawerRoomId,
                status: 'free',
            })
        });

        if (res.ok) {
            showToast('✅ Habitación liberada y lista para recibir clientes');
            closeRoomDrawer();
            setTimeout(() => location.reload(), 600);
        }
    } catch (e) {
        showToast('Habitación liberada');
        closeRoomDrawer();
        setTimeout(() => location.reload(), 600);
    }
}

async function executeDrawerReservationCheckin() {
    if (!currentDrawerReservation) return;

    if (confirm(`🏨 ¿Deseas ejecutar el Check-In inmediato de la reserva #${currentDrawerReservation.id} (${currentDrawerReservation.guest_name}) para esta habitación?`)) {
        try {
            const result = await apiFetch('/api/reservations', {
                method: 'POST',
                body: JSON.stringify({
                    action: 'checkin',
                    reservation_id: currentDrawerReservation.id
                })
            });
            if (result.ok && result.data.status === 'success') {
                if (typeof showToast === 'function') {
                    showToast(result.data.message || 'Check-in de reserva completado con éxito.', 'success');
                }
                closeRoomDrawer();
                setTimeout(() => window.location.reload(), 600);
            } else {
                alert('Error al realizar Check-in: ' + (result.data.message || 'Error desconocido'));
            }
        } catch (e) {
            alert('Error de comunicación con el servidor.');
        }
    }
}
