(function () {
    'use strict';

    const STORAGE_KEY = 'cs-hotel-accessibility';
    const DEFAULTS = Object.freeze({
        theme: 'dark',
        fontScale: 100,
        highContrast: false,
        reducedMotion: false,
        highlightLinks: false,
        textSpacing: false,
        colorVision: 'original'
    });
    const COLOR_VISION_MODES = Object.freeze(['original', 'protanopia', 'deuteranopia', 'tritanopia', 'accessible']);
    const FONT_STEP = 10;
    const MIN_FONT_SCALE = 80;
    const MAX_FONT_SCALE = 150;

    let preferences = loadPreferences();
    let panel;
    let trigger;
    let lastFocusedElement;

    function loadPreferences() {
        try {
            const saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '{}');
            const colorVision = COLOR_VISION_MODES.includes(saved.colorVision) ? saved.colorVision : 'original';
            return {
                ...DEFAULTS,
                ...saved,
                theme: saved.theme === 'light' ? 'light' : 'dark',
                fontScale: Math.min(MAX_FONT_SCALE, Math.max(MIN_FONT_SCALE, Number(saved.fontScale) || 100)),
                colorVision
            };
        } catch (error) {
            return { ...DEFAULTS };
        }
    }

    function savePreferences() {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(preferences));
        } catch (error) {
            // La accesibilidad sigue funcionando aunque el almacenamiento esté bloqueado.
        }
    }

    function applyPreferences() {
        const root = document.documentElement;
        root.dataset.a11yTheme = preferences.theme;
        root.style.setProperty('--a11y-font-scale', `${preferences.fontScale / 100}`);
        root.classList.toggle('a11y-high-contrast', preferences.highContrast);
        root.classList.toggle('a11y-reduced-motion', preferences.reducedMotion);
        root.classList.toggle('a11y-highlight-links', preferences.highlightLinks);
        root.classList.toggle('a11y-text-spacing', preferences.textSpacing);
        root.dataset.a11yColorVision = preferences.colorVision;
        root.style.colorScheme = preferences.theme;
        decorateRoomStatuses();
        updateControls();
    }

    function decorateRoomStatuses(scope = document) {
        const selectors = '.badge-status, .ns-drawer__badge, .ns-room-modal__badge, .room-row-item .badge';
        scope.querySelectorAll(selectors).forEach(function (badge) {
            const label = badge.textContent.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toUpperCase();
            let status = '';
            if (label.includes('TIEMPO CUMPLIDO') || label.includes('VENCID')) status = 'overdue';
            else if (label.includes('AVERIA') || label.includes('MANTENIMIENTO')) status = 'maintenance';
            else if (label.includes('LIMPIEZA')) status = 'cleaning';
            else if (label.includes('OCUPAD')) status = 'occupied';
            else if (label.includes('RESERVAD')) status = 'reserved';
            else if (label.includes('LIBRE')) status = 'free';
            if (status) badge.dataset.a11yRoomStatus = status;
        });
    }

    function createToggle(id, label, description) {
        return `
            <button type="button" class="a11y-option" id="${id}" data-a11y-toggle aria-pressed="false">
                <span class="a11y-option__text">
                    <span class="a11y-option__label">${label}</span>
                    <span class="a11y-option__description">${description}</span>
                </span>
                <span class="a11y-switch" aria-hidden="true"><span></span></span>
            </button>`;
    }

    function buildComponent() {
        const container = document.createElement('div');
        container.className = 'a11y-widget';
        container.innerHTML = `
            <button type="button" class="a11y-trigger" aria-label="Abrir menú de accesibilidad" aria-expanded="false" aria-controls="a11y-panel">
                <span aria-hidden="true">♿</span>
            </button>
            <section class="a11y-panel" id="a11y-panel" role="dialog" aria-modal="false" aria-labelledby="a11y-title" hidden>
                <div class="a11y-panel__header">
                    <div>
                        <h2 id="a11y-title">Accesibilidad</h2>
                        <p>Personaliza la visualización del sistema.</p>
                    </div>
                    <button type="button" class="a11y-close" aria-label="Cerrar menú de accesibilidad">×</button>
                </div>
                <div class="a11y-panel__content">
                    ${createToggle('a11y-theme', 'Tema claro', 'Alternar entre modo oscuro y claro')}
                    <div class="a11y-font-control" role="group" aria-labelledby="a11y-font-label">
                        <span id="a11y-font-label" class="a11y-option__text">
                            <span class="a11y-option__label">Tamaño del texto</span>
                            <span class="a11y-option__description">Entre 80% y 150%</span>
                        </span>
                        <span class="a11y-font-control__actions">
                            <button type="button" id="a11y-font-decrease" aria-label="Disminuir tamaño del texto">A−</button>
                            <output id="a11y-font-value" aria-live="polite">100%</output>
                            <button type="button" id="a11y-font-increase" aria-label="Aumentar tamaño del texto">A+</button>
                        </span>
                    </div>
                    ${createToggle('a11y-contrast', 'Alto contraste', 'Aumentar la diferencia entre texto y fondo')}
                    ${createToggle('a11y-motion', 'Reducir animaciones', 'Desactivar transiciones y movimientos')}
                    ${createToggle('a11y-links', 'Resaltar enlaces', 'Subrayar y distinguir los enlaces')}
                    ${createToggle('a11y-spacing', 'Espaciado de texto', 'Ampliar letras, palabras y líneas')}
                    <fieldset class="a11y-color-vision" id="a11y-color-vision">
                        <legend>Visión del color</legend>
                        <p>Paletas de estado con símbolos y texto redundantes.</p>
                        <label><input type="radio" name="a11y-color-vision" value="original"> <span>Colores originales</span></label>
                        <label><input type="radio" name="a11y-color-vision" value="protanopia"> <span>Paleta para protanopia</span></label>
                        <label><input type="radio" name="a11y-color-vision" value="deuteranopia"> <span>Paleta para deuteranopia</span></label>
                        <label><input type="radio" name="a11y-color-vision" value="tritanopia"> <span>Paleta para tritanopia</span></label>
                        <label><input type="radio" name="a11y-color-vision" value="accessible"> <span>Accesible general</span></label>
                    </fieldset>
                    <button type="button" class="a11y-reset" id="a11y-reset">Restablecer configuración</button>
                    <p class="a11y-status" id="a11y-status" role="status" aria-live="polite"></p>
                </div>
            </section>`;
        document.body.appendChild(container);

        panel = container.querySelector('.a11y-panel');
        trigger = container.querySelector('.a11y-trigger');
        bindEvents(container);
        decorateRoomStatuses();
        const statusObserver = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                const target = mutation.target instanceof Element ? mutation.target : mutation.target.parentElement;
                if (target) decorateRoomStatuses(target.closest('.ns-room-modal, .ns-drawer, .room-card, .room-row-item') || target);
            });
        });
        statusObserver.observe(document.body, { childList: true, subtree: true, characterData: true });
    }

    function bindEvents(container) {
        trigger.addEventListener('click', openPanel);
        container.querySelector('.a11y-close').addEventListener('click', closePanel);
        container.querySelector('#a11y-theme').addEventListener('click', function () {
            preferences.theme = preferences.theme === 'dark' ? 'light' : 'dark';
            commit('Tema actualizado.');
        });
        bindBooleanToggle(container, '#a11y-contrast', 'highContrast');
        bindBooleanToggle(container, '#a11y-motion', 'reducedMotion');
        bindBooleanToggle(container, '#a11y-links', 'highlightLinks');
        bindBooleanToggle(container, '#a11y-spacing', 'textSpacing');
        container.querySelectorAll('input[name="a11y-color-vision"]').forEach(function (control) {
            control.addEventListener('change', function () {
                if (!COLOR_VISION_MODES.includes(control.value)) return;
                preferences.colorVision = control.value;
                commit(`Visión del color: ${control.parentElement.textContent.trim()}.`);
            });
        });

        container.querySelector('#a11y-font-decrease').addEventListener('click', function () {
            preferences.fontScale = Math.max(MIN_FONT_SCALE, preferences.fontScale - FONT_STEP);
            commit(`Tamaño del texto: ${preferences.fontScale}%.`);
        });
        container.querySelector('#a11y-font-increase').addEventListener('click', function () {
            preferences.fontScale = Math.min(MAX_FONT_SCALE, preferences.fontScale + FONT_STEP);
            commit(`Tamaño del texto: ${preferences.fontScale}%.`);
        });
        container.querySelector('#a11y-reset').addEventListener('click', function () {
            preferences = { ...DEFAULTS };
            commit('Configuración de accesibilidad restablecida.');
        });

        panel.addEventListener('keydown', handlePanelKeydown);
        document.addEventListener('pointerdown', function (event) {
            if (!panel.hidden && !container.contains(event.target)) closePanel(false);
        });
    }

    function bindBooleanToggle(container, selector, property) {
        container.querySelector(selector).addEventListener('click', function () {
            preferences[property] = !preferences[property];
            commit('Preferencia actualizada.');
        });
    }

    function commit(message) {
        applyPreferences();
        savePreferences();
        const status = document.getElementById('a11y-status');
        if (status) status.textContent = message;
    }

    function updateControls() {
        if (!panel) return;
        setPressed('a11y-theme', preferences.theme === 'light');
        setPressed('a11y-contrast', preferences.highContrast);
        setPressed('a11y-motion', preferences.reducedMotion);
        setPressed('a11y-links', preferences.highlightLinks);
        setPressed('a11y-spacing', preferences.textSpacing);
        const colorVisionControl = panel.querySelector(`input[name="a11y-color-vision"][value="${preferences.colorVision}"]`);
        if (colorVisionControl) colorVisionControl.checked = true;
        document.getElementById('a11y-font-value').textContent = `${preferences.fontScale}%`;
        document.getElementById('a11y-font-decrease').disabled = preferences.fontScale <= MIN_FONT_SCALE;
        document.getElementById('a11y-font-increase').disabled = preferences.fontScale >= MAX_FONT_SCALE;
    }

    function setPressed(id, active) {
        const control = document.getElementById(id);
        if (control) control.setAttribute('aria-pressed', String(active));
    }

    function openPanel() {
        lastFocusedElement = document.activeElement;
        panel.hidden = false;
        trigger.setAttribute('aria-expanded', 'true');
        trigger.setAttribute('aria-label', 'Cerrar menú de accesibilidad');
        panel.querySelector('.a11y-close').focus();
    }

    function closePanel(restoreFocus = true) {
        if (panel.hidden) return;
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-label', 'Abrir menú de accesibilidad');
        if (restoreFocus && lastFocusedElement instanceof HTMLElement) lastFocusedElement.focus();
    }

    function handlePanelKeydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            closePanel();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = Array.from(panel.querySelectorAll('button:not([disabled]), [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'));
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function init() {
        applyPreferences();
        buildComponent();
        updateControls();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init, { once: true });
    } else {
        init();
    }
}());
