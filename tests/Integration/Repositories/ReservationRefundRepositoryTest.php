<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\GuestRepository;
use App\Repositories\ReservationCancellationRepository;
use App\Repositories\ReservationPaymentRepository;
use App\Repositories\ReservationRefundRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use PDOException;
use Tests\Support\MySqlTestCase;

final class ReservationRefundRepositoryTest extends MySqlTestCase
{
    public function testCreateFindAndListRefund(): void
    {
        $user_id = $this->createUserFixture(3, 'refund-repository');
        $reservation_id = $this->createReservation($user_id);
        $shift_id = $this->createShift($user_id);
        $payment_repository = new ReservationPaymentRepository($this->pdo());
        $payment = $payment_repository->create(
            reservation_id: $reservation_id,
            cashbox_shift_id: $shift_id,
            amount: 40.00,
            payment_method: 'yape',
            created_by_user_id: $user_id,
            reference_number: 'YAPE-REF-001',
        );
        $cancellation_repository = new ReservationCancellationRepository($this->pdo());
        $cancellation = $cancellation_repository->create(
            reservation_id: $reservation_id,
            event_type: 'cancelled',
            policy_code: 'full_refund',
            refund_percentage: 100.0,
            gross_paid_amount: 40.0,
            refund_amount: 40.0,
            penalty_amount: 0.0,
            status: 'pending_refund',
            reason: 'Prueba de devolución',
            requested_by_user_id: $user_id,
        );
        $repository = new ReservationRefundRepository($this->pdo());

        $refund = $repository->create(
            cancellation_id: $cancellation->id,
            reservation_payment_id: $payment->id,
            cashbox_shift_id: $shift_id,
            amount: 40.00,
            payment_method: 'yape',
            reference_number: 'REF-001',
            executed_by_user_id: $user_id,
            reason: 'Autorizada',
        );

        self::assertSame($payment->id, $refund->reservation_payment_id);
        self::assertSame($refund->id, $repository->findById($refund->id)?->id);
        self::assertSame($refund->id, $repository->findByReservationPaymentId($payment->id)?->id);
        self::assertCount(1, $repository->findByCancellationId($cancellation->id));
    }

    public function testReservationPaymentRepositoryLocksAndDetectsFolioApplication(): void
    {
        $user_id = $this->createUserFixture(4, 'refund-folio');
        $reservation_id = $this->createReservation($user_id);
        $shift_id = $this->createShift($user_id);
        $payment_repository = new ReservationPaymentRepository($this->pdo());
        $payment = $payment_repository->create(
            reservation_id: $reservation_id,
            cashbox_shift_id: $shift_id,
            amount: 30.00,
            payment_method: 'cash',
            created_by_user_id: $user_id,
        );

        self::assertCount(1, $payment_repository->findByReservationIdForUpdate($reservation_id));
        self::assertFalse($payment_repository->hasAppliedFolioPayment($payment->id));

        $stay_id = $this->createStay($user_id);
        $folio_statement = $this->pdo()->prepare(
            'INSERT INTO folios (stay_id, status) VALUES (:stay_id, :status)'
        );
        $folio_statement->execute(['stay_id' => $stay_id, 'status' => 'open']);
        $folio_id = (int) $this->pdo()->lastInsertId();
        $link_statement = $this->pdo()->prepare(
            'INSERT INTO folio_payments '
            . '(folio_id, payment_type, amount, payment_method, created_by_user_id, reservation_payment_id) '
            . 'VALUES (:folio_id, :payment_type, :amount, :payment_method, :created_by_user_id, :reservation_payment_id)'
        );
        $link_statement->execute([
            'folio_id' => $folio_id,
            'payment_type' => 'advance',
            'amount' => 30.00,
            'payment_method' => 'cash',
            'created_by_user_id' => $user_id,
            'reservation_payment_id' => $payment->id,
        ]);

        self::assertTrue($payment_repository->hasAppliedFolioPayment($payment->id));
    }

