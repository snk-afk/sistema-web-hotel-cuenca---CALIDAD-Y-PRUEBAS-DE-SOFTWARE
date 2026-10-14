<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\GuestRepository;
use Tests\Support\MySqlTestCase;

final class GuestRepositoryTest extends MySqlTestCase
{
    public function testCreateAndSearchGuest(): void
    {
        $repository = new GuestRepository($this->pdo());

        $created = $repository->create('DNI', '99887766', 'Ana', 'Ruiz');
        $this->assertEquals('99887766', $created->document_number);

        $results = $repository->search('Ruiz');
        $this->assertCount(1, $results);
        $this->assertEquals('Ana Ruiz', $results[0]->name);
    }
}
