<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * Excepción lanzada por AuthMiddleware cuando la autenticación,
 * autorización o verificación CSRF fallan.
 */
final class AuthException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $http_status = 401,
        public readonly ?string $redirect_location = null,
    ) {
        parent::__construct($message);
    }
}
