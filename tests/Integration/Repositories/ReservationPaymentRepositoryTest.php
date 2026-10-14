<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\GuestRepository;
use App\Repositories\ReservationPaymentRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use Tests\Support\MySqlTestCase;

final class ReservationPaymentRepositoryTest extends MySqlTestCase
{
    public function testCreateAndFindReservationPayment(): void
    {
        $user_id = $this->createUserFixture(1, 'reservation-payment-repository');
        $category_repository = new RoomCategoryRepository($this->pdo());
        $category = $category_repository->create('Reservation Payment Test');
        $room_repository = new RoomRepository($this->pdo(), $category_repository);
        $room = $room_repository->create($category->id, '601');
        $tariff_repository = new RoomTariffRepository($this->pdo());
        $tariff = $tariff_repository->create($category->id, '12 Horas', 12, 80.00);
        $guest_repository = new GuestRepository($this->pdo());
        $guest = $guest_repository->create('DNI', '60100100', 'Reservation Payment Guest');
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
            reserved_check_in_at: '2026-09-08 10:00:00',
            reserved_check_out_at: '2026-09-08 22:00:00',
            advance_payment: 20.00,
            created_by_user_id: $user_id,
        );

        $shift = $this->pdo()->prepare(
            'INSERT INTO cashbox_shifts (user_id, opened_at, initial_cash, expected_cash) '
            . 'VALUES (:user_id, :opened_at, :initial_cash, :expected_cash)'
        );
        $shift->execute([
            'user_id' => $user_id,
            'opened_at' => '2026-09-07 09:00:00',
            'initial_cash' => 100.00,
            'expected_cash' => 100.00,
        ]);
        $shift_id = (int) $this->pdo()->lastInsertId();

        $repository = new ReservationPaymentRepository($this->pdo());
        $payment = $repository->create(
            reservation_id: $reservation->id,
            cashbox_shift_id: $shift_id,
            amount: 20.00,
            payment_method: 'yape',
            created_by_user_id: $user_id,
            reference_number: 'YAPE-601',
            notes: 'Adelanto de prueba',
        );

        self::assertSame($reservation->id, $payment->reservation_id);
        self::assertSame($shift_id, $payment->cashbox_shift_id);
        self::assertSame(20.00, $payment->amount);
        self::assertSame('yape', $payment->payment_method);
        self::assertSame('YAPE-601', $payment->reference_number);

        $payments = $repository->findByReservationId($reservation->id);
        self::assertCount(1, $payments);
        self::assertSame($payment->id, $payments[0]->id);
    }
}
