<?php

declare(strict_types=1);

namespace App\Services;

use App\Database\Database;
use App\Support\CsvExporter;
use PDO;

final class ReportService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * @return array{total_rooms: int, occupied_rooms: int, occupancy_rate: float, adr: float, revpar: float, total_accommodation_income: float}
     */
    public function getOccupancyReport(?string $from_date = null, ?string $to_date = null): array
    {
        $total_stmt = $this->db->query('SELECT COUNT(*) FROM rooms');
        /** @var int|string $total_rooms_count */
        $total_rooms_count = $total_stmt !== false ? $total_stmt->fetchColumn() : 0;
        $total_rooms = (int) $total_rooms_count;

        $occupied_stmt = $this->db->query('SELECT COUNT(*) FROM rooms WHERE status = "occupied"');
        /** @var int|string $occupied_rooms_count */
        $occupied_rooms_count = $occupied_stmt !== false ? $occupied_stmt->fetchColumn() : 0;
        $occupied_rooms = (int) $occupied_rooms_count;

        $occupancy_rate = $total_rooms > 0 ? round(($occupied_rooms / $total_rooms) * 100, 2) : 0.0;

        $payments_stmt = $this->db->query('SELECT COALESCE(SUM(amount), 0.0) FROM folio_payments');
        /** @var float|string $income_raw */
        $income_raw = $payments_stmt !== false ? $payments_stmt->fetchColumn() : 0.0;
        $total_accommodation_income = (float) $income_raw;

        $adr = $occupied_rooms > 0 ? round($total_accommodation_income / $occupied_rooms, 2) : 0.0;
        $revpar = $total_rooms > 0 ? round($total_accommodation_income / $total_rooms, 2) : 0.0;

        return [
            'total_rooms' => $total_rooms,
            'occupied_rooms' => $occupied_rooms,
            'occupancy_rate' => $occupancy_rate,
            'adr' => $adr,
            'revpar' => $revpar,
            'total_accommodation_income' => $total_accommodation_income,
        ];
    }

    /**
     * @return array{accommodation_income: float, store_income: float, other_income: float, total_income: float, total_expenses: float, net_profit: float}
     */
    public function getFinancialSummary(?string $from_date = null, ?string $to_date = null): array
    {
        $acc_stmt = $this->db->query('SELECT COALESCE(SUM(amount), 0.0) FROM folio_payments');
        /** @var float|string $acc_raw */
        $acc_raw = $acc_stmt !== false ? $acc_stmt->fetchColumn() : 0.0;
        $accommodation_income = (float) $acc_raw;

        $store_stmt = $this->db->query('SELECT COALESCE(SUM(total_amount), 0.0) FROM store_sales');
        /** @var float|string $store_raw */
        $store_raw = $store_stmt !== false ? $store_stmt->fetchColumn() : 0.0;
        $store_income = (float) $store_raw;

        $other_stmt = $this->db->query('SELECT COALESCE(SUM(amount), 0.0) FROM other_incomes');
        /** @var float|string $other_raw */
        $other_raw = $other_stmt !== false ? $other_stmt->fetchColumn() : 0.0;
        $other_income = (float) $other_raw;

        $exp_stmt = $this->db->query('SELECT COALESCE(SUM(amount), 0.0) FROM expenses');
        /** @var float|string $exp_raw */
        $exp_raw = $exp_stmt !== false ? $exp_stmt->fetchColumn() : 0.0;
        $total_expenses = (float) $exp_raw;

        $total_income = round($accommodation_income + $store_income + $other_income, 2);
        $net_profit = round($total_income - $total_expenses, 2);

        return [
            'accommodation_income' => round($accommodation_income, 2),
            'store_income' => round($store_income, 2),
            'other_income' => round($other_income, 2),
            'total_income' => $total_income,
            'total_expenses' => round($total_expenses, 2),
            'net_profit' => $net_profit,
        ];
    }

    public function exportFinancialCsv(?string $from_date = null, ?string $to_date = null): string
    {
        $occ = $this->getOccupancyReport($from_date, $to_date);
        $fin = $this->getFinancialSummary($from_date, $to_date);

        $period = is_string($from_date) && is_string($to_date) ? "Del {$from_date} al {$to_date}" : 'Historico Completo';
        $margin = $fin['total_income'] > 0 ? number_format(($fin['net_profit'] / $fin['total_income']) * 100, 1) : '0.0';

        $headers = ['SECCION / CONCEPTO', 'DETALLE / TIPO', 'VALOR / MONTO (S/)'];
        $rows = [
            ['HOTEL CS-HOTEL', 'RUC: 20608912345', 'INFORME FINANCIERO Y AUDITORIA'],
            ['Periodo Evaluado', $period, 'Generado: ' . date('Y-m-d H:i:s')],
            ['----------------------------------------', '----------------------------------------', '----------------------------------------'],
            ['1. METRICAS Y KPIs OPERATIVOS', '', ''],
            ['Tasa de Ocupacion Promedio', "{$occ['occupied_rooms']} de {$occ['total_rooms']} habitaciones", number_format($occ['occupancy_rate'], 1) . ' %'],
            ['ADR (Tarifa Promedio Diaria)', 'Ingreso prom. por hab. ocupada', 'S/ ' . number_format($occ['adr'], 2)],
            ['RevPAR (Ingreso por Hab. Disponible)', 'Rendimiento sobre capacidad instalada', 'S/ ' . number_format($occ['revpar'], 2)],
            ['Margen Operativo Neto', 'Rentabilidad sobre ventas brutas', "{$margin} %"],
            ['----------------------------------------', '----------------------------------------', '----------------------------------------'],
            ['2. ESTADO DE RESULTADOS OPERATIVOS', '', ''],
            ['(+) Ingresos por Alojamiento / Hospedaje', 'Ingreso Operativo Principal', number_format($fin['accommodation_income'], 2, '.', '')],
            ['(+) Ingresos por Tienda POS / Minibar', 'Ingreso Operativo Comercial', number_format($fin['store_income'], 2, '.', '')],
            ['(+) Otros Ingresos (Cocheras, Lavanderia)', 'Ingreso No Operativo / Servicios', number_format($fin['other_income'], 2, '.', '')],
            ['(=) TOTAL INGRESOS OPERATIVOS BRUTOS', 'Consolidado General de Ingresos', number_format($fin['total_income'], 2, '.', '')],
            ['(-) Egresos y Gastos de Caja Chica', 'Gastos Operativos de Turno', '-' . number_format($fin['total_expenses'], 2, '.', '')],
            ['----------------------------------------', '----------------------------------------', '----------------------------------------'],
            ['(==) UTILIDAD OPERATIVA NETA FINAL', "Margen: {$margin} %", number_format($fin['net_profit'], 2, '.', '')],
        ];

        return CsvExporter::generateCsv($headers, $rows);
    }
}
