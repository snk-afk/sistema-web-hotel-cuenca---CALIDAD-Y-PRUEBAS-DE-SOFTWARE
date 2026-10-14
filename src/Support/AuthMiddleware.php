<?php

declare(strict_types=1);

namespace App\Support;

use App\Repositories\UserRepository;
use RuntimeException;

/**
 * Middleware de autenticación, RBAC y CSRF.
 *
 * Se ejecuta antes de dispatchar el handler del Router.
 * Verifica: sesión activa (401), permisos RBAC (403), y CSRF en mutations (419).
 */
final class AuthMiddleware
{
    /**
     * Rutas públicas que no requieren autenticación.
     *
     * @var array<int, string>
     */
    private const array PUBLIC_ROUTES = [
        '/api/status',
        '/api/health/live',
        '/api/health/ready',
        '/api/auth/login',
        '/api/auth/csrf-token',
        '/login',
    ];

    /**
     * Mapa de ruta+method → permiso requerido.
     * Los catálogos públicos del backoffice también quedan bajo RBAC para evitar
     * exposición de información operativa a cualquier sesión autenticada.
     *
     * @var array<string, string>
     */
    private const array PERMISSION_MAP = [
        'GET /api/rooms' => 'rooms.view',
        'GET /api/categories' => 'rooms.view',
        'GET /api/tariffs' => 'rooms.view',
        'GET /api/guests' => 'guests.manage',
        'GET /api/guests/lookup' => 'guests.manage',
        'GET /api/reservations' => 'reservations.manage',
        'GET /api/cashbox/current' => 'cashbox.manage',
        'GET /api/cashbox/shifts' => 'cashbox.manage',
        'GET /api/cashbox/shift' => 'cashbox.manage',
        'GET /api/cashbox/export/csv' => 'cashbox.manage',
        'GET /api/cashbox/export/pdf' => 'cashbox.manage',
        'GET /api/products' => 'store.sell',
        'GET /api/expenses' => 'expenses.manage',
        'GET /api/other-incomes' => 'incomes.manage',
        'GET /api/housekeeping/tasks' => 'housekeeping.manage',
        'GET /api/invoices' => 'invoices.issue',
        'GET /api/invoices/{id}/escpos' => 'invoices.issue',
        'GET /comprobantes/download' => 'invoices.issue',
        'GET /api/users' => 'users.manage',
        'GET /api/audit-logs' => 'users.manage',
        'GET /api/reports/occupancy' => 'reports.view',
        'GET /api/reports/financial' => 'reports.view',
        'GET /api/reports/export/csv' => 'reports.view',
        'GET /api/reports/export/pdf' => 'reports.view',
        'GET /storage/comprobantes/{file}' => 'invoices.issue',
        'POST /api/rooms' => 'rooms.manage',
        'POST /api/categories' => 'rooms.manage',
        'POST /api/tariffs' => 'rooms.manage',
        'POST /api/guests' => 'guests.manage',
        'POST /api/reservations' => 'reservations.manage',
        'POST /api/reservations/refunds/approve' => 'reservations.refund',
        'POST /api/cashbox/open' => 'cashbox.manage',
        'POST /api/cashbox/close' => 'cashbox.manage',
        'POST /api/cashbox/movement' => 'cashbox.movement',
        'POST /api/products' => 'store.manage',
        'POST /api/store/sales' => 'store.sell',
        'POST /api/store/charge-to-room' => 'store.sell',
        'POST /api/expenses' => 'expenses.manage',
        'POST /api/other-incomes' => 'incomes.manage',
        'POST /api/housekeeping/tasks' => 'housekeeping.manage',
        'POST /api/invoices/issue' => 'invoices.issue',
        'POST /api/users' => 'users.manage',
    ];

    private ?UserRepository $user_repository;

    public function __construct(?UserRepository $user_repository = null)
    {
        $this->user_repository = $user_repository;
    }

