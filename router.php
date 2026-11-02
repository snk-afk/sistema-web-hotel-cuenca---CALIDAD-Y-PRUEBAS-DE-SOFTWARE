<?php

declare(strict_types=1);

$raw_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($raw_path) && $raw_path !== '' ? rawurldecode($raw_path) : '/';
$public_root = realpath(__DIR__ . '/public');
$file = $public_root !== false
    ? realpath($public_root . DIRECTORY_SEPARATOR . ltrim($uri, '/'))
    : false;
$is_public_file = is_string($file)
    && ($file === $public_root || str_starts_with($file, $public_root . DIRECTORY_SEPARATOR));

if ($uri !== '/' && $is_public_file && is_file($file)) {
    $mime_types = [
        'css' => 'text/css; charset=UTF-8',
        'js' => 'application/javascript; charset=UTF-8',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff2' => 'font/woff2',
    ];

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime = $mime_types[$ext] ?? (mime_content_type($file) ?: 'application/octet-stream');

    header('Content-Type: ' . $mime);
    readfile($file);
    exit;
}

require_once __DIR__ . '/public/index.php';
