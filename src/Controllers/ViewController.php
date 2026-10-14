<?php

declare(strict_types=1);

namespace App\Controllers;

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
use App\Support\Session;
use App\Support\View;

final class ViewController
{
    private RoomService $room_service;
    private ?GuestService $guest_service;
    private ?ReservationService $reservation_service;
    private CashboxService $cashbox_service;
    private StoreService $store_service;
    private ExpenseService $expense_service;
    private HousekeepingService $housekeeping_service;
    private UserService $user_service;
    private InvoiceService $invoice_service;
    private ReportService $report_service;

    public function __construct(
        ?RoomService $room_service = null,
        ?CashboxService $cashbox_service = null,
        ?StoreService $store_service = null,
        ?ExpenseService $expense_service = null,
        ?HousekeepingService $housekeeping_service = null,
        ?UserService $user_service = null,
        ?InvoiceService $invoice_service = null,
        ?ReportService $report_service = null,
        ?GuestService $guest_service = null,
        ?ReservationService $reservation_service = null
    ) {
        $this->room_service = $room_service ?? new RoomService();
        $this->cashbox_service = $cashbox_service ?? new CashboxService();
        $this->store_service = $store_service ?? new StoreService();
        $this->expense_service = $expense_service ?? new ExpenseService();
        $this->housekeeping_service = $housekeeping_service ?? new HousekeepingService();
        $this->user_service = $user_service ?? new UserService();
        $this->invoice_service = $invoice_service ?? new InvoiceService();
        $this->report_service = $report_service ?? new ReportService();
        $this->guest_service = $guest_service;
        $this->reservation_service = $reservation_service;
    }

    private function getGuestService(): GuestService
    {
        return $this->guest_service ??= new GuestService();
    }

    private function getReservationService(): ReservationService
    {
        return $this->reservation_service ??= new ReservationService();
    }

    public function login(): void
    {
        View::render('pages/login', [
            'page_title' => 'Iniciar Sesión',
        ]);
    }

    public function dashboard(): void
    {
        $session_user_id = Session::requireAuth();
        $rooms = $this->room_service->getDashboardMap();
        $shift = $this->cashbox_service->getCurrentShift($session_user_id);
        $guests = $this->getGuestService()->getGuestsCatalog();
        $reservations = $this->getReservationService()->getReservationsCatalog();

        View::render('pages/dashboard', [
            'page_title' => 'Mapa de Habitaciones',
            'active_menu' => 'dashboard',
            'rooms' => $rooms,
            'shift' => $shift,
            'guests' => $guests,
            'reservations' => $reservations,
        ]);
    }

    public function rooms(): void
    {
        $rooms = $this->room_service->getDashboardMap();
        $categories = $this->room_service->getCategoriesCatalog();
        $tariffs = $this->room_service->getTariffsCatalog();

        View::render('pages/rooms', [
            'page_title' => 'Gestión de Habitaciones y Tarifas',
            'active_menu' => 'rooms',
            'rooms' => $rooms,
            'categories' => $categories,
            'tariffs' => $tariffs,
        ]);
    }

    public function guests(): void
    {
        $guests = $this->getGuestService()->getGuestsCatalog();

        $total_guests = count($guests);
        $total_stays = array_sum(array_column($guests, 'stays_count'));
        $total_spent = array_sum(array_column($guests, 'total_spent'));
        $vip_count = count(array_filter($guests, fn($g) => $g['stays_count'] >= 2 || (is_string($g['notes'] ?? null) && str_contains(strtoupper($g['notes']), 'VIP'))));

        View::render('pages/guests', [
            'page_title' => 'Maestro de Huéspedes',
            'active_menu' => 'guests',
            'guests' => $guests,
            'metrics' => [
                'total_guests' => $total_guests,
                'total_stays' => $total_stays,
                'total_spent' => $total_spent,
                'vip_count' => $vip_count,
            ],
        ]);
    }

    public function reservations(): void
    {
        $reservations = $this->getReservationService()->getReservationsCatalog();
        $metrics = $this->getReservationService()->getReservationMetrics();
        $rooms = $this->room_service->getDashboardMap();
        $categories = $this->room_service->getCategoriesCatalog();
        $tariffs = $this->room_service->getTariffsCatalog();
        $guests = $this->getGuestService()->getGuestsCatalog();

        View::render('pages/reservations', [
            'page_title' => 'Gestión de Reservas',
            'active_menu' => 'reservations',
            'reservations' => $reservations,
            'metrics' => $metrics,
            'rooms' => $rooms,
            'categories' => $categories,
            'tariffs' => $tariffs,
            'guests' => $guests,
        ]);
    }

    public function folios(): void
    {
        View::render('pages/folios', [
            'page_title' => 'Estados de Cuenta / Folios',
            'active_menu' => 'folios',
        ]);
    }

    public function store(): void
    {
        $products = $this->store_service->getActiveProducts();

        View::render('pages/store', [
            'page_title' => 'Punto de Venta (POS) Tienda',
            'active_menu' => 'store',
            'products' => $products,
        ]);
    }

    public function cashbox(): void
    {
        $session_user_id = Session::requireAuth();
        $shift = $this->cashbox_service->getCurrentShift($session_user_id);

        View::render('pages/cashbox', [
            'page_title' => 'Turnos y Movimientos de Caja',
            'active_menu' => 'cashbox',
            'shift' => $shift,
        ]);
    }

    public function expenses(): void
    {
        $expenses = $this->expense_service->listExpenses();
        $other_incomes = $this->expense_service->listOtherIncomes();

        View::render('pages/expenses', [
            'page_title' => 'Egresos y Otros Ingresos',
            'active_menu' => 'expenses',
            'expenses' => $expenses,
            'other_incomes' => $other_incomes,
        ]);
    }

    public function housekeeping(): void
    {
        $tasks = $this->housekeeping_service->listActiveTasks();

        View::render('pages/housekeeping', [
            'page_title' => 'Tareas de Limpieza y Mantenimiento',
            'active_menu' => 'housekeeping',
            'tasks' => $tasks,
        ]);
    }

    public function users(): void
    {
        $users = $this->user_service->listUsers();

        View::render('pages/users', [
            'page_title' => 'Usuarios, Roles y Bitácora de Auditoría',
            'active_menu' => 'users',
            'users' => $users,
        ]);
    }

    public function invoices(): void
    {
        $invoices = $this->invoice_service->listInvoices();

        View::render('pages/invoices', [
            'page_title' => 'Comprobantes y Facturación SUNAT',
            'active_menu' => 'invoices',
            'invoices' => $invoices,
        ]);
    }

    public function reports(): void
    {
        $occupancy = $this->report_service->getOccupancyReport();
        $financial = $this->report_service->getFinancialSummary();

        View::render('pages/reports', [
            'page_title' => 'Reportes Ejecutivos',
            'active_menu' => 'reports',
            'occupancy' => $occupancy,
            'financial' => $financial,
        ]);
    }
}
