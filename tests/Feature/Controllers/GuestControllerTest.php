<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\GuestController;
use App\Repositories\GuestRepository;
use App\Services\GuestService;
use Tests\Support\MySqlTestCase;

final class GuestControllerTest extends MySqlTestCase
{
    private GuestService $guest_service;

    protected function setUp(): void
    {
        parent::setUp();

        $repo = new GuestRepository($this->pdo);
        $repo->create('DNI', '72819283', 'Pedro', 'Picapiedra');

        $this->guest_service = new GuestService($repo);
    }

    public function testLookupReturnsExistingGuestJson(): void
    {
        $_GET['document_type'] = 'DNI';
        $_GET['document_number'] = '72819283';

        $controller = new GuestController($this->guest_service);

        ob_start();
        $controller->lookup();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertEquals('72819283', $data['data']['guest']['document_number']);
        $this->assertEquals('Pedro', $data['data']['guest']['first_name']);
    }
}
