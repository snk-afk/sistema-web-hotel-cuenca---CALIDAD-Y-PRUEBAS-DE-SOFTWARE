<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Support\StorageProvider;
use RuntimeException;

final class StorageController
{
    private string $storage_root;
    private StorageProvider $storage_provider;

    public function __construct(?string $storage_root = null, ?StorageProvider $storage_provider = null)
    {
        $this->storage_root = rtrim(
            $storage_root ?? dirname(__DIR__, 2) . '/storage/comprobantes',
            DIRECTORY_SEPARATOR
        );
        $this->storage_provider = $storage_provider ?? new StorageProvider($this->storage_root);
    }

    public function download(string $file): void
    {
        if (preg_match('/\A[A-Za-z0-9]+(?:-[A-Za-z0-9]+)*(?:_voucher)?\.(?:pdf|xml)\z/', $file) !== 1) {
            $this->respondJson(400, 'Nombre de comprobante inválido.');
            return;
        }

        try {
            $file_data = $this->storage_provider->readInvoiceFile($file);
        } catch (RuntimeException) {
            $this->respondJson(400, 'Nombre de comprobante inválido.');
            return;
        }

        if ($file_data === null) {
            $this->respondJson(404, 'Comprobante no encontrado. El archivo no existe.');
            return;
        }

        header('Content-Type: ' . $file_data['content_type']);
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        echo $file_data['content'];
    }

    private function respondJson(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $message,
        ]);
    }
}
