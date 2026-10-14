<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use Tests\Support\MySqlTestCase;

final class UserRepositoryTest extends MySqlTestCase
{
    public function testCreateAndFindUser(): void
    {
        $role_repo = new RoleRepository($this->pdo());
        $role = $role_repo->create('cajero', 'Cajero');

        $user_repo = new UserRepository($this->pdo(), $role_repo);
        $user = $user_repo->create($role->id, 'cajero1', 'cajero1@hotel.com', 'hash', 'Cajero Uno');

        $this->assertEquals('cajero1', $user->username);
        $this->assertEquals('Cajero', $user->role?->description);
    }
}
