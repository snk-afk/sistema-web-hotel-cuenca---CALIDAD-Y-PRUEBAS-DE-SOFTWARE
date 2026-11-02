<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de verificación para SPEC-0044 (Drawer con namespace exclusivo ns-drawer).
 */
final class RoomDrawerRedesignTest extends TestCase
{
    private string $drawerCss;
    private string $roomDrawerPhp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->drawerCss = (string) file_get_contents(__DIR__ . '/../../public/assets/css/components/drawer.css');
        $this->roomDrawerPhp = (string) file_get_contents(__DIR__ . '/../../views/components/room_drawer.php');
    }

    public function testDrawerCssHasExclusiveNamespace(): void
    {
        $this->assertStringContainsString('.ns-drawer', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__panel', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__header', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__tabs', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__tab', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__body', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__card', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__input', $this->drawerCss);
        $this->assertStringContainsString('.ns-drawer__btn', $this->drawerCss);
    }

    public function testRoomDrawerPhpUsesExclusiveNamespaceClasses(): void
    {
        $this->assertStringContainsString('ns-drawer', $this->roomDrawerPhp);
        $this->assertStringContainsString('ns-drawer__panel', $this->roomDrawerPhp);
        $this->assertStringContainsString('ns-drawer__header', $this->roomDrawerPhp);
        $this->assertStringContainsString('ns-drawer__tab', $this->roomDrawerPhp);
        $this->assertStringContainsString('id="room-drawer-overlay"', $this->roomDrawerPhp);
        $this->assertStringContainsString('id="drawer-room-number"', $this->roomDrawerPhp);
    }
}