    public function testReservationPaymentRefundIsUnique(): void
    {
        $user_id = $this->createUserFixture(5, 'refund-unique');
        $reservation_id = $this->createReservation($user_id);
        $shift_id = $this->createShift($user_id);
        $payment_repository = new ReservationPaymentRepository($this->pdo());
        $payment = $payment_repository->create(
            reservation_id: $reservation_id,
            cashbox_shift_id: $shift_id,
            amount: 15.00,
            payment_method: 'card',
            created_by_user_id: $user_id,
        );
        $cancellation_repository = new ReservationCancellationRepository($this->pdo());
        $cancellation = $cancellation_repository->create(
            reservation_id: $reservation_id,
            event_type: 'cancelled',
            policy_code: 'full_refund',
            refund_percentage: 100.0,
            gross_paid_amount: 15.0,
            refund_amount: 15.0,
            penalty_amount: 0.0,
            status: 'pending_refund',
            reason: 'Prueba',
            requested_by_user_id: $user_id,
        );
        $repository = new ReservationRefundRepository($this->pdo());
        $arguments = [
            'cancellation_id' => $cancellation->id,
            'reservation_payment_id' => $payment->id,
            'cashbox_shift_id' => $shift_id,
            'amount' => 15.00,
            'payment_method' => 'card',
            'reference_number' => null,
            'executed_by_user_id' => $user_id,
            'reason' => 'Prueba',
        ];

        $repository->create(...$arguments);
        $this->expectException(PDOException::class);
        $repository->create(...$arguments);
    }

    private function createReservation(int $user_id): int
    {
        $category_repository = new RoomCategoryRepository($this->pdo());
        $category = $category_repository->create('Refund Repository Category');
        $room_repository = new RoomRepository($this->pdo(), $category_repository);
        $room = $room_repository->create($category->id, 'RR-' . $user_id);
        $tariff_repository = new RoomTariffRepository($this->pdo());
        $tariff = $tariff_repository->create($category->id, '12 Horas', 12, 80.00);
        $guest_repository = new GuestRepository($this->pdo());
        $guest = $guest_repository->create('DNI', 'RR-' . $user_id, 'Refund Guest');
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
            reserved_check_in_at: '2026-09-20 12:00:00',
            reserved_check_out_at: '2026-09-21 12:00:00',
            advance_payment: 0.0,
            created_by_user_id: $user_id,
        );

        return $reservation->id;
    }

    private function createShift(int $user_id): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO cashbox_shifts (user_id, opened_at, initial_cash, expected_cash, status, balance_status) '
            . 'VALUES (:user_id, :opened_at, :initial_cash, :expected_cash, :status, :balance_status)'
        );
        $statement->execute([
            'user_id' => $user_id,
            'opened_at' => '2026-09-07 09:00:00',
            'initial_cash' => 100.00,
            'expected_cash' => 100.00,
            'status' => 'open',
            'balance_status' => 'pending',
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function createStay(int $user_id): int
    {
        $category_repository = new RoomCategoryRepository($this->pdo());
        $category = $category_repository->create('Refund Stay Category');
        $room_repository = new RoomRepository($this->pdo(), $category_repository);
        $room = $room_repository->create($category->id, 'ST-' . $user_id);
        $tariff_repository = new RoomTariffRepository($this->pdo());
        $tariff = $tariff_repository->create($category->id, '12 Horas', 12, 80.00);
        $guest_repository = new GuestRepository($this->pdo());
        $guest = $guest_repository->create('DNI', 'ST-' . $user_id, 'Stay Guest');
        $statement = $this->pdo()->prepare(
            'INSERT INTO stays '
            . '(room_id, room_tariff_id, guest_id, check_in_at, expected_check_out_at, base_price, applied_price, status, created_by_user_id) '
            . 'VALUES (:room_id, :room_tariff_id, :guest_id, :check_in_at, :expected_check_out_at, :base_price, :applied_price, :status, :created_by_user_id)'
        );
        $statement->execute([
            'room_id' => $room->id,
            'room_tariff_id' => $tariff->id,
            'guest_id' => $guest->id,
            'check_in_at' => '2026-09-07 10:00:00',
            'expected_check_out_at' => '2026-09-07 22:00:00',
            'base_price' => 80.00,
            'applied_price' => 80.00,
            'status' => 'active',
            'created_by_user_id' => $user_id,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }
}
