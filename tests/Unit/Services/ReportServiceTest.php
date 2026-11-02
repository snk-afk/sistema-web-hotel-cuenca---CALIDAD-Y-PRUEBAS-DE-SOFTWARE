<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ReportService;
use PDO;
use PHPUnit\Framework\TestCase;

final class ReportServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->createTables();
    }

    private function createTables(): void
    {
        $this->pdo->exec('
            CREATE TABLE room_categories (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );

            CREATE TABLE rooms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                room_category_id INTEGER NOT NULL,
                number TEXT NOT NULL UNIQUE,
                status TEXT DEFAULT "free"
            );

            CREATE TABLE folio_payments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                amount REAL NOT NULL
            );

            CREATE TABLE store_sales (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                total_amount REAL NOT NULL
            );

            CREATE TABLE other_incomes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                amount REAL NOT NULL
            );

            CREATE TABLE expenses (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                amount REAL NOT NULL
            );
        ');

        // Populate sample data
        $this->pdo->exec('
            INSERT INTO rooms (room_category_id, number, status) VALUES (1, "101", "occupied");
            INSERT INTO rooms (room_category_id, number, status) VALUES (1, "102", "occupied");
            INSERT INTO rooms (room_category_id, number, status) VALUES (1, "103", "free");
            INSERT INTO rooms (room_category_id, number, status) VALUES (1, "104", "free");

            INSERT INTO folio_payments (amount) VALUES (200.00);
            INSERT INTO store_sales (total_amount) VALUES (50.00);
            INSERT INTO other_incomes (amount) VALUES (30.00);
            INSERT INTO expenses (amount) VALUES (40.00);
        ');
    }

    public function testOccupancyReportMetricsCalculation(): void
    {
        $service = new ReportService($this->pdo);

        $report = $service->getOccupancyReport();

        $this->assertEquals(4, $report['total_rooms']);
        $this->assertEquals(2, $report['occupied_rooms']);
        $this->assertEquals(50.0, $report['occupancy_rate']); // (2/4) * 100 = 50%
        $this->assertEquals(100.0, $report['adr']); // 200 / 2 = 100
        $this->assertEquals(50.0, $report['revpar']); // 200 / 4 = 50
    }

    public function testFinancialSummaryCalculations(): void
    {
        $service = new ReportService($this->pdo);

        $summary = $service->getFinancialSummary();

        $this->assertEquals(200.00, $summary['accommodation_income']);
        $this->assertEquals(50.00, $summary['store_income']);
        $this->assertEquals(30.00, $summary['other_income']);
        $this->assertEquals(280.00, $summary['total_income']); // 200 + 50 + 30
        $this->assertEquals(40.00, $summary['total_expenses']);
        $this->assertEquals(240.00, $summary['net_profit']); // 280 - 40
    }

    public function testExportFinancialCsvProducesStructuredReport(): void
    {
        $service = new ReportService($this->pdo);

        $csv = $service->exportFinancialCsv();

        $this->assertStringContainsString('HOTEL CS-HOTEL', $csv);
        $this->assertStringContainsString('1. METRICAS Y KPIs OPERATIVOS', $csv);
        $this->assertStringContainsString('2. ESTADO DE RESULTADOS OPERATIVOS', $csv);
        $this->assertStringContainsString('Tasa de Ocupacion Promedio', $csv);
        $this->assertStringContainsString('UTILIDAD OPERATIVA NETA FINAL', $csv);
        $this->assertStringContainsString('240.00', $csv);
    }
}
