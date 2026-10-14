<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\HealthRepository;
use App\Support\Config;

final class HealthController
{
    private ?HealthRepository $health_repository;

    public function __construct(?HealthRepository $health_repository = null)
    {
        $this->health_repository = $health_repository;
    }

    public function live(): void
    {
        $this->respond(200, ['status' => 'ok']);
    }

    public function ready(): void
    {
        $configured_token = Config::string('HEALTH_TOKEN');
        $request_token = is_string($_SERVER['HTTP_X_HEALTH_TOKEN'] ?? null)
            ? $_SERVER['HTTP_X_HEALTH_TOKEN']
            : '';

        if ($configured_token === '' || $request_token === '' || !hash_equals($configured_token, $request_token)) {
            $this->respond(401, ['status' => 'error', 'message' => 'No autorizado.']);
            return;
        }

        try {
            $checks = ($this->health_repository ??= new HealthRepository())->check();
        } catch (\Throwable) {
            $this->respond(503, ['status' => 'not_ready']);
            return;
        }

        $ready = !in_array(false, $checks, true);
        $this->respond($ready ? 200 : 503, [
            'status' => $ready ? 'ok' : 'not_ready',
            'checks' => $checks,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function respond(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
    }
}
