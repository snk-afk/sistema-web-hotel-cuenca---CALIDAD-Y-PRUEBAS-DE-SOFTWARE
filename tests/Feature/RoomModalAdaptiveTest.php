<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pruebas para SPEC-0045 (Modal Adaptativo Panorámico de Habitación).
 */
final class RoomModalAdaptiveTest extends TestCase
{
    private string $roomModalCss;
    private string $roomModalPhp;
    private string $dashboardPhp;
    private string $headerPhp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->roomModalCss = (string) file_get_contents(__DIR__ . '/../../public/assets/css/components/room-modal.css');
        $this->roomModalPhp = (string) file_get_contents(__DIR__ . '/../../views/components/room_modal.php');
        $this->dashboardPhp = (string) file_get_contents(__DIR__ . '/../../views/pages/dashboard.php');
        $this->headerPhp = (string) file_get_contents(__DIR__ . '/../../views/layouts/header.php');
    }

    public function testRoomModalCssStructureAndGrid(): void
    {
        $this->assertStringContainsString('.ns-room-modal', $this->roomModalCss);
        $this->assertStringContainsString('.ns-room-modal__dialog', $this->roomModalCss);
        $this->assertStringContainsString('.ns-room-modal__tabs', $this->roomModalCss);
        $this->assertStringContainsString('.ns-room-modal__tab', $this->roomModalCss);
        $this->assertStringContainsString('.ns-room-modal__body', $this->roomModalCss);
        $this->assertStringContainsString('.ns-room-modal__header', $this->roomModalCss);
        $this->assertStringContainsString('.ns-room-modal__btn', $this->roomModalCss);
    }

    public function testRoomModalPhpStructure(): void
    {
        $this->assertStringContainsString('id="room-drawer-overlay"', $this->roomModalPhp);
        $this->assertStringContainsString('id="drawer-room-number"', $this->roomModalPhp);
        $this->assertStringContainsString('ns-room-modal__tabs', $this->roomModalPhp);
        $this->assertStringContainsString('ns-room-modal__body', $this->roomModalPhp);
    }

    public function testDashboardAndHeaderIncludeModal(): void
    {
        $this->assertStringContainsString('room_modal.php', $this->dashboardPhp);
        $this->assertStringContainsString('room-modal.css', $this->headerPhp);
    }

    public function testDashboardFutureReservationBannerAndCalendarTab(): void
    {
        // Validación de elementos de SPEC-0046
        $this->assertStringContainsString('tab-btn-room-calendar', $this->roomModalPhp);
        $this->assertStringContainsString('drawer-room-calendar-list', $this->roomModalPhp);
        $this->assertStringContainsString('tab-context-reserved', $this->roomModalPhp);
        $this->assertStringContainsString('badge-danger', $this->dashboardPhp);
        $this->assertStringContainsString('⚠️ AVERÍA', $this->dashboardPhp);
        $this->assertStringContainsString('📅 PROX. RESERVA', $this->dashboardPhp);
    }

    public function testInteractiveCalendarGridAndNavigation(): void
    {
        // Validación de elementos de SPEC-0047/0048
        $this->assertStringContainsString('drawer-fullcalendar-mount', $this->roomModalPhp);
        $this->assertStringContainsString('calendar-day-details-panel', $this->roomModalPhp);
        $this->assertStringContainsString('drawer-room-calendar-list', $this->roomModalPhp);
    }

    public function testFullCalendarBundleAndMountIntegration(): void
    {
        $footerPhp = (string) file_get_contents(__DIR__ . '/../../views/layouts/footer.php');
        $this->assertStringContainsString('/assets/vendor/fullcalendar/index.global.min.js', $footerPhp);
        $this->assertStringContainsString('/assets/vendor/fullcalendar/es.global.min.js', $footerPhp);
        $this->assertFileExists(__DIR__ . '/../../public/assets/vendor/fullcalendar/index.global.min.js');
        $this->assertFileExists(__DIR__ . '/../../public/assets/vendor/fullcalendar/es.global.min.js');
    }
}
