<?php

declare(strict_types=1);

namespace App\Support;

use App\Database\Database;
use App\Repositories\LoginAttemptRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

final class RateLimiter
{
    private PDO $db;
    private LoginAttemptRepository $attempt_repository;
    private int $max_attempts;
    private int $window_seconds;
    private DateTimeImmutable $clock;

    public function __construct(
        ?PDO $db = null,
        ?LoginAttemptRepository $attempt_repository = null,
        int $max_attempts = 5,
        int $window_seconds = 900,
        ?DateTimeImmutable $clock = null
    ) {
        if ($max_attempts < 1 || $window_seconds < 1) {
            throw new RuntimeException('La configuración del límite de intentos no es válida.');
        }

        $this->db = $db ?? Database::connection();
        $this->attempt_repository = $attempt_repository ?? new LoginAttemptRepository($this->db);
        $this->max_attempts = $max_attempts;
        $this->window_seconds = $window_seconds;
        $this->clock = $clock ?? new DateTimeImmutable();
    }

    public function assertAllowed(string $username, string $source): void
    {
        $now = $this->clock;
        $since = $now->modify('-' . $this->window_seconds . ' seconds');
        [$username_hash, $source_hash] = $this->identityHashes($username, $source);

        $failed_attempts = $this->attempt_repository->countFailedSince(
            $username_hash,
            $source_hash,
            $since,
        );

        if ($failed_attempts >= $this->max_attempts) {
            throw new RateLimitExceededException($this->window_seconds);
        }
    }

    public function record(string $username, string $source, bool $was_successful): void
    {
        $now = $this->clock;
        [$username_hash, $source_hash] = $this->identityHashes($username, $source);

        $this->attempt_repository->deleteBefore($now->modify('-1 day'));
        $this->attempt_repository->create(
            $username_hash,
            $source_hash,
            $now,
            $was_successful,
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function identityHashes(string $username, string $source): array
    {
        $key = Config::string('app.key', '');
        if ($key === '') {
            $key = 'local-rate-limit-test-key';
        }

        return [
            hash_hmac('sha256', mb_strtolower(trim($username), 'UTF-8'), $key),
            hash_hmac('sha256', trim($source), $key),
        ];
    }
}
