<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de verificación de diseño visual para SPEC-0043 (Dashboard Rack & Drawer).
 */
final class DashboardVisualRemasterTest extends TestCase
{
    private string $dashboardCss;
    private string $drawerCss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dashboardCss = (string) file_get_contents(__DIR__ . '/../../public/assets/css/pages/dashboard.css');
        $this->drawerCss = (string) file_get_contents(__DIR__ . '/../../public/assets/css/components/drawer.css');
    }

    public function testDashboardHeaderAndFilterPillsStyling(): void
    {
        $this->assertStringContainsString('.dashboard-header-bar', $this->dashboardCss);
        $this->assertStringContainsString('.rack-stats-pills', $this->dashboardCss);
        $this->assertStringContainsString('.stat-pill', $this->dashboardCss);
        $this->assertStringContainsString('.stat-pill:hover', $this->dashboardCss);
        $this->assertStringContainsString('.pill-overdue', $this->dashboardCss);
    }

    public function testRoomCardsStatusVariantsAndGradients(): void
    {
        $this->assertStringContainsString('.rooms-grid', $this->dashboardCss);
        $this->assertStringContainsString('.room-card', $this->dashboardCss);
        $this->assertStringContainsString('.room-card-free', $this->dashboardCss);
        $this->assertStringContainsString('.room-card-occupied', $this->dashboardCss);
        $this->assertStringContainsString('.room-card-overdue', $this->dashboardCss);
        $this->assertStringContainsString('.room-card-cleaning', $this->dashboardCss);
        $this->assertStringContainsString('.badge-status', $this->dashboardCss);
    }

    public function testDrawerPanelAndComponentsStyling(): void
    {
        $this->assertStringContainsString('.ns-drawer', $this->drawerCss);
        $this->assertStringContainsString('backdrop-filter: blur', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__panel', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__header', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__tabs', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__tab', $this->drawerCss);
    }
}
