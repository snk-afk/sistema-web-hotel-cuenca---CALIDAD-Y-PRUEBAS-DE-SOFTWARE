<?php

declare(strict_types=1);

namespace App\Support;

final class DocumentLookup
{
    /**
     * @return array{first_name: ?string, last_name: ?string, company_name: ?string, address: ?string}|null
     */
    public function lookup(string $document_type, string $document_number): ?array
    {
        $enabled = Config::bool('services.jsonpe.enabled', false);
        if (!$enabled) {
            return null;
        }

        $token = Config::string('services.jsonpe.api_token', '');
        $base_url = Config::string('services.jsonpe.base_url', 'https://api.jsonpe.com');

        if (trim($token) === '' || trim($base_url) === '') {
            return null;
        }

        $endpoint = strtolower($document_type) === 'ruc'
            ? "{$base_url}/ruc/{$document_number}?token={$token}"
            : "{$base_url}/dni/{$document_number}?token={$token}";

        $ch = curl_init();
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        /** @var string|false $response */
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $http_code !== 200) {
            return null;
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return null;
        }

        if (strtolower($document_type) === 'ruc') {
            return [
                'first_name' => null,
                'last_name' => null,
                'company_name' => is_string($data['razonSocial'] ?? null) ? $data['razonSocial'] : null,
                'address' => is_string($data['direccion'] ?? null) ? $data['direccion'] : null,
            ];
        }

        return [
            'first_name' => is_string($data['nombres'] ?? null) ? $data['nombres'] : null,
            'last_name' => trim(sprintf('%s %s', is_string($data['apellidoPaterno'] ?? null) ? $data['apellidoPaterno'] : '', is_string($data['apellidoMaterno'] ?? null) ? $data['apellidoMaterno'] : '')),
            'company_name' => null,
            'address' => is_string($data['direccion'] ?? null) ? $data['direccion'] : null,
        ];
    }
}
