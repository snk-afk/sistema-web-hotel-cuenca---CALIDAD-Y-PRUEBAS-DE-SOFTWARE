<?php

declare(strict_types=1);

namespace App\Models;

final readonly class HousekeepingTask
{
    public function __construct(
        public int $id,
        public int $room_id,
        public string $task_type,
        public string $status,
        public string $priority,
        public ?int $assigned_to_user_id,
        public ?string $started_at,
        public ?string $completed_at,
        public ?string $notes,
        public int $created_by_user_id,
        public string $created_at,
        public string $updated_at,
        public ?Room $room = null,
    ) {
    }
}
