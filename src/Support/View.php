<?php

declare(strict_types=1);

namespace App\Support;

final class View
{
    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template_name, array $data = []): void
    {
        extract($data);
        $file_path = __DIR__ . '/../../views/' . $template_name . '.php';

        if (!file_exists($file_path)) {
            http_response_code(404);
            echo "Vista '{$template_name}' no encontrada.";
            return;
        }

        require $file_path;
    }
}
