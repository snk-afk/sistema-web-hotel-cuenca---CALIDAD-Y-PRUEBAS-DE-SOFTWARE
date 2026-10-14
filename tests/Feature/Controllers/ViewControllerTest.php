<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

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
use Tests\Support\MySqlTestCase;

final class ViewControllerTest extends MySqlTestCase
{
    private ViewController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUserFixture(1, 'view');

        // Simulate an authenticated session for view controllers that call
        // Session::requireAuth() (dashboard, cashbox).
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['user_id'] = 1;
        $_SESSION['username'] = 'admin';
        $_SESSION['role'] = 'admin';

        $room_service = new RoomService($this->pdo);
        $cashbox_service = new CashboxService($this->pdo);
        $store_service = new StoreService($this->pdo);
        $expense_service = new ExpenseService($this->pdo);
        $housekeeping_service = new HousekeepingService($this->pdo);
        $user_service = new UserService($this->pdo);
        $invoice_service = new InvoiceService($this->pdo);
        $report_service = new ReportService($this->pdo);
        $guest_service = new GuestService(new GuestRepository($this->pdo));
        $reservation_service = new ReservationService($this->pdo);

        $this->controller = new ViewController(
            $room_service,
            $cashbox_service,
            $store_service,
            $expense_service,
            $housekeeping_service,
            $user_service,
            $invoice_service,
            $report_service,
            $guest_service,
            $reservation_service
        );
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testDashboardRendersHtml(): void
    {
        ob_start();
        $this->controller->dashboard();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Mapa de Habitaciones', $output);
        $this->assertStringContainsString('drawer-pill-op-checkin', $output);
        $this->assertStringContainsString('drawer-pill-op-reservation', $output);
        $this->assertStringContainsString('Reservar a Futuro', $output);
        $this->assertStringContainsString('drawer-future-checkin-at', $output);
        $this->assertStringContainsString('box-calculated-checkout-preview', $output);
        $this->assertStringContainsString('drawer-guest-select', $output);
        $this->assertStringContainsString('drawer-room-reservations-box', $output);
        $this->assertStringContainsString('window.allReservationsCatalog', $output);
    }

