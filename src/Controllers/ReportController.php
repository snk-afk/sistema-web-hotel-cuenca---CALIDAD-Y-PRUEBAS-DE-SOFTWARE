<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ReportService;

final class ReportController
{
    private ReportService $report_service;

    public function __construct(?ReportService $report_service = null)
    {
        $this->report_service = $report_service ?? new ReportService();
    }

    public function occupancy(): void
    {
        $from_date = is_string($_GET['from'] ?? null) ? $_GET['from'] : null;
        $to_date = is_string($_GET['to'] ?? null) ? $_GET['to'] : null;

        $report = $this->report_service->getOccupancyReport($from_date, $to_date);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    public function financial(): void
    {
        $from_date = is_string($_GET['from'] ?? null) ? $_GET['from'] : null;
        $to_date = is_string($_GET['to'] ?? null) ? $_GET['to'] : null;

        $report = $this->report_service->getFinancialSummary($from_date, $to_date);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $report,
        ]);
    }

    public function exportCsv(): void
    {
        $from_date = is_string($_GET['from'] ?? null) ? $_GET['from'] : null;
        $to_date = is_string($_GET['to'] ?? null) ? $_GET['to'] : null;

        $csv = $this->report_service->exportFinancialCsv($from_date, $to_date);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="reporte_financiero.csv"');
        echo $csv;
    }

    public function exportPdf(): void
    {
        $from_date = is_string($_GET['from'] ?? null) ? $_GET['from'] : null;
        $to_date = is_string($_GET['to'] ?? null) ? $_GET['to'] : null;

        $occ = $this->report_service->getOccupancyReport($from_date, $to_date);
        $fin = $this->report_service->getFinancialSummary($from_date, $to_date);

        $label = is_string($from_date) && is_string($to_date) ? "Del {$from_date} al {$to_date}" : 'Historico Completo';

        $pdf_content = \App\Support\PdfGenerator::generateExecutiveReportPdf([
            'period_label' => $label,
            'generated_at' => date('Y-m-d H:i:s'),
            'occupancy_rate' => $occ['occupancy_rate'],
            'occupied_rooms' => $occ['occupied_rooms'],
            'total_rooms' => $occ['total_rooms'],
            'adr' => $occ['adr'],
            'revpar' => $occ['revpar'],
            'accommodation_income' => $fin['accommodation_income'],
            'store_income' => $fin['store_income'],
            'other_income' => $fin['other_income'],
            'total_income' => $fin['total_income'],
            'total_expenses' => $fin['total_expenses'],
            'net_profit' => $fin['net_profit'],
        ]);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="informe_ejecutivo_hotel.pdf"');
        echo $pdf_content;
    }
}
