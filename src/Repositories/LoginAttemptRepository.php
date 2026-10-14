<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use DateTimeImmutable;
use PDO;

final class LoginAttemptRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function create(
        string $username_hash,
        string $source_hash,
        DateTimeImmutable $attempted_at,
        bool $was_successful
    ): void {
        $statement = $this->db->prepare(
            'INSERT INTO auth_login_attempts '
            . '(username_hash, source_hash, attempted_at, was_successful) '
            . 'VALUES (:username_hash, :source_hash, :attempted_at, :was_successful)'
        );
        $statement->execute([
            'username_hash' => $username_hash,
            'source_hash' => $source_hash,
            'attempted_at' => $attempted_at->format('Y-m-d H:i:s'),
            'was_successful' => $was_successful ? 1 : 0,
        ]);
    }

    public function countFailedSince(
        string $username_hash,
        string $source_hash,
        DateTimeImmutable $since
    ): int {
        $statement = $this->db->prepare(
            'SELECT COUNT(*) FROM auth_login_attempts '
            . 'WHERE username_hash = :username_hash '
            . 'AND source_hash = :source_hash '
            . 'AND attempted_at >= :since '
            . 'AND was_successful = 0'
        );
        $statement->execute([
            'username_hash' => $username_hash,
            'source_hash' => $source_hash,
            'since' => $since->format('Y-m-d H:i:s'),
        ]);

        /** @var int|string|false $count */
        $count = $statement->fetchColumn();
        return $count === false ? 0 : (int) $count;
    }

    public function deleteBefore(DateTimeImmutable $cutoff): int
    {
        $statement = $this->db->prepare(
            'DELETE FROM auth_login_attempts WHERE attempted_at < :cutoff'
        );
        $statement->execute(['cutoff' => $cutoff->format('Y-m-d H:i:s')]);

        return $statement->rowCount();
    }
}
