<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\GuestRepository;
use App\Repositories\ReservationCancellationRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use PDOException;
use Tests\Support\MySqlTestCase;

final class ReservationCancellationRepositoryTest extends MySqlTestCase
{
    public function testCreateFindLockAndCompleteSettlement(): void
    {
        $user_id = $this->createUserFixture(1, 'cancellation-repository');
        $reservation_id = $this->createReservation($user_id, '2026-09-20 12:00:00');
        $repository = new ReservationCancellationRepository($this->pdo());

        $created = $repository->create(
            reservation_id: $reservation_id,
            event_type: 'cancelled',
            policy_code: 'partial_refund',
            refund_percentage: 50.0,
            gross_paid_amount: 100.0,
            refund_amount: 50.0,
            penalty_amount: 50.0,
            status: 'pending_refund',
            reason: 'Cambio de planes',
            requested_by_user_id: $user_id,
        );

        self::assertSame($reservation_id, $repository->findById($created->id)?->reservation_id);
        self::assertSame($created->id, $repository->findByReservationId($reservation_id)?->id);
        self::assertSame($created->id, $repository->lockByReservationId($reservation_id)?->id);
        self::assertSame(
            1,
            $repository->markCompleted($created->id, $user_id, '2026-09-07 12:00:00')
        );
        self::assertSame('completed', $repository->findById($created->id)?->status);
    }

    public function testReservationHasOnlyOneSettlement(): void
    {
        $user_id = $this->createUserFixture(2, 'cancellation-unique');
        $reservation_id = $this->createReservation($user_id, '2026-09-21 12:00:00');
        $repository = new ReservationCancellationRepository($this->pdo());

        $arguments = [
            'reservation_id' => $reservation_id,
            'event_type' => 'cancelled',
            'policy_code' => 'no_refund',
            'refund_percentage' => 0.0,
            'gross_paid_amount' => 0.0,
            'refund_amount' => 0.0,
            'penalty_amount' => 0.0,
            'status' => 'no_refund',
            'reason' => 'Sin adelanto',
            'requested_by_user_id' => $user_id,
        ];
        $repository->create(...$arguments);

        $this->expectException(PDOException::class);
        $repository->create(...$arguments);
    }

    private function createReservation(int $user_id, string $check_in_at): int
    {
        $category_repository = new RoomCategoryRepository($this->pdo());
        $category = $category_repository->create('Cancellation Repository Category');
        $room_repository = new RoomRepository($this->pdo(), $category_repository);
        $room = $room_repository->create($category->id, 'CR-' . $user_id);
        $tariff_repository = new RoomTariffRepository($this->pdo());
        $tariff = $tariff_repository->create($category->id, '24 Horas', 24, 100.00);
        $guest_repository = new GuestRepository($this->pdo());
        $guest = $guest_repository->create('DNI', 'CR-' . $user_id, 'Cancellation Guest');
        $reservation_repository = new ReservationRepository(
            $this->pdo(),
            $room_repository,
            $guest_repository,
            $tariff_repository,
        );

        $reservation = $reservation_repository->create(
            room_id: $room->id,
            guest_id: $guest->id,
            room_tariff_id: $tariff->id,
            reserved_check_in_at: $check_in_at,
            reserved_check_out_at: '2026-09-21 12:00:00',
            advance_payment: 0.0,
            created_by_user_id: $user_id,
        );

        return $reservation->id;
    }
}
