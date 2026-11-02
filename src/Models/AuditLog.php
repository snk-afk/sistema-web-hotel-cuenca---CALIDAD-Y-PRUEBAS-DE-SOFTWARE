<?php

declare(strict_types=1);

namespace App\Models;

final readonly class AuditLog
{
    public function __construct(
        public int $id,
        public int $user_id,
        public string $action,
        public string $entity_name,
        public int $entity_id,
        public ?string $old_values,
        public ?string $new_values,
        public ?string $ip_address,
        public string $created_at,
        public ?User $user = null,
    ) {
    }
}
