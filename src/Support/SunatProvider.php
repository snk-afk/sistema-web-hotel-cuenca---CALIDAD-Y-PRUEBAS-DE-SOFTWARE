<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use JsonException;

final class SunatProvider
{
    private Closure $transport;
    private bool $custom_transport;

    /**
     * @param null|Closure(array<string, mixed>): array{status_code: int, body: string} $transport
     */
    public function __construct(?Closure $transport = null)
    {
        $this->custom_transport = $transport !== null;
        $this->transport = $transport ?? Closure::fromCallable($this->performHttpRequest(...));
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status: string, code: string, message: string}
     */
    public function sendDocument(array $payload): array
    {
        $mode = Config::string('sunat.mode', Config::string('SUNAT_MODE', 'simulated'));

        if ($mode === 'simulated') {
            return [
                'status' => 'simulated',
                'code' => '0000',
                'message' => 'Comprobante simulado en entorno local de pruebas',
            ];
        }

        if ($mode !== 'real') {
            return $this->failed('CONFIG', 'Modo SUNAT no válido.');
        }

        $endpoint = Config::string('sunat.endpoint', Config::string('SUNAT_ENDPOINT'));
        if (!$this->custom_transport && ($endpoint === '' || !str_starts_with($endpoint, 'https://'))) {
            return $this->failed('CONFIG', 'SUNAT no está configurado para producción.');
        }

        try {
            $raw_response = ($this->transport)($payload);
        } catch (\Throwable) {
            return $this->failed('TIMEOUT', 'No se pudo obtener respuesta de SUNAT.');
        }

        $status_code = $raw_response['status_code'] ?? 0;
        $body = $raw_response['body'] ?? '';
        if (!is_int($status_code) || !is_string($body) || $status_code < 200 || $status_code >= 300) {
            return $this->failed(
                $status_code === 0 ? 'TIMEOUT' : 'HTTP_' . (string) $status_code,
                'SUNAT no confirmó la recepción del comprobante.'
            );
        }

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            return $this->failed('MALFORMED', 'La respuesta de SUNAT no es válida.');
        }

        $status = $decoded['status'] ?? null;
        $code = $decoded['code'] ?? null;
        $message = $decoded['message'] ?? null;
        if (!is_string($status) || !is_string($code) || !is_string($message)) {
            return $this->failed('MALFORMED', 'La respuesta de SUNAT está incompleta.');
        }

        if ($status === 'accepted') {
            return [
                'status' => 'accepted',
                'code' => mb_substr($code, 0, 100),
                'message' => mb_substr($message, 0, 500),
            ];
        }

        if ($status === 'rejected') {
            return [
                'status' => 'rejected',
                'code' => mb_substr($code, 0, 100),
                'message' => mb_substr($message, 0, 500),
            ];
        }

        return $this->failed('MALFORMED', 'SUNAT devolvió un estado no reconocido.');
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{status_code: int, body: string}
     */
    private function performHttpRequest(array $payload): array
    {
        $endpoint = Config::string('sunat.endpoint', Config::string('SUNAT_ENDPOINT'));
        $timeout = max(1, Config::int('sunat.timeout_seconds', Config::int('SUNAT_TIMEOUT_SECONDS', 15)));
        $username = Config::string('sunat.sol_user', Config::string('SUNAT_SOL_USER'));
        $password = Config::string('sunat.sol_pass', Config::string('SUNAT_SOL_PASS'));

        try {
            $body = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ['status_code' => 0, 'body' => ''];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Basic ' . base64_encode($username . ':' . $password),
        ];
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $http_response_header = [];
        $response = @file_get_contents($endpoint, false, $context);
        $status_code = 0;
        foreach ($http_response_header as $header) {
            if (preg_match('/\AHTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                $status_code = (int) $matches[1];
                break;
            }
        }

        return [
            'status_code' => $status_code,
            'body' => is_string($response) ? $response : '',
        ];
    }

    /**
     * @return array{status: string, code: string, message: string}
     */
    private function failed(string $code, string $message): array
    {
        return [
            'status' => 'failed',
            'code' => $code,
            'message' => $message,
        ];
    }
}
