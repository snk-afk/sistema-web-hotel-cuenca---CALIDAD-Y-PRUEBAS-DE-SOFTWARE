<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\View;
use PHPUnit\Framework\TestCase;

final class ViewTest extends TestCase
{
    public function testRenderOutputsContentOrNotFound(): void
    {
        ob_start();
        View::render('non_existent_page');
        $output = (string) ob_get_clean();

        $this->assertStringContainsString("Vista 'non_existent_page' no encontrada.", $output);
    }
}
