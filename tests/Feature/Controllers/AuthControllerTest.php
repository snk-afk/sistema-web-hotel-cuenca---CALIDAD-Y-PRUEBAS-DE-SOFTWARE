<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\AuthController;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\UserService;
use Tests\Support\MySqlTestCase;

final class AuthControllerTest extends MySqlTestCase
{
    private UserService $user_service;

    protected function setUp(): void
    {
        parent::setUp();

        $role_repo = new RoleRepository($this->pdo);
        $role = $role_repo->create('admin', 'Administrador');

        $user_repo = new UserRepository($this->pdo, $role_repo);
        $user_repo->create($role->id, 'admin', 'admin@hotel.com', password_hash('Pass123', PASSWORD_DEFAULT), 'Admin');

        $this->user_service = new UserService($this->pdo, $user_repo, $role_repo);
    }

    public function testLoginRejectsInvalidCredentials(): void
    {
        $controller = new AuthController($this->user_service);

        // Simulate request payload
        $_POST['username'] = 'admin';
        $_POST['password'] = 'WrongPassword';

        ob_start();
        $controller->login();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('error', $data['status']);
    }
}
