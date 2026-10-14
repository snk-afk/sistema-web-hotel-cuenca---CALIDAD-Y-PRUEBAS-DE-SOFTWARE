<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use RuntimeException;

final class StorageProvider
{
    private string $root_path;
    private Closure $uploader;
    private Closure $downloader;

    /**
     * @param null|Closure(string, string, string): bool $uploader
     */
    public function __construct(
        ?string $root_path = null,
        ?Closure $uploader = null,
        ?Closure $downloader = null,
    )
    {
        $this->root_path = rtrim(
            $root_path ?? Config::string(
                'storage.comprobantes_path',
                dirname(__DIR__, 2) . '/storage/comprobantes',
            ),
            '/',
        );
        $this->uploader = $uploader ?? Closure::fromCallable($this->uploadToR2(...));
        $this->downloader = $downloader ?? Closure::fromCallable($this->downloadFromR2(...));
    }

    /**
     * @return array{pdf_url: string, xml_url: string, voucher_url: string}
     */
    public function storeInvoiceFiles(string $full_number, string $pdf_content, string $xml_content, string $voucher_content): array
    {
        if (preg_match('/\A[A-Z0-9]+-[0-9]{8}\z/', $full_number) !== 1) {
            throw new RuntimeException('Identificador de comprobante inválido.');
        }

        $provider = Config::string(
            'storage.driver',
            Config::string('STORAGE_DRIVER', Config::string('STORAGE_PROVIDER', 'local')),
        );

        if ($provider === 'r2') {
            $objects = [
                [$full_number . '.pdf', $pdf_content, 'application/pdf'],
                [$full_number . '.xml', $xml_content, 'application/xml'],
                [$full_number . '_voucher.pdf', $voucher_content, 'application/pdf'],
            ];
            foreach ($objects as [$key, $content, $content_type]) {
                if (!($this->uploader)($key, $content, $content_type)) {
                    throw new RuntimeException('No se pudo confirmar el almacenamiento del comprobante.');
                }
            }

            return [
                'pdf_url' => "/storage/comprobantes/{$full_number}.pdf",
                'xml_url' => "/storage/comprobantes/{$full_number}.xml",
                'voucher_url' => "/storage/comprobantes/{$full_number}_voucher.pdf",
            ];
        }

        $files = [
            [$full_number . '.pdf', $pdf_content],
            [$full_number . '.xml', $xml_content],
            [$full_number . '_voucher.pdf', $voucher_content],
        ];
        $written_paths = [];
        try {
            if (!is_dir($this->root_path) && !mkdir($this->root_path, 0750, true) && !is_dir($this->root_path)) {
                throw new RuntimeException('No se pudo preparar el almacenamiento local.');
            }

            foreach ($files as [$filename, $content]) {
                $target = $this->root_path . '/' . $filename;
                $temporary = tempnam($this->root_path, '.upload-');
                if ($temporary === false || file_put_contents($temporary, $content) !== strlen($content)) {
                    throw new RuntimeException('No se pudo escribir un archivo de comprobante.');
                }
                chmod($temporary, 0640);
                if (!rename($temporary, $target)) {
                    @unlink($temporary);
                    throw new RuntimeException('No se pudo confirmar un archivo de comprobante.');
                }
                $written_paths[] = $target;
            }
        } catch (\Throwable $e) {
            foreach ($written_paths as $written_path) {
                @unlink($written_path);
            }
            throw $e;
        }

        return [
            'pdf_url' => "/storage/comprobantes/{$full_number}.pdf",
            'xml_url' => "/storage/comprobantes/{$full_number}.xml",
            'voucher_url' => "/storage/comprobantes/{$full_number}_voucher.pdf",
        ];
    }

    /**
     * @return null|array{content: string, content_type: string}
     */
    public function readInvoiceFile(string $file): ?array
    {
        if (preg_match('/\A[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*(?:_voucher)?\.(?:pdf|xml)\z/', $file) !== 1) {
            throw new RuntimeException('Nombre de comprobante inválido.');
        }

        $provider = Config::string(
            'storage.driver',
            Config::string('STORAGE_DRIVER', Config::string('STORAGE_PROVIDER', 'local')),
        );

        if ($provider === 'r2') {
            /** @var null|array{content: string, content_type: string} $result */
            $result = ($this->downloader)($file);
            return $result;
        }

        $root = realpath($this->root_path);
        if ($root === false || !is_dir($root)) {
            return null;
        }

        $requested_path = $root . DIRECTORY_SEPARATOR . $file;
        if (is_link($requested_path)) {
            return null;
        }

        $real_file = realpath($requested_path);
        $root_prefix = $root . DIRECTORY_SEPARATOR;
        if ($real_file === false || !is_file($real_file) || !str_starts_with($real_file, $root_prefix)) {
            return null;
        }

        $extension = strtolower(pathinfo($real_file, PATHINFO_EXTENSION));
        $content = file_get_contents($real_file);
        if ($content === false) {
            return null;
        }

        return [
            'content' => $content,
            'content_type' => $extension === 'xml' ? 'application/xml' : 'application/pdf',
        ];
    }

