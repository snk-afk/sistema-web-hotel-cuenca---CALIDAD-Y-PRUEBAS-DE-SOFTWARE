/**
 * CS-Hotel: Core API Client
 * Centraliza fetch con CSRF automático, redirect 401 y toasts.
 */

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : (type === 'warning' ? 'warning' : 'success')}`;
    toast.style.cssText = 'min-width: 280px; max-width: 420px; margin: 0; box-shadow: 0 10px 25px rgba(0,0,0,0.5); animation: fadeIn 0.3s; font-size: 0.9rem;';
    toast.textContent = message;

    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

/**
 * Obtiene el token CSRF del meta tag inyectado por header.php
 */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * Wrapper de fetch que inyecta CSRF en mutations y redirige a /login en 401.
 */
async function apiFetch(url, options = {}) {
    const defaultHeaders = { 'Content-Type': 'application/json' };
    options.headers = { ...defaultHeaders, ...(options.headers || {}) };

    // Inyectar CSRF en POST/PUT/PATCH/DELETE
    const method = (options.method || 'GET').toUpperCase();
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
        const token = getCsrfToken();
        if (token) {
            options.headers['X-CSRF-Token'] = token;
        }
    }

    try {
        const response = await fetch(url, options);

        // 401 en el endpoint de login: dejar que el caller (login.js) maneje el error.
        // 401 en cualquier otra ruta: sesión expirada → redirect a login.
        if (response.status === 401 && !url.startsWith('/api/auth/login')) {
            window.location.href = '/login';
            return { ok: false, status: 401, data: { status: 'error', message: 'Sesión expirada' } };
        }

        // 419: CSRF inválido → recargar para obtener token nuevo
        if (response.status === 419) {
            showToast('Sesión expirada. Recargando...', 'warning');
            setTimeout(() => window.location.href = '/login', 1500);
            return { ok: false, status: 419, data: { status: 'error', message: 'Token CSRF inválido' } };
        }

        let data = {};
        try {
            data = await response.json();
        } catch (jsonErr) {
            data = { status: 'error', message: `Respuesta del servidor (${response.status} ${response.statusText})` };
        }
        return { ok: response.ok, status: response.status, data };
    } catch (networkError) {
        const errorMsg = networkError instanceof Error ? networkError.message : 'Error de red';
        return { ok: false, status: 0, data: { status: 'error', message: `Error de conexión: ${errorMsg}` } };
    }
}

/**
 * Cierra la sesión del usuario y redirige a /login.
 */
async function doLogout() {
    try {
        await apiFetch('/api/auth/logout', { method: 'POST' });
    } catch (e) {
        // Incluso si falla el logout en el servidor, redirigir al login
    }
    window.location.href = '/login';
}
