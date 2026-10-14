<?php
declare(strict_types=1);

$active = $active_menu ?? '';
?>
<div class="ns-nav__backdrop"></div>
<nav class="ns-nav" id="main-nav">
    <ul class="ns-nav__list">
        <li class="ns-nav__item <?= $active === 'dashboard' ? 'ns-nav__item--active' : '' ?>">
            <a href="/" class="ns-nav__link <?= $active === 'dashboard' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">📊</span> Mapa de Habitaciones
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'rooms' ? 'ns-nav__item--active' : '' ?>">
            <a href="/habitaciones" class="ns-nav__link <?= $active === 'rooms' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">🚪</span> Habitaciones y Tarifas
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'guests' ? 'ns-nav__item--active' : '' ?>">
            <a href="/huespedes" class="ns-nav__link <?= $active === 'guests' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">🪪</span> Maestro de Huéspedes
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'reservations' ? 'ns-nav__item--active' : '' ?>">
            <a href="/reservas" class="ns-nav__link <?= $active === 'reservations' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">📅</span> Reservas
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'folios' ? 'ns-nav__item--active' : '' ?>">
            <a href="/folios" class="ns-nav__link <?= $active === 'folios' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">📋</span> Cuentas / Folios
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'store' ? 'ns-nav__item--active' : '' ?>">
            <a href="/tienda" class="ns-nav__link <?= $active === 'store' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">🛒</span> POS Tienda
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'cashbox' ? 'ns-nav__item--active' : '' ?>">
            <a href="/caja" class="ns-nav__link <?= $active === 'cashbox' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">💵</span> Caja Chica y Turnos
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'expenses' ? 'ns-nav__item--active' : '' ?>">
            <a href="/egresos" class="ns-nav__link <?= $active === 'expenses' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">💸</span> Egresos e Ingresos
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'housekeeping' ? 'ns-nav__item--active' : '' ?>">
            <a href="/limpieza" class="ns-nav__link <?= $active === 'housekeeping' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">🧹</span> Limpieza y Averías
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'users' ? 'ns-nav__item--active' : '' ?>">
            <a href="/usuarios" class="ns-nav__link <?= $active === 'users' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">👤</span> Usuarios & Auditoría
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'invoices' ? 'ns-nav__item--active' : '' ?>">
            <a href="/comprobantes" class="ns-nav__link <?= $active === 'invoices' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">🧾</span> Facturación SUNAT
            </a>
        </li>
        <li class="ns-nav__item <?= $active === 'reports' ? 'ns-nav__item--active' : '' ?>">
            <a href="/reportes" class="ns-nav__link <?= $active === 'reports' ? 'active' : '' ?>">
                <span class="ns-nav__link-icon">📈</span> Reportes Ejecutivos
            </a>
        </li>
    </ul>
</nav>
<main class="app-content">
