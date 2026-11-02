<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Repositories\GuestRepository;
use App\Services\GuestService;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class GuestServiceTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('
            CREATE TABLE guests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_type TEXT DEFAULT "DNI",
                document_number TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                phone TEXT,
                email TEXT,
                address TEXT,
                notes TEXT,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            );
        ');
    }

    public function testCreateGuestStoresGuestInRepository(): void
    {
        $repository = new GuestRepository($this->pdo);
        $service = new GuestService($repository);

        $guest = $service->createGuest(
            document_type: 'DNI',
            document_number: '72819283',
            first_name: 'Juan',
            last_name: 'Pérez',
            phone: '987654321'
        );

        $this->assertEquals('72819283', $guest->document_number);
        $this->assertEquals('Juan Pérez', $guest->name);
    }

    public function testCreateGuestThrowsExceptionForInvalidDocumentType(): void
    {
        $repository = new GuestRepository($this->pdo);
        $service = new GuestService($repository);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('inválido');

        $service->createGuest(
            document_type: 'INVALID_TYPE',
            document_number: '123'
        );
    }

    public function testUpdateGuestModifiesDataSuccessfully(): void
    {
        $repository = new GuestRepository($this->pdo);
        $service = new GuestService($repository);

        $guest = $service->createGuest(
            document_type: 'DNI',
            document_number: '11223344',
            first_name: 'Carlos',
            last_name: 'Santana',
            phone: '999111222'
        );

        $updated = $service->updateGuest(
            id: $guest->id,
            document_type: 'DNI',
            document_number: '11223344',
            name: 'Carlos Santana Jr.',
            phone: '999888777',
            email: 'carlos@santana.com',
            address: 'Av. Primavera 100',
            notes: 'Cliente VIP - Suite Jacuzzi'
        );

        $this->assertSame('Carlos Santana Jr.', $updated->name);
        $this->assertSame('999888777', $updated->phone);
        $this->assertSame('Cliente VIP - Suite Jacuzzi', $updated->notes);
    }

    public function testGetGuestsCatalogAndProfile(): void
    {
        $repository = new GuestRepository($this->pdo);
        $service = new GuestService($repository);

        $this->pdo->exec('
            CREATE TABLE stays (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                guest_id INTEGER,
                room_id INTEGER,
                room_tariff_id INTEGER,
                check_in_at TEXT,
                expected_check_out_at TEXT,
                actual_check_out_at TEXT,
                base_price REAL,
                applied_price REAL,
                discount_reason TEXT,
                status TEXT,
                created_by_user_id INTEGER,
                created_at TEXT,
                updated_at TEXT
            );
            CREATE TABLE rooms (id INTEGER, number TEXT, room_category_id INTEGER);
            CREATE TABLE room_categories (id INTEGER, name TEXT);
            CREATE TABLE room_tariffs (id INTEGER, name TEXT);
            CREATE TABLE folios (id INTEGER, stay_id INTEGER, status TEXT);
        ');

        $guest = $service->createGuest(
            document_type: 'RUC',
            document_number: '20601234567',
            company_name: 'Inversiones Hotel SAC'
        );

        $this->pdo->exec("INSERT INTO stays (guest_id, room_id, applied_price, status, created_by_user_id) VALUES ({$guest->id}, 1, 150.0, 'completed', 1)");

        $catalog = $service->getGuestsCatalog();
        $this->assertNotEmpty($catalog);
        $this->assertSame(1, $catalog[0]['stays_count']);
        $this->assertSame(150.0, $catalog[0]['total_spent']);

        $profile = $service->getGuestProfile($guest->id);
        $this->assertNotNull($profile);
        $this->assertSame(1, $profile['stays_count']);
        $this->assertSame(150.0, $profile['total_spent']);
    }
}
