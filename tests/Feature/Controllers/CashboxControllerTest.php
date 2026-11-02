<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\CashboxController;
use App\Services\CashboxService;
use Tests\Support\MySqlTestCase;

final class CashboxControllerTest extends MySqlTestCase
{
    private CashboxService $cashbox_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUserFixture(1, 'cashbox-one');
        $this->createUserFixture(2, 'cashbox-two');
        $this->createUserFixture(3, 'cashbox-three');
        $this->cashbox_service = new CashboxService($this->pdo);

        // Simulate an authenticated session for endpoints that call
        // Session::requireAuth(). The session must be started BEFORE
        // setting $_SESSION, otherwise Session::ensureStarted() resets it.
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
        $_GET = [];
        parent::tearDown();
    }

    public function testCurrentReturnsNullWhenNoActiveShift(): void
    {
        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->current();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertNull($data['data']);
    }

    public function testHistoryReturnsListOfShifts(): void
    {
        $this->cashbox_service->openShift(user_id: 1, initial_cash: 100.00);
        $this->cashbox_service->openShift(user_id: 2, initial_cash: 200.00);

        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->history();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertCount(2, $data['data']);
        // Ordered by id DESC
        $this->assertEquals(2, $data['data'][0]['user_id']);
        $this->assertEquals(1, $data['data'][1]['user_id']);
        // Listado NO incluye movimientos
        $this->assertArrayNotHasKey('movements', $data['data'][0]);
    }

    public function testHistoryFiltersByStatus(): void
    {
        $this->cashbox_service->openShift(user_id: 1, initial_cash: 100.00);
        $this->cashbox_service->openShift(user_id: 2, initial_cash: 200.00);
        $this->cashbox_service->closeShift(user_id: 2, real_cash_declared: 200.00);

        $_GET['status'] = 'closed';
        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->history();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertCount(1, $data['data']);
        $this->assertEquals('closed', $data['data'][0]['status']);
    }

    public function testShowReturnsShiftDetailWithMovements(): void
    {
        $shift = $this->cashbox_service->openShift(user_id: 1, initial_cash: 100.00);
        $this->cashbox_service->recordMovement(
            user_id: 1,
            type: 'income',
            category: 'accommodation',
            concept: 'Cobro Hab 102',
            amount: 50.00,
            payment_method: 'cash'
        );

        $_GET['id'] = (string) $shift->id;
        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->show();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertEquals('success', $data['status']);
        $this->assertEquals($shift->id, $data['data']['id']);
        $this->assertCount(1, $data['data']['movements']);
    }

    public function testShowReturns404WhenNotFound(): void
    {
        $_GET['id'] = '999';
        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->show();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertEquals('error', $data['status']);
        $this->assertEquals('Turno de caja no encontrado.', $data['message']);
        $this->assertEquals(404, http_response_code());
    }

    public function testShowReturns400WhenIdMissing(): void
    {
        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->show();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertEquals('error', $data['status']);
        $this->assertEquals('El parámetro id es obligatorio.', $data['message']);
    }

    // ─── EXPORT CSV (CA-10, CA-11) ────────────────────────────

    public function testExportCsvReturnsValidCsvWithBom(): void
    {
        $this->cashbox_service->openShift(user_id: 1, initial_cash: 100.00);
        $this->cashbox_service->openShift(user_id: 2, initial_cash: 200.00);
        $this->cashbox_service->openShift(user_id: 3, initial_cash: 50.00);

        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->exportCsv();
        $output = (string) ob_get_clean();

        // UTF-8 BOM at the start for Excel compatibility
        $this->assertStringStartsWith("\xEF\xBB\xBF", $output);
        // Header row present
        $this->assertStringContainsString('ID', $output);
        $this->assertStringContainsString('Responsable (user_id)', $output);
        $this->assertStringContainsString('Fondo Inicial', $output);
        $this->assertStringContainsString('Estado', $output);
        // 3 data rows + 1 header row = 4 lines (excluding BOM line)
        $line_count = substr_count($output, "\n");
        $this->assertSame(4, $line_count);
    }

    public function testExportCsvAppliesFilters(): void
    {
        $this->cashbox_service->openShift(user_id: 1, initial_cash: 100.00);
        $this->cashbox_service->openShift(user_id: 2, initial_cash: 200.00);
        $this->cashbox_service->closeShift(user_id: 2, real_cash_declared: 200.00);

        $_GET['status'] = 'closed';
        $_GET['user_id'] = '2';
        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->exportCsv();
        $output = (string) ob_get_clean();

        // Only 1 data row + 1 header row = 2 lines
        $line_count = substr_count($output, "\n");
        $this->assertSame(2, $line_count);
        $this->assertStringContainsString('closed', $output);
        $this->assertStringNotContainsString(',1,', $output);
    }

    // ─── EXPORT PDF (CA-12, CA-13) ────────────────────────────

    public function testExportPdfReturnsValidPdfWithShifts(): void
    {
        $this->cashbox_service->openShift(user_id: 1, initial_cash: 100.00);
        $this->cashbox_service->openShift(user_id: 2, initial_cash: 200.00);

        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->exportPdf();
        $output = (string) ob_get_clean();

        $this->assertStringStartsWith('%PDF-1.4', $output);
        $this->assertStringContainsString('%%EOF', $output);
        $this->assertStringContainsString('Historial de Turnos de Caja', $output);
    }

    public function testExportPdfReturnsValidPdfWhenEmpty(): void
    {
        // No shifts in DB, filters produce empty result
        $_GET['from'] = '2030-01-01';
        $controller = new CashboxController($this->cashbox_service);

        ob_start();
        $controller->exportPdf();
        $output = (string) ob_get_clean();

        $this->assertStringStartsWith('%PDF-1.4', $output);
        $this->assertStringContainsString('%%EOF', $output);
        $this->assertStringContainsString('No hay turnos', $output);
    }
}
