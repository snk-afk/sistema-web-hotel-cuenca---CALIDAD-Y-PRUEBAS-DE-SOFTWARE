<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - CS-Hotel System</title>
    <link rel="stylesheet" href="/assets/css/base.css">
    <link rel="stylesheet" href="/assets/css/components/modal.css">
    <link rel="stylesheet" href="/assets/css/pages/login.css">
</head>
<body class="login-body">
<div class="login-container">
    <div class="login-card">
        <div class="login-header">
            <h2>🏨 CS-Hotel System</h2>
            <p>Acceso al Sistema de Gestión Hotelera</p>
        </div>

        <?php if (isset($error_message) && $error_message !== ''): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error_message, ENT_QUOTES | ENT_HTML5, 'UTF-8') ?></div>
        <?php endif; ?>

        <div id="login-alert" class="alert alert-danger" style="display: none;"></div>

        <form id="form-login" action="/login" method="POST" onsubmit="handleLoginSubmit(event)">
            <div class="form-group">
                <label for="username">Usuario:</label>
                <input type="text" id="username" name="username" class="form-control" placeholder="Usuario" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Contraseña:</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block">🔓 Iniciar Sesión</button>
        </form>
    </div>
</div>

<script src="/assets/js/core/api.js"></script>
<script src="/assets/js/pages/login.js"></script>
</body>
</html>
