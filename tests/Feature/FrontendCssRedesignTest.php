<?php

declare(strict_types=1);

namespace Tests\Feature;

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
use Tests\Support\MySqlTestCase;

/**
 * SPEC-0041: Rediseño CSS Frontend — Namespaces BEM, Responsive Móvil y Desduplicación.
 *
 * Verifica los criterios de aceptación del spec a nivel de HTML servido y
 * estructura de archivos CSS.
 */
final class FrontendCssRedesignTest extends MySqlTestCase
{
    private ViewController $view_controller;

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
        $user_service = new UserService($this->pdo);
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
            $user_service,
            $invoice_service,
            $report_service,
            $guest_service,
            $reservation_service
        );

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

    // ─── Criterio 1: Namespace BEM en layout ───────────────────────────

    public function testHeaderUsesBemNamespaceClasses(): void
    {
        $output = $this->renderDashboard();

        $this->assertStringContainsString('ns-header__', $output, 'Header debe usar prefijo ns-header__');
        $this->assertStringNotContainsString('app-header"', $output, 'Clase legacy app-header ya no debe usarse en header');
    }

    public function testNavUsesBemNamespaceClasses(): void
    {
        $output = $this->renderDashboard();

        $this->assertStringContainsString('ns-nav__', $output, 'Nav debe usar prefijo ns-nav__');
        $this->assertStringNotContainsString('app-sidebar"', $output, 'Clase legacy app-sidebar ya no debe usarse en nav');
        $this->assertStringNotContainsString('nav-list"', $output, 'Clase legacy nav-list ya no debe usarse');
    }

    public function testFooterUsesBemNamespaceClasses(): void
    {
        $output = $this->renderDashboard();

        $this->assertStringContainsString('ns-footer__', $output, 'Footer debe usar prefijo ns-footer__');
        $this->assertStringNotContainsString('app-footer"', $output, 'Clase legacy app-footer ya no debe usarse');
    }

    // ─── Criterio 2: Menú lateral responsive en móvil (hamburguesa) ──

    public function testMobileNavToggleButtonExists(): void
    {
        $output = $this->renderDashboard();

        $this->assertStringContainsString('ns-nav__toggle', $output, 'Botón hamburguesa con clase ns-nav__toggle debe existir');
        $this->assertStringContainsString('nav-toggle.js', $output, 'El JS del toggle debe cargarse en el footer');
    }

    public function testNavOverlayBackdropExists(): void
    {
        $output = $this->renderDashboard();

        $this->assertStringContainsString('ns-nav__backdrop', $output, 'Overlay/backdrop del nav móvil debe existir');
    }

    // ─── Criterio 7: Carga dinámica de CSS de páginas ─────────────────

    public function testPageStylesAreNotHardcodedInHeader(): void
    {
        $header_path = dirname(__DIR__, 2) . '/views/layouts/header.php';
        $header_content = (string) file_get_contents($header_path);

        // El header no debe tener hardcoded el CSS de páginas específicas
        $this->assertStringNotContainsString('assets/css/pages/dashboard.css', $header_content, 'dashboard.css no debe estar hardcoded en header');
        $this->assertStringNotContainsString('assets/css/pages/rooms.css', $header_content, 'rooms.css no debe estar hardcoded en header');
        $this->assertStringNotContainsString('assets/css/pages/reservations.css', $header_content, 'reservations.css no debe estar hardcoded en header');
        $this->assertStringNotContainsString('assets/css/pages/guests.css', $header_content, 'guests.css no debe estar hardcoded en header');

        // Debe iterar page_styles dinámicamente
        $this->assertStringContainsString('page_styles', $header_content, 'Header debe iterar la variable $page_styles');
    }

    public function testCashboxPageDeclaresPageStylesNotInlineLink(): void
    {
        $page_path = dirname(__DIR__, 2) . '/views/pages/cashbox.php';
        $page_content = (string) file_get_contents($page_path);

        // No debe tener un <link> suelto en el cuerpo de la página
        $this->assertStringNotContainsString('<link rel="stylesheet"', $page_content, 'cashbox.php no debe tener <link> suelto; debe usar $page_styles');
    }

    // ─── Criterio 6: Desduplicación de componentes compartidos ────────

    public function testMetricCardComponentCssExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/assets/css/components/metric-card.css', 'Debe existir components/metric-card.css');
    }

    public function testTabBarComponentCssExists(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/assets/css/components/tab-bar.css', 'Debe existir components/tab-bar.css');
    }

    public function testMetricIconIsNotDuplicatedInPageCss(): void
    {
        $pages_with_dup = $this->findCssFilesContainingSelector('.metric-icon');
        $this->assertSame([], $pages_with_dup, 'No debe haber .metric-icon duplicado en pages/*.css; debe vivir en components/metric-card.css');
    }

    public function testPillGroupIsNotDuplicatedInReservationsCss(): void
    {
        $reservations_css = dirname(__DIR__, 2) . '/public/assets/css/pages/reservations.css';
        if (!file_exists($reservations_css)) {
            $this->markTestSkipped('reservations.css no existe');
        }
        $content = (string) file_get_contents($reservations_css);
        $this->assertStringNotContainsString('.pill-group', $content, 'pill-group no debe estar duplicado en reservations.css');
        $this->assertStringNotContainsString('.pill-option', $content, 'pill-option no debe estar duplicado en reservations.css');
    }

    // ─── Criterio 8: Sin estilos inline en layout ─────────────────────

    public function testHeaderHasNoInlineStyles(): void
    {
        $header_path = dirname(__DIR__, 2) . '/views/layouts/header.php';
        $content = (string) file_get_contents($header_path);

        $this->assertStringNotContainsString('style="', $content, 'header.php no debe tener estilos inline');
    }

    public function testSidebarHasNoInlineStyles(): void
    {
        $sidebar_path = dirname(__DIR__, 2) . '/views/layouts/sidebar.php';
        $content = (string) file_get_contents($sidebar_path);

        $this->assertStringNotContainsString('style="', $content, 'sidebar.php no debe tener estilos inline');
    }

    public function testFooterHasNoInlineStyles(): void
    {
        $footer_path = dirname(__DIR__, 2) . '/views/layouts/footer.php';
        $content = (string) file_get_contents($footer_path);

        $this->assertStringNotContainsString('style="', $content, 'footer.php no debe tener estilos inline');
    }

    // ─── Criterio 5: Cero clases inexistentes (verificación de archivos CSS) ─

    public function testLayoutNamespaceCssFilesExist(): void
    {
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/assets/css/layout/header.css');
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/assets/css/layout/nav.css');
        $this->assertFileExists(dirname(__DIR__, 2) . '/public/assets/css/layout/footer.css');
    }

    // ─── Helpers ───────────────────────────────────────────────────────

    private function renderDashboard(): string
    {
        ob_start();
        $this->view_controller->dashboard();
        return (string) ob_get_clean();
    }

    /**
     * @return list<string>
     */
    private function findCssFilesContainingSelector(string $selector): array
    {
        $dir = dirname(__DIR__, 2) . '/public/assets/css/pages/';
        $matches = [];
        foreach ((array) glob($dir . '*.css') as $file) {
            $content = (string) file_get_contents($file);
            if (str_contains($content, $selector)) {
                $matches[] = basename($file);
            }
        }
        return $matches;
    }

}
