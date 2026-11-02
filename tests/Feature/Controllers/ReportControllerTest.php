<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\ReportController;
use App\Services\ReportService;
use Tests\Support\MySqlTestCase;

final class ReportControllerTest extends MySqlTestCase
{
    private ReportService $report_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->report_service = new ReportService($this->pdo);
    }

    public function testOccupancyEndpointReturnsJson(): void
    {
        $controller = new ReportController($this->report_service);

        ob_start();
        $controller->occupancy();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(0, $data['data']['total_rooms']);
    }

    public function testFinancialEndpointReturnsJson(): void
    {
        $controller = new ReportController($this->report_service);

        ob_start();
        $controller->financial();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals(0.0, $data['data']['net_profit']);
    }
}
