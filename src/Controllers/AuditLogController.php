<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuditLogService;

final class AuditLogController
{
    private AuditLogService $audit_service;

    public function __construct(?AuditLogService $audit_service = null)
    {
        $this->audit_service = $audit_service ?? new AuditLogService();
    }

    public function index(): void
    {
        $logs = $this->audit_service->getLogs();

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $logs,
        ]);
    }
}
