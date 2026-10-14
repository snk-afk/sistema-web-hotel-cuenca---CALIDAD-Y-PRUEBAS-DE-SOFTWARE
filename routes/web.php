<?php

declare(strict_types=1);

use App\Controllers\AuditLogController;
use App\Controllers\AuthController;
use App\Controllers\CashboxController;
use App\Controllers\ExpenseController;
use App\Controllers\GuestController;
use App\Controllers\HealthController;
use App\Controllers\HousekeepingController;
use App\Controllers\InvoiceController;
use App\Controllers\OtherIncomeController;
use App\Controllers\ProductController;
use App\Controllers\ReportController;
use App\Controllers\ReservationController;
use App\Controllers\RoomController;
use App\Controllers\StorageController;
use App\Controllers\StoreController;
use App\Controllers\UserController;
use App\Controllers\ViewController;
use App\Support\AuthMiddleware;
use App\Support\Router;

$router = new Router();

// Attach security middleware (auth + RBAC + CSRF)
$router->setAuthMiddleware(new AuthMiddleware());

$router->get('/api/status', function () {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'message' => 'CS-Hotel API Active',
        'version' => '1.0.0',
    ]);
});
$router->get('/api/health/live', [HealthController::class, 'live']);
$router->get('/api/health/ready', [HealthController::class, 'ready']);

// UI Pages (Full Client Views)
$router->get('/login', [ViewController::class, 'login']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/', [ViewController::class, 'dashboard']);
$router->get('/habitaciones', [ViewController::class, 'rooms']);
$router->get('/huespedes', [ViewController::class, 'guests']);
$router->get('/reservas', [ViewController::class, 'reservations']);
$router->get('/folios', [ViewController::class, 'folios']);
$router->get('/tienda', [ViewController::class, 'store']);
$router->get('/caja', [ViewController::class, 'cashbox']);
$router->get('/egresos', [ViewController::class, 'expenses']);
$router->get('/limpieza', [ViewController::class, 'housekeeping']);
$router->get('/usuarios', [ViewController::class, 'users']);
$router->get('/comprobantes', [ViewController::class, 'invoices']);
$router->get('/comprobantes/download', [InvoiceController::class, 'download']);
$router->get('/reportes', [ViewController::class, 'reports']);

// Auth & Users API
$router->get('/api/auth/csrf-token', [AuthController::class, 'csrfToken']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/users', [UserController::class, 'index']);
$router->post('/api/users', [UserController::class, 'create']);
$router->get('/api/audit-logs', [AuditLogController::class, 'index']);

// Rooms & Tariffs API
$router->get('/api/rooms', [RoomController::class, 'index']);
$router->post('/api/rooms', [RoomController::class, 'handlePost']);
$router->get('/api/categories', [RoomController::class, 'categories']);
$router->post('/api/categories', [RoomController::class, 'handleCategoryPost']);
$router->get('/api/tariffs', [RoomController::class, 'tariffs']);
$router->post('/api/tariffs', [RoomController::class, 'handleTariffPost']);

// Guests API
$router->get('/api/guests', [GuestController::class, 'index']);
$router->get('/api/guests/lookup', [GuestController::class, 'lookup']);
$router->post('/api/guests', [GuestController::class, 'create']);

// Reservations API
$router->get('/api/reservations', [ReservationController::class, 'index']);
$router->post('/api/reservations', [ReservationController::class, 'handlePost']);
$router->post('/api/reservations/refunds/approve', [ReservationController::class, 'approveRefund']);

// Cashbox Shifts API
$router->get('/api/cashbox/current', [CashboxController::class, 'current']);
$router->post('/api/cashbox/open', [CashboxController::class, 'open']);
$router->post('/api/cashbox/close', [CashboxController::class, 'close']);
$router->post('/api/cashbox/movement', [CashboxController::class, 'movement']);
$router->get('/api/cashbox/shifts', [CashboxController::class, 'history']);
$router->get('/api/cashbox/shift', [CashboxController::class, 'show']);
$router->get('/api/cashbox/export/csv', [CashboxController::class, 'exportCsv']);
$router->get('/api/cashbox/export/pdf', [CashboxController::class, 'exportPdf']);

// Products & Store POS API
$router->get('/api/products', [ProductController::class, 'index']);
$router->post('/api/products', [ProductController::class, 'create']);
$router->post('/api/store/sales', [StoreController::class, 'sellDirect']);
$router->post('/api/store/charge-to-room', [StoreController::class, 'chargeToRoom']);

// Expenses & Other Incomes API
$router->get('/api/expenses', [ExpenseController::class, 'index']);
$router->post('/api/expenses', [ExpenseController::class, 'create']);
$router->get('/api/other-incomes', [OtherIncomeController::class, 'index']);
$router->post('/api/other-incomes', [OtherIncomeController::class, 'create']);

// Housekeeping & Maintenance Tasks API
$router->get('/api/housekeeping/tasks', [HousekeepingController::class, 'index']);
$router->post('/api/housekeeping/tasks', [HousekeepingController::class, 'handlePost']);

// Invoices & Billing SUNAT/R2 API
$router->get('/api/invoices', [InvoiceController::class, 'index']);
$router->post('/api/invoices/issue', [InvoiceController::class, 'issue']);
$router->get('/api/invoices/{id}/escpos', [InvoiceController::class, 'escpos']);
$router->get('/storage/comprobantes/{file}', [StorageController::class, 'download']);

// Reports & Analytics API
$router->get('/api/reports/occupancy', [ReportController::class, 'occupancy']);
$router->get('/api/reports/financial', [ReportController::class, 'financial']);
$router->get('/api/reports/export/csv', [ReportController::class, 'exportCsv']);
$router->get('/api/reports/export/pdf', [ReportController::class, 'exportPdf']);

return $router;