    private function uploadToR2(string $key, string $content, string $content_type): bool
    {
        $account_id = Config::string('storage.r2.account_id', Config::string('R2_ACCOUNT_ID'));
        $access_key = Config::string('storage.r2.access_key_id', Config::string('R2_ACCESS_KEY_ID'));
        $secret_key = Config::string('storage.r2.secret_access_key', Config::string('R2_SECRET_ACCESS_KEY'));
        $bucket = Config::string('storage.r2.bucket_name', Config::string('R2_BUCKET_NAME'));
        $timeout = max(1, Config::int('STORAGE_TIMEOUT_SECONDS', 15));

        if ($account_id === '' || $access_key === '' || $secret_key === '' || $bucket === '') {
            return false;
        }

        $region = 'auto';
        $service = 's3';
        $amz_date = gmdate('Ymd\THis\Z');
        $date = substr($amz_date, 0, 8);
        $payload_hash = hash('sha256', $content);
        $canonical_uri = '/' . rawurlencode($bucket) . '/' . implode(
            '/',
            array_map('rawurlencode', explode('/', 'comprobantes/' . $key)),
        );
        $host = $account_id . '.r2.cloudflarestorage.com';
        $canonical_headers = 'content-type:' . $content_type . "\n"
            . 'host:' . $host . "\n"
            . 'x-amz-content-sha256:' . $payload_hash . "\n"
            . 'x-amz-date:' . $amz_date . "\n";
        $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';
        $canonical_request = "PUT\n{$canonical_uri}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
        $scope = "{$date}/{$region}/{$service}/aws4_request";
        $string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$scope}\n" . hash('sha256', $canonical_request);
        $date_key = hash_hmac('sha256', $date, 'AWS4' . $secret_key, true);
        $region_key = hash_hmac('sha256', $region, $date_key, true);
        $service_key = hash_hmac('sha256', $service, $region_key, true);
        $signing_key = hash_hmac('sha256', 'aws4_request', $service_key, true);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $access_key . "/{$scope}, "
            . 'SignedHeaders=' . $signed_headers . ', Signature=' . $signature;

        $context = stream_context_create([
            'http' => [
                'method' => 'PUT',
                'header' => implode("\r\n", [
                    'Host: ' . $host,
                    'Content-Type: ' . $content_type,
                    'x-amz-content-sha256: ' . $payload_hash,
                    'x-amz-date: ' . $amz_date,
                    'Authorization: ' . $authorization,
                ]),
                'content' => $content,
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
        $response = @file_get_contents('https://' . $host . $canonical_uri, false, $context);

        foreach ($http_response_header as $header) {
            if (preg_match('/\AHTTP\/\S+\s+(\d{3})/', $header, $matches) === 1) {
                return (int) $matches[1] >= 200 && (int) $matches[1] < 300;
            }
        }

        return false;
    }

    /**
     * @return null|array{content: string, content_type: string}
     */
    private function downloadFromR2(string $file): ?array
    {
        $account_id = Config::string('storage.r2.account_id', Config::string('R2_ACCOUNT_ID'));
        $access_key = Config::string('storage.r2.access_key_id', Config::string('R2_ACCESS_KEY_ID'));
        $secret_key = Config::string('storage.r2.secret_access_key', Config::string('R2_SECRET_ACCESS_KEY'));
        $bucket = Config::string('storage.r2.bucket_name', Config::string('R2_BUCKET_NAME'));
        $timeout = max(1, Config::int('STORAGE_TIMEOUT_SECONDS', 15));

        if ($account_id === '' || $access_key === '' || $secret_key === '' || $bucket === '') {
            return null;
        }

        $region = 'auto';
        $service = 's3';
        $amz_date = gmdate('Ymd\\THis\\Z');
        $date = substr($amz_date, 0, 8);
        $payload_hash = hash('sha256', '');
        $canonical_uri = '/' . rawurlencode($bucket) . '/' . implode(
            '/',
            array_map('rawurlencode', explode('/', 'comprobantes/' . $file)),
        );
        $host = $account_id . '.r2.cloudflarestorage.com';
        $canonical_headers = 'host:' . $host . "\n"
            . 'x-amz-content-sha256:' . $payload_hash . "\n"
            . 'x-amz-date:' . $amz_date . "\n";
        $signed_headers = 'host;x-amz-content-sha256;x-amz-date';
        $canonical_request = "GET\n{$canonical_uri}\n\n{$canonical_headers}\n{$signed_headers}\n{$payload_hash}";
        $scope = "{$date}/{$region}/{$service}/aws4_request";
        $string_to_sign = "AWS4-HMAC-SHA256\n{$amz_date}\n{$scope}\n" . hash('sha256', $canonical_request);
        $date_key = hash_hmac('sha256', $date, 'AWS4' . $secret_key, true);
        $region_key = hash_hmac('sha256', $region, $date_key, true);
        $service_key = hash_hmac('sha256', $service, $region_key, true);
        $signing_key = hash_hmac('sha256', 'aws4_request', $service_key, true);
        $signature = hash_hmac('sha256', $string_to_sign, $signing_key);
        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $access_key . "/{$scope}, "
            . 'SignedHeaders=' . $signed_headers . ', Signature=' . $signature;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Host: ' . $host,
                    'x-amz-content-sha256: ' . $payload_hash,
                    'x-amz-date: ' . $amz_date,
                    'Authorization: ' . $authorization,
                ]),
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
        $response = @file_get_contents('https://' . $host . $canonical_uri, false, $context);

        foreach ($http_response_header as $header) {
            if (preg_match('/\AHTTP\/\S+\s+(\d{3})/', $header, $matches) !== 1) {
                continue;
            }

            if ((int) $matches[1] < 200 || (int) $matches[1] >= 300 || $response === false) {
                return null;
            }

            return [
                'content' => $response,
                'content_type' => strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'xml'
                    ? 'application/xml'
                    : 'application/pdf',
            ];
        }

        return null;
    }
}
