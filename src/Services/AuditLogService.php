<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Models\AuditLog;
use App\Repositories\AuditLogRepository;
use PDO;

final class AuditLogService
{
    private PDO $db;
    private AuditLogRepository $audit_repository;

    public function __construct(?PDO $db = null, ?AuditLogRepository $audit_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->audit_repository = $audit_repository ?? new AuditLogRepository($this->db);
    }

    /**
     * @param array<string, mixed>|null $old_values
     * @param array<string, mixed>|null $new_values
     */
    public function logAction(
        int $user_id,
        string $action,
        string $entity_name,
        int $entity_id,
        ?array $old_values = null,
        ?array $new_values = null,
        ?string $ip_address = null
    ): AuditLog {
        $old_json = $old_values !== null ? json_encode($old_values) : null;
        $new_json = $new_values !== null ? json_encode($new_values) : null;

        return $this->audit_repository->create(
            user_id: $user_id,
            action: $action,
            entity_name: $entity_name,
            entity_id: $entity_id,
            old_values: is_string($old_json) ? $old_json : null,
            new_values: is_string($new_json) ? $new_json : null,
            ip_address: $ip_address
        );
    }

    /**
     * @return array<int, AuditLog>
     */
    public function getLogs(): array
    {
        return $this->audit_repository->findAll();
    }
}
