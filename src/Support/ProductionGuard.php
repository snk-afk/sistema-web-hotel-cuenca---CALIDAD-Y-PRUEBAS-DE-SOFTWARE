<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class ProductionGuard
{
    public static function validateIfProduction(): void
    {
        $app_env = Config::env('APP_ENV');
        if (is_string($app_env) && strtolower($app_env) === 'production') {
            self::validate();
        }
    }

    public static function validate(): void
    {
        $app_env = Config::env('APP_ENV');
        if (!is_string($app_env) || strtolower($app_env) !== 'production') {
            throw new RuntimeException('APP_ENV debe ser production.');
        }

        if (Config::env('APP_DEBUG') !== false) {
            throw new RuntimeException('APP_DEBUG debe estar deshabilitado en producción.');
        }

        $app_url = Config::env('APP_URL');
        if (!is_string($app_url) || strtolower((string) parse_url($app_url, PHP_URL_SCHEME)) !== 'https') {
            throw new RuntimeException('APP_URL debe usar HTTPS en producción.');
        }

        $app_key = Config::env('APP_KEY');
        if (!is_string($app_key) || strlen($app_key) < 32 || self::isExampleValue($app_key)) {
            throw new RuntimeException('APP_KEY no tiene una configuración segura.');
        }

        self::validateDatabase();
        self::validateExternalServices();
    }

    private static function validateDatabase(): void
    {
        $driver = Config::env('DB_DRIVER');
        if (!is_string($driver) || strtolower($driver) !== 'mysql') {
            throw new RuntimeException('DB_DRIVER debe ser mysql en producción.');
        }

        foreach (['DB_HOST', 'DB_USERNAME', 'DB_PASSWORD', 'DB_CHARSET'] as $key) {
            $value = Config::env($key);
            if (!is_string($value) || trim($value) === '') {
                throw new RuntimeException("{$key} es obligatorio en producción.");
            }
        }

        $charset = Config::env('DB_CHARSET');
        if (!is_string($charset) || strtolower($charset) !== 'utf8mb4') {
            throw new RuntimeException('DB_CHARSET debe ser utf8mb4 en producción.');
        }
    }

    private static function validateExternalServices(): void
    {
        $sunat_mode = Config::env('SUNAT_MODE');
        if (!is_string($sunat_mode) || strtolower($sunat_mode) !== 'real') {
            throw new RuntimeException('SUNAT_MODE debe ser real en producción.');
        }

        $sunat_endpoint = Config::env('SUNAT_ENDPOINT');
        if (!is_string($sunat_endpoint) || strtolower((string) parse_url($sunat_endpoint, PHP_URL_SCHEME)) !== 'https') {
            throw new RuntimeException('SUNAT_ENDPOINT debe usar HTTPS en producción.');
        }

        foreach (['SUNAT_RUC', 'SUNAT_SOL_USER', 'SUNAT_SOL_PASS'] as $key) {
            $value = Config::env($key);
            if (!is_string($value) || trim($value) === '') {
                throw new RuntimeException("{$key} es obligatorio en producción.");
            }
        }

        $health_token = Config::env('HEALTH_TOKEN');
        if (!is_string($health_token) || strlen($health_token) < 32) {
            throw new RuntimeException('HEALTH_TOKEN debe tener al menos 32 caracteres en producción.');
        }

        $storage_provider = Config::env('STORAGE_DRIVER', Config::env('STORAGE_PROVIDER'));
        if (!is_string($storage_provider) || !in_array(strtolower($storage_provider), ['local', 'r2'], true)) {
            throw new RuntimeException('STORAGE_PROVIDER no es válido en producción.');
        }

        if (strtolower($storage_provider) !== 'r2') {
            return;
        }

        foreach (['R2_ACCOUNT_ID', 'R2_ACCESS_KEY_ID', 'R2_SECRET_ACCESS_KEY', 'R2_BUCKET_NAME'] as $key) {
            $value = Config::env($key);
            if (!is_string($value) || trim($value) === '') {
                throw new RuntimeException("{$key} es obligatorio cuando R2 está activo.");
            }
        }
    }

    private static function isExampleValue(string $value): bool
    {
        return in_array(
            trim($value),
            [
                'secret_app_key_32_characters_long_str',
                'secret_app_key_32_characters_long',
                'change-me',
                'change_me',
            ],
            true
        );
    }
}
