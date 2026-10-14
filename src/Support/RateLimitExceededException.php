<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class RateLimitExceededException extends RuntimeException
{
    public function __construct(public readonly int $retry_after_seconds)
    {
        parent::__construct('Demasiados intentos. Intente nuevamente más tarde.');
    }
}
