<?php
declare(strict_types=1);

use App\Support\Session;

// Auth guard para vistas: si no hay sesión, redirigir a /login
Session::start();
$session_user_id = Session::userId();
if ($session_user_id === null) {
    header('Location: /login');
    exit;
}

// Token CSRF para inyectar en el meta tag
$csrf_token = Session::csrfToken();

// Estado de caja: consultar en cada página para consistencia global
if (!isset($shift)) {
    $shift = null;
    try {
        $cashbox_service = new \App\Services\CashboxService();
        $shift = $cashbox_service->getCurrentShift($session_user_id);
    } catch (\Throwable) {
        // Si la BD no está disponible, no romper el render
    }
}

$session_username = $_SESSION['username'] ?? 'Usuario';
$session_role = $_SESSION['role'] ?? 'user';

// CSS de páginas declarado dinámicamente por cada vista (SPEC-0041 §6 Criterio 7)
if (!isset($page_styles) || !is_array($page_styles)) {
    $page_styles = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
    <title><?= htmlspecialchars($page_title ?? 'CS-Hotel', ENT_QUOTES | ENT_HTML5, 'UTF-8') ?> - CS-Hotel</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/layout.css">
    <link rel="stylesheet" href="/assets/css/layout/header.css">
    <link rel="stylesheet" href="/assets/css/layout/nav.css">
    <link rel="stylesheet" href="/assets/css/layout/footer.css">
    <link rel="stylesheet" href="/assets/css/components/modal.css">
    <link rel="stylesheet" href="/assets/css/components/drawer.css">
    <link rel="stylesheet" href="/assets/css/components/room-modal.css">
    <link rel="stylesheet" href="/assets/css/components/pos.css">
    <link rel="stylesheet" href="/assets/css/components/metric-card.css">
    <link rel="stylesheet" href="/assets/css/components/tab-bar.css">
    <?php foreach ($page_styles as $style_href): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($style_href, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>">
    <?php endforeach; ?>
</head>
<body>
<div class="app-container">
    <header class="ns-header">
        <div class="ns-header__brand">
            <button type="button" class="ns-nav__toggle" aria-label="Abrir menú" aria-expanded="false" aria-controls="main-nav">
                <span class="ns-nav__toggle-bar"></span>
            </button>
            <h2>🏨 CS-Hotel System</h2>
        </div>
        <div class="ns-header__status">
            <span class="ns-header__cashbox-badge <?= $shift !== null ? 'is-open' : '' ?>">
                💳 Caja: <?= $shift !== null ? 'ABIERTA (S/ ' . number_format((float) $shift->expected_cash, 2) . ')' : 'CERRADA' ?>
            </span>
            <span class="ns-header__user-profile">
                👤 <?= htmlspecialchars($session_username, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?>
                <span class="ns-header__user-role"><?= htmlspecialchars($session_role, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></span>
            </span>
            <a href="#" class="ns-header__logout" onclick="doLogout(); return false;">Cerrar sesión</a>
        </div>
    </header>
    <div class="app-body">
