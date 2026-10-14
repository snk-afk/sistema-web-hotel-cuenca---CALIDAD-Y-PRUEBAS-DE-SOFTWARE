<?php

declare(strict_types=1);

namespace Tests\Feature\Browser;

use App\Controllers\ViewController;
use App\Repositories\GuestRepository;
use App\Services\CashboxService;
use App\Services\ExpenseService;
use App\Services\GuestService;
use App\Services\HousekeepingService;
use App\Services\InvoiceService;
use App\Services\ReportService;
use App\Services\ReservationService;
use App\Services\RoomService;
use App\Services\StoreService;
use App\Services\UserService;
use Database\Seeds\DevelopmentSeeder;
use InvalidArgumentException;
use Tests\Support\MySqlTestCase;

final class ClientE2ETest extends MySqlTestCase
{
    private ViewController $view_controller;
    private UserService $user_service;

    protected function setUp(): void
    {
        parent::setUp();

        $seeder = new DevelopmentSeeder($this->pdo);
        $seeder->run();

        $room_service = new RoomService($this->pdo);
        $cashbox_service = new CashboxService($this->pdo);
        $store_service = new StoreService($this->pdo);
        $expense_service = new ExpenseService($this->pdo);
        $housekeeping_service = new HousekeepingService($this->pdo);
        $this->user_service = new UserService($this->pdo);
        $invoice_service = new InvoiceService($this->pdo);
        $report_service = new ReportService($this->pdo);
        $guest_service = new GuestService(new GuestRepository($this->pdo));
        $reservation_service = new ReservationService($this->pdo);

        $this->view_controller = new ViewController(
            $room_service,
            $cashbox_service,
            $store_service,
            $expense_service,
            $housekeeping_service,
            $this->user_service,
            $invoice_service,
            $report_service,
            $guest_service,
            $reservation_service
        );

        // Simulate authenticated session for view controllers
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testLoginViewRenders(): void
    {
        ob_start();
        $this->view_controller->login();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Iniciar Sesión', $output);
        $this->assertStringContainsString('CS-Hotel System', $output);
    }

    public function testAllClientPagesRenderSuccessfully(): void
    {
        $pages = [
            'dashboard' => 'Mapa de Habitaciones',
            'rooms' => 'Gestión de Habitaciones y Tarifas',
            'guests' => 'Maestro de Huéspedes',
            'reservations' => 'Gestión de Reservas',
            'folios' => 'Estados de Cuenta / Folios',
            'store' => 'Punto de Venta (POS) Tienda',
            'cashbox' => 'Turnos y Movimientos de Caja',
            'expenses' => 'Egresos y Otros Ingresos',
            'housekeeping' => 'Tareas de Limpieza y Mantenimiento',
            'users' => 'Usuarios, Roles y Bitácora de Auditoría',
            'invoices' => 'Comprobantes y Facturación SUNAT',
            'reports' => 'Reportes Ejecutivos',
        ];

        foreach ($pages as $method => $expected_title) {
            ob_start();
            /** @var callable $callable */
            $callable = [$this->view_controller, $method];
            call_user_func($callable);
            $output = (string) ob_get_clean();

            $this->assertStringContainsString($expected_title, $output, "Page method {$method} failed to render.");
        }
    }

    public function testAuthenticationForFourRoles(): void
    {
        $credentials = [
            ['username' => 'admin', 'pass' => 'Admin123!'],
            ['username' => 'receptionist', 'pass' => 'Recep123!'],
            ['username' => 'housekeeper', 'pass' => 'Clean123!'],
            ['username' => 'accountant', 'pass' => 'Count123!'],
        ];

        foreach ($credentials as $cred) {
            $user = $this->user_service->authenticate($cred['username'], $cred['pass']);
            $this->assertNotNull($user, "Authentication failed for role {$cred['username']}");
            $this->assertEquals($cred['username'], $user->username);
        }
    }

    public function testInvalidLoginThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->user_service->authenticate('invalid_user', 'wrong_password');
    }
}
