<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Pruebas de verificación de diseño visual para SPEC-0042.
 */
final class FrontendVisualDesignTest extends TestCase
{
    private string $baseCss;
    private string $modalCss;
    private string $headerCss;
    private string $navCss;

    protected function setUp(): void
    {
        parent::setUp();
        $this->baseCss = (string) file_get_contents(__DIR__ . '/../../public/assets/css/base.css');
        $this->modalCss = (string) file_get_contents(__DIR__ . '/../../public/assets/css/components/modal.css');
        $this->headerCss = (string) file_get_contents(__DIR__ . '/../../public/assets/css/layout/header.css');
        $this->navCss = (string) file_get_contents(__DIR__ . '/../../public/assets/css/layout/nav.css');
    }

    public function testBaseCssHasModernPaletteAndVariables(): void
    {
        $this->assertStringContainsString('--bg-dark: #0b0f19', $this->baseCss);
        $this->assertStringContainsString('--bg-card: #141c2e', $this->baseCss);
        $this->assertStringContainsString('--border-color:', $this->baseCss);
        $this->assertStringContainsString('--primary-color:', $this->baseCss);
        $this->assertStringContainsString('--radius-lg:', $this->baseCss);
    }

    public function testModalCssHasPolishedStylingAndBackdrop(): void
    {
        $this->assertStringContainsString('backdrop-filter: blur', $this->modalCss);
        $this->assertStringContainsString('modalScaleIn', $this->modalCss);
        $this->assertStringContainsString('border-radius:', $this->modalCss);
        $this->assertStringContainsString('.close', $this->modalCss);
    }

    public function testTablesHaveSpaciousPaddingAndModernHeaders(): void
    {
        $this->assertStringContainsString('.data-table', $this->baseCss);
        $this->assertStringContainsString('.table', $this->baseCss);
        $this->assertStringContainsString('.table-responsive', $this->baseCss);
    }

    public function testInputsHaveStandardHeightAndGlowFocus(): void
    {
        $this->assertStringContainsString('.form-control', $this->baseCss);
        $this->assertStringContainsString('.form-control:focus', $this->baseCss);
        $this->assertStringContainsString('.search-box', $this->baseCss);
    }

    public function testNavigationHasModernStylesAndTransitions(): void
    {
        $this->assertStringContainsString('.ns-nav__link', $this->navCss);
        $this->assertStringContainsString('.ns-header', $this->headerCss);
    }
}
