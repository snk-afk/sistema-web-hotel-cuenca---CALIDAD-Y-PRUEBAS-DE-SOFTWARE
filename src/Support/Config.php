<?php

declare(strict_types=1);

namespace App\Support;

use Dotenv\Dotenv;

final class Config
{
    private static bool $loaded = false;

    /**
     * @var array<string, mixed>
     */
    private static array $repository = [];

    public static function load(string $base_path): void
    {
        if (self::$loaded) {
            return;
        }

        if (file_exists($base_path . '/.env')) {
            $dotenv = Dotenv::createImmutable($base_path);
            $dotenv->safeLoad();
        }

        self::loadConfigFiles($base_path . '/config');
        self::$loaded = true;
    }

    private static function loadConfigFiles(string $config_path): void
    {
        if (!is_dir($config_path)) {
            return;
        }

        /** @var array<int, string>|false $files */
        $files = glob($config_path . '/*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            /** @var mixed $content */
            $content = require $file;
            if (is_array($content)) {
                self::$repository[$key] = $content;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $current = self::$repository;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return self::env($key, $default);
            }
            $current = $current[$part];
        }

        return $current;
    }

    public static function string(string $key, string $default = ''): string
    {
        $val = self::get($key, $default);
        if (is_string($val) || is_numeric($val)) {
            return (string) $val;
        }
        return $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $val = self::get($key, $default);
        if (is_numeric($val)) {
            return (int) $val;
        }
        return $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $val = self::get($key, $default);
        if (is_bool($val)) {
            return $val;
        }
        if (is_string($val)) {
            return in_array(strtolower($val), ['true', '1', 'yes', 'on'], true);
        }
        return (bool) $val;
    }

    public static function env(string $key, mixed $default = null): mixed
    {
        /** @var mixed $value */
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default;
        }

        if (!is_string($value) && !is_numeric($value) && !is_bool($value)) {
            return $default;
        }

        $str_val = (string) $value;

        return match (strtolower($str_val)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'empty', '(empty)' => '',
            'null', '(null)' => null,
            default => $value,
        };
    }

    public static function clear(): void
    {
        self::$loaded = false;
        self::$repository = [];
    }
}
