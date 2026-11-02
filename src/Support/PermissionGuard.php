<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class PermissionGuard
{
    public static function can(User $user, string $permission_key): bool
    {
        if (!$user->is_active) {
            return false;
        }

        if ($user->role === null) {
            return false;
        }

        // Admin has all permissions
        if ($user->role->name === 'admin') {
            return true;
        }

        foreach ($user->role->permissions as $p) {
            if ($p->key_name === $permission_key) {
                return true;
            }
        }

        return false;
    }
}