    public function testGuestsRendersHtmlWithDeepLinks(): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO guests (id, document_type, document_number, name) VALUES (:id, :document_type, :document_number, :name)'
        );
        $statement->execute([
            'id' => 5,
            'document_type' => 'DNI',
            'document_number' => '77889900',
            'name' => 'Carlos Ruiz',
        ]);

        ob_start();
        $this->controller->guests();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Maestro de Huéspedes', $output);
        $this->assertStringContainsString('/reservas?search=77889900', $output);
        $this->assertStringContainsString('goToReservationsHistoryForGuest', $output);
    }

    public function testRoomsRendersHtmlWithDeepLinks(): void
    {
        $category = $this->pdo->prepare(
            'INSERT INTO room_categories (id, name) VALUES (:id, :name)'
        );
        $category->execute(['id' => 1, 'name' => 'Simple']);
        $room = $this->pdo->prepare(
            'INSERT INTO rooms (id, room_category_id, number, status) VALUES (:id, :room_category_id, :number, :status)'
        );
        $room->execute([
            'id' => 10,
            'room_category_id' => 1,
            'number' => '305',
            'status' => 'free',
        ]);

        ob_start();
        $this->controller->rooms();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Gestión de Habitaciones y Tarifas', $output);
        $this->assertStringContainsString('/reservas?search=305', $output);
    }

    public function testReservationsRendersHtml(): void
    {
        ob_start();
        $this->controller->reservations();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Libro Maestro & Historial de Reservas', $output);
        $this->assertStringContainsString('window.allReservationsCatalog', $output);
    }

    public function testReservationsUiContractSupportsSettlementActions(): void
    {
        $javascript = (string) file_get_contents(
            dirname(__DIR__, 3) . '/public/assets/js/pages/reservations.js'
        );
        $view = (string) file_get_contents(
            dirname(__DIR__, 3) . '/views/pages/reservations.php'
        );

        $this->assertStringContainsString("action: 'no_show'", $javascript);
        $this->assertStringContainsString('reason', $javascript);
        $this->assertStringContainsString("'no_show'", $view);
        $this->assertStringContainsString("'pending_refund'", $view);
    }

    public function testStoreRendersHtml(): void
    {
        ob_start();
        $this->controller->store();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Punto de Venta (POS) — Tienda del Hotel', $output);
        $this->assertStringContainsString('metric-products-count', $output);
        $this->assertStringContainsString('metric-low-stock', $output);
        $this->assertStringContainsString('metric-inventory-value', $output);
        $this->assertStringContainsString('pos-catalog-container', $output);
        $this->assertStringContainsString('store-cart-items', $output);
        $this->assertStringContainsString('btn-sell-direct', $output);
        $this->assertStringContainsString('btn-charge-room', $output);
        $this->assertStringContainsString('modal-create-product', $output);
        $this->assertStringContainsString('modal-charge-room', $output);
        $this->assertStringContainsString('modal-store-checkout', $output);
        $this->assertStringContainsString('store.js', $output);
    }

    public function testReportsRendersHtml(): void
    {
        ob_start();
        $this->controller->reports();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Cuadro de Mando y Reportes Ejecutivos', $output);
        $this->assertStringContainsString('kpi-occupancy-rate', $output);
        $this->assertStringContainsString('kpi-adr-value', $output);
        $this->assertStringContainsString('kpi-revpar-value', $output);
        $this->assertStringContainsString('kpi-net-profit', $output);
        $this->assertStringContainsString('btn-export-pdf', $output);
        $this->assertStringContainsString('btn-export-csv', $output);
        $this->assertStringContainsString('reports.js', $output);
    }

    public function testFoliosRendersHtmlWithComponents(): void
    {
        ob_start();
        $this->controller->folios();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Estados de Cuenta / Folios', $output);
        $this->assertStringContainsString('metric-folios-open', $output);
        $this->assertStringContainsString('metric-total-charges', $output);
        $this->assertStringContainsString('metric-pending-balance', $output);
        $this->assertStringContainsString('table-folios-body', $output);
        $this->assertStringContainsString('modal-folio-detail', $output);
        $this->assertStringContainsString('folio-charges-body', $output);
        $this->assertStringContainsString('folio-payments-body', $output);
        $this->assertStringContainsString('filter-folios-input', $output);
        $this->assertStringContainsString('folios.js', $output);
    }

    public function testCashboxRendersHtmlWithComponents(): void
    {
        ob_start();
        $this->controller->cashbox();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Gestión de Caja Chica y Turnos de Trabajo', $output);
        $this->assertStringContainsString('cashbox-no-shift-container', $output);
        $this->assertStringContainsString('cashbox-active-shift-container', $output);
        $this->assertStringContainsString('metric-initial-cash', $output);
        $this->assertStringContainsString('metric-expected-cash', $output);
        $this->assertStringContainsString('modal-new-movement', $output);
        $this->assertStringContainsString('modal-close-shift', $output);
        $this->assertStringContainsString('table-cashbox-movements', $output);
        $this->assertStringContainsString('cashbox.js', $output);
    }

    public function testCashboxRendersHistorySection(): void
    {
        ob_start();
        $this->controller->cashbox();
        $output = (string) ob_get_clean();

        // CA-8: Section "Historial de Turnos" with table and "Ver Detalle" button
        $this->assertStringContainsString('Historial de Turnos', $output);
        $this->assertStringContainsString('table-cashbox-history', $output);
        $this->assertStringContainsString('table-cashbox-history-body', $output);
        // CA-9: Modal for shift detail
        $this->assertStringContainsString('modal-shift-detail', $output);
        // CA-14: Export buttons
        $this->assertStringContainsString('btn-export-csv', $output);
        $this->assertStringContainsString('btn-export-pdf', $output);
        // History filters
        $this->assertStringContainsString('history-filter-status', $output);
        $this->assertStringContainsString('history-filter-user-id', $output);
        $this->assertStringContainsString('history-filter-from', $output);
        $this->assertStringContainsString('history-filter-to', $output);
    }

    public function testExpensesRendersHtmlWithComponents(): void
    {
        ob_start();
        $this->controller->expenses();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Egresos de Caja Chica y Otros Ingresos', $output);
        $this->assertStringContainsString('metric-total-expenses', $output);
        $this->assertStringContainsString('metric-total-other-incomes', $output);
        $this->assertStringContainsString('metric-net-balance', $output);
        $this->assertStringContainsString('modal-create-expense', $output);
        $this->assertStringContainsString('modal-create-income', $output);
        $this->assertStringContainsString('table-expenses', $output);
        $this->assertStringContainsString('table-incomes', $output);
        $this->assertStringContainsString('expenses.js', $output);
    }

    public function testHousekeepingRendersHtmlWithComponents(): void
    {
        ob_start();
        $this->controller->housekeeping();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Tareas de Limpieza (Housekeeping) y Averías', $output);
        $this->assertStringContainsString('metric-tasks-pending', $output);
        $this->assertStringContainsString('metric-tasks-progress', $output);
        $this->assertStringContainsString('metric-rooms-cleaning', $output);
        $this->assertStringContainsString('metric-rooms-maintenance', $output);
        $this->assertStringContainsString('modal-create-task', $output);
        $this->assertStringContainsString('table-housekeeping', $output);
        $this->assertStringContainsString('housekeeping.js', $output);
    }

    public function testUsersRendersHtmlWithComponents(): void
    {
        ob_start();
        $this->controller->users();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Usuarios del Sistema, Permisos (RBAC) y Seguridad', $output);
        $this->assertStringContainsString('metric-users-total', $output);
        $this->assertStringContainsString('metric-users-active', $output);
        $this->assertStringContainsString('modal-create-user', $output);
        $this->assertStringContainsString('table-users', $output);
        $this->assertStringContainsString('users.js', $output);
    }

    public function testInvoicesRendersHtmlWithComponents(): void
    {
        ob_start();
        $this->controller->invoices();
        $output = (string) ob_get_clean();

        $this->assertStringContainsString('Facturación Electrónica SUNAT y Comprobantes', $output);
        $this->assertStringContainsString('metric-invoices-total-amount', $output);
        $this->assertStringContainsString('metric-boletas-count', $output);
        $this->assertStringContainsString('metric-facturas-count', $output);
        $this->assertStringContainsString('modal-invoice-detail', $output);
        $this->assertStringContainsString('modal-issue-invoice', $output);
        $this->assertStringContainsString('table-invoices', $output);
        $this->assertStringContainsString('invoices.js', $output);
    }
}
