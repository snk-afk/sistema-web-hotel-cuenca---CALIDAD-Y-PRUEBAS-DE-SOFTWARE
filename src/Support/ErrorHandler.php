<?php

declare(strict_types=1);

namespace App\Support;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    public static function register(): void
    {
        set_error_handler(
            static function (int $severity, string $message, string $file, int $line): bool {
                if ((error_reporting() & $severity) === 0) {
                    return false;
                }

                self::render(new ErrorException($message, 0, $severity, $file, $line));
                return true;
            }
        );

        set_exception_handler(static function (Throwable $exception): void {
            self::render($exception);
        });
    }

    /**
     * @return array{status: string, message: string, request_id: string}
     */
    public static function payload(Throwable $exception, string $request_id, bool $is_debug): array
    {
        return [
            'status' => 'error',
            'message' => 'Ocurrió un error interno.',
            'request_id' => $request_id,
        ];
    }

    /**
     * @return array{request_id: string, exception: string, message: string, file: string, line: int}
     */
    public static function logContext(Throwable $exception, string $request_id): array
    {
        return [
            'request_id' => $request_id,
            'exception' => $exception::class,
            'message' => self::redact($exception->getMessage()),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
        ];
    }

    public static function render(Throwable $exception, ?string $request_id = null): void
    {
        $request_id ??= bin2hex(random_bytes(16));
        self::writeLog(self::logContext($exception, $request_id));

        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(
            self::payload($exception, $request_id, Config::bool('app.debug', false)),
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param array{request_id: string, exception: string, message: string, file: string, line: int} $context
     */
    private static function writeLog(array $context): void
    {
        $log_directory = Config::string('LOG_PATH', dirname(__DIR__, 2) . '/storage/logs');
        if (!is_dir($log_directory)) {
            @mkdir($log_directory, 0750, true);
        }

        $line = json_encode($context, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . PHP_EOL;
        @file_put_contents($log_directory . '/application.log', $line, FILE_APPEND | LOCK_EX);
    }

    private static function redact(string $message): string
    {
        $redacted = preg_replace(
            '/\b(password|token|secret|authorization|api[_-]?key)\s*[:=]\s*[^\s,;]+/i',
            '$1=[REDACTED]',
            $message
        );

        return is_string($redacted) ? $redacted : '[REDACTED]';
    }
}
