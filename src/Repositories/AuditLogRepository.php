<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\AuditLog;
use PDO;

final class AuditLogRepository
{
    private PDO $db;
    private UserRepository $user_repository;

    public function __construct(?PDO $db = null, ?UserRepository $user_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->user_repository = $user_repository ?? new UserRepository($this->db);
    }

    public function findById(int $id): ?AuditLog
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, action, entity_name, entity_id, old_values, new_values, ip_address, created_at
             FROM audit_logs
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, user_id: int|string, action: string, entity_name: string, entity_id: int|string, old_values: ?string, new_values: ?string, ip_address: ?string, created_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $user = $this->user_repository->findById((int) $row['user_id']);

        return new AuditLog(
            id: (int) $row['id'],
            user_id: (int) $row['user_id'],
            action: $row['action'],
            entity_name: $row['entity_name'],
            entity_id: (int) $row['entity_id'],
            old_values: $row['old_values'],
            new_values: $row['new_values'],
            ip_address: $row['ip_address'],
            created_at: $row['created_at'],
            user: $user,
        );
    }

    /**
     * @return array<int, AuditLog>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, user_id, action, entity_name, entity_id, old_values, new_values, ip_address, created_at
             FROM audit_logs
             ORDER BY id DESC
             LIMIT 100'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, user_id: int|string, action: string, entity_name: string, entity_id: int|string, old_values: ?string, new_values: ?string, ip_address: ?string, created_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): AuditLog {
                $user = $this->user_repository->findById((int) $row['user_id']);
                return new AuditLog(
                    id: (int) $row['id'],
                    user_id: (int) $row['user_id'],
                    action: $row['action'],
                    entity_name: $row['entity_name'],
                    entity_id: (int) $row['entity_id'],
                    old_values: $row['old_values'],
                    new_values: $row['new_values'],
                    ip_address: $row['ip_address'],
                    created_at: $row['created_at'],
                    user: $user,
                );
            },
            $rows
        );
    }

    public function create(
        int $user_id,
        string $action,
        string $entity_name,
        int $entity_id,
        ?string $old_values = null,
        ?string $new_values = null,
        ?string $ip_address = null
    ): AuditLog {
        $stmt = $this->db->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_name, entity_id, old_values, new_values, ip_address)
             VALUES (:user_id, :action, :entity_name, :entity_id, :old_values, :new_values, :ip_address)'
        );

        $stmt->execute([
            'user_id' => $user_id,
            'action' => $action,
            'entity_name' => $entity_name,
            'entity_id' => $entity_id,
            'old_values' => $old_values,
            'new_values' => $new_values,
            'ip_address' => $ip_address,
        ]);

        $id = (int) $this->db->lastInsertId();
        $created_statement = $this->db->prepare(
            'SELECT created_at FROM audit_logs WHERE id = :id'
        );
        $created_statement->execute(['id' => $id]);
        /** @var array{created_at: string}|false $created_row */
        $created_row = $created_statement->fetch();
        if ($created_row === false) {
            throw new \RuntimeException('No se pudo recuperar la auditoría recién creada.');
        }

        return new AuditLog(
            id: $id,
            user_id: $user_id,
            action: $action,
            entity_name: $entity_name,
            entity_id: $entity_id,
            old_values: $old_values,
            new_values: $new_values,
            ip_address: $ip_address,
            created_at: $created_row['created_at'],
        );
    }
}
