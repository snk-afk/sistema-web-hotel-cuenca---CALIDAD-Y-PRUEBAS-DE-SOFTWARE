<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\GuestRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use Tests\Support\MySqlTestCase;

final class ReservationRepositoryTest extends MySqlTestCase
{
    private ReservationRepository $repo;
    private RoomRepository $room_repo;
    private GuestRepository $guest_repo;
    private RoomTariffRepository $tariff_repo;
    private RoomCategoryRepository $category_repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUserFixture(1, 'reservation');

        $this->category_repo = new RoomCategoryRepository($this->pdo());
        $this->room_repo = new RoomRepository($this->pdo(), $this->category_repo);
        $this->tariff_repo = new RoomTariffRepository($this->pdo());
        $this->guest_repo = new GuestRepository($this->pdo());
        $this->repo = new ReservationRepository(
            $this->pdo(),
            $this->room_repo,
            $this->guest_repo,
            $this->tariff_repo,
        );
    }

    public function testCreateFindAndCatalog(): void
    {
        $category = $this->category_repo->create('Suite Presidencial');
        $room = $this->room_repo->create($category->id, '501');
        $tariff = $this->tariff_repo->create($category->id, '24 Horas', 24, 200.00);
        $guest = $this->guest_repo->create('DNI', '88776655', 'Valeria Ramos', phone: '998877665');

        $res = $this->repo->create(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: '2026-08-25 14:00:00',
            reserved_check_out_at: '2026-08-26 14:00:00',
            advance_payment: 100.00,
            created_by_user_id: 1,
            notes: 'Vista al mar'
        );

        $this->assertEquals('confirmed', $res->status);
        $this->assertEquals(100.00, $res->advance_payment);

        $found = $this->repo->findById($res->id);
        $this->assertNotNull($found);
        $this->assertEquals('501', $found->room?->number);
        $this->assertEquals('Valeria Ramos', $found->guest?->name);

        $catalog = $this->repo->getReservationsCatalog();
        $this->assertCount(1, $catalog);
        $this->assertEquals('501', $catalog[0]['room_number']);
        $this->assertEquals('Suite Presidencial', $catalog[0]['category_name']);

        $metrics = $this->repo->getMetrics();
        $this->assertEquals(1, $metrics['total_reservations']);
        $this->assertEquals(1, $metrics['confirmed_count']);
        $this->assertEquals(100.00, $metrics['total_advance_amount']);
    }

    public function testOverlapQueryExcludesCancelled(): void
    {
        $category = $this->category_repo->create('Simple');
        $room = $this->room_repo->create($category->id, '105');
        $tariff = $this->tariff_repo->create($category->id, '12 Horas', 12, 50.00);
        $guest = $this->guest_repo->create('DNI', '11112222', 'Test User');

        $res = $this->repo->create(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: '2026-08-25 10:00:00',
            reserved_check_out_at: '2026-08-25 22:00:00',
            advance_payment: 0.00,
            created_by_user_id: 1
        );

        $this->assertTrue($this->repo->hasOverlappingReservation($room->id, '2026-08-25 12:00:00', '2026-08-25 18:00:00'));

        $this->repo->updateStatus($res->id, 'cancelled');

        $this->assertFalse($this->repo->hasOverlappingReservation($room->id, '2026-08-25 12:00:00', '2026-08-25 18:00:00'));
    }
}