    /**
     * Verifica si la petición pasa los controles de seguridad.
     * Devuelve el user_id si la petición está autenticada.
     * Lanza RuntimeException con el código de error correspondiente si falla.
     *
     * @return int El ID del usuario autenticado (0 si la ruta es pública).
     * @throws AuthException Si la autenticación, autorización o CSRF fallan.
     */
    public function check(string $method, string $path): int
    {
        $method = strtoupper($method);

        // Rutas públicas: no requieren autenticación
        if ($this->isPublicRoute($path) && !$this->isProtectedMutation($method, $path)) {
            return 0;
        }

        // Verificar sesión. Las vistas UI redirigen al login; las APIs conservan
        // el contrato JSON 401 para clientes programáticos.
        $user_id = Session::userId();

        if ($user_id === null) {
            if ($this->isUiRoute($path)) {
                throw new AuthException('No autenticado', 302, '/login');
            }

            throw new AuthException('No autenticado', 401);
        }

        // Verificar que el usuario sigue activo
        $user = $this->getUserRepository()->findById($user_id);

        if ($user === null || !$user->is_active) {
            Session::destroy();
            throw new AuthException('Cuenta desactivada', 403);
        }

        // Verificar CSRF en mutations (excepto logout: permite cerrar sesión
        // incluso si el token CSRF expiró, para evitar sesiones zombie)
        if (Session::isMutation() && $path !== '/api/auth/logout') {
            $token = Session::csrfTokenFromRequest();
            if (!Session::verifyCsrf($token)) {
                throw new AuthException('Token CSRF inválido', 419);
            }
        }

        // Verificar permisos RBAC
        $permission_key = $this->permissionFor($method, $path);

        if ($permission_key !== null && $user->role !== null) {
            if (!PermissionGuard::can($user, $permission_key)) {
                throw new AuthException('Permiso insuficiente', 403);
            }
        }

        return $user_id;
    }

    /**
     * Una ruta solo es pública si está explícitamente incluida en la allowlist.
     */
    private function isPublicRoute(string $path): bool
    {
        return in_array($path, self::PUBLIC_ROUTES, true);
    }

    private function isUiRoute(string $path): bool
    {
        return !str_starts_with($path, '/api/');
    }

    /**
     * Una ruta pública que es mutation (POST /login, POST /api/auth/login)
     * no requiere CSRF (no hay sesión previa).
     */
    private function isProtectedMutation(string $method, string $path): bool
    {
        // POST /login no requiere CSRF (es el punto de entrada de autenticación)
        // POST /api/auth/login no requiere CSRF (no hay sesión previa)
        return false;
    }

    private function getUserRepository(): UserRepository
    {
        return $this->user_repository ??= new UserRepository();
    }

    private function permissionFor(string $method, string $path): ?string
    {
        $exact_key = "{$method} {$path}";
        if (isset(self::PERMISSION_MAP[$exact_key])) {
            return self::PERMISSION_MAP[$exact_key];
        }

        foreach (self::PERMISSION_MAP as $route_key => $permission) {
            [$route_method, $route_path] = explode(' ', $route_key, 2);
            if ($route_method !== $method || !$this->matchesRouteTemplate($route_path, $path)) {
                continue;
            }

            return $permission;
        }

        return null;
    }

    private function matchesRouteTemplate(string $template, string $path): bool
    {
        $template_segments = $template === '/' ? [] : explode('/', trim($template, '/'));
        $path_segments = $path === '/' ? [] : explode('/', trim($path, '/'));

        if (count($template_segments) !== count($path_segments)) {
            return false;
        }

        foreach ($template_segments as $index => $template_segment) {
            if (str_starts_with($template_segment, '{') && str_ends_with($template_segment, '}')) {
                continue;
            }

            if ($template_segment !== rawurldecode($path_segments[$index])) {
                return false;
            }
        }

        return true;
    }
}
