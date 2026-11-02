<?php

declare(strict_types=1);

namespace Tests\Integration\Database;

use PDO;
use PDOException;
use Tests\Support\MySqlTestCase;

final class RelationalIntegrityTest extends MySqlTestCase
{
    public function testRejectsStayWithUnknownGuestWithoutLeavingPartialRow(): void
    {
        $user_id = $this->createUser('creator-stay');
        [$room_id, $tariff_id] = $this->createRoomFixture();

        $statement = $this->pdo()->prepare(
            'INSERT INTO stays '
            . '(room_id, room_tariff_id, guest_id, check_in_at, expected_check_out_at, '
            . 'base_price, applied_price, status, created_by_user_id) '
            . 'VALUES (:room_id, :tariff_id, :guest_id, :check_in_at, :check_out_at, '
            . ':base_price, :applied_price, :status, :created_by_user_id)'
        );

        $thrown = false;
        try {
            $statement->execute([
                'room_id' => $room_id,
                'tariff_id' => $tariff_id,
                'guest_id' => 999999,
                'check_in_at' => '2026-09-07 12:00:00',
                'check_out_at' => '2026-09-08 12:00:00',
                'base_price' => 100.00,
                'applied_price' => 100.00,
                'status' => 'active',
                'created_by_user_id' => $user_id,
            ]);
        } catch (PDOException) {
            $thrown = true;
        }

        self::assertTrue($thrown);

        $count = $this->pdo()->prepare('SELECT COUNT(*) FROM stays WHERE room_id = :room_id');
        $count->execute(['room_id' => $room_id]);
        self::assertSame(0, (int) $count->fetchColumn());
    }

    public function testRejectsCashboxShiftWithUnknownUser(): void
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO cashbox_shifts (user_id, opened_at, initial_cash, expected_cash) '
            . 'VALUES (:user_id, :opened_at, :initial_cash, :expected_cash)'
        );

        $thrown = false;
        try {
            $statement->execute([
                'user_id' => 999999,
                'opened_at' => '2026-09-07 12:00:00',
                'initial_cash' => 0.00,
                'expected_cash' => 0.00,
            ]);
        } catch (PDOException) {
            $thrown = true;
        }

        self::assertTrue($thrown);

        $count = $this->pdo()->query('SELECT COUNT(*) FROM cashbox_shifts')->fetchColumn();
        self::assertSame(0, (int) $count);
    }

    public function testAssignedHousekeepingUserCanBeDeletedAndAssignmentIsNullified(): void
    {
        $creator_id = $this->createUser('creator-housekeeping');
        $assigned_id = $this->createUser('assigned-housekeeping');
        [$room_id] = $this->createRoomFixture();

        $statement = $this->pdo()->prepare(
            'INSERT INTO housekeeping_tasks '
            . '(room_id, assigned_to_user_id, created_by_user_id) '
            . 'VALUES (:room_id, :assigned_to_user_id, :created_by_user_id)'
        );
        $statement->execute([
            'room_id' => $room_id,
            'assigned_to_user_id' => $assigned_id,
            'created_by_user_id' => $creator_id,
        ]);
        $task_id = (int) $this->pdo()->lastInsertId();

        $delete = $this->pdo()->prepare('DELETE FROM users WHERE id = :user_id');
        $delete->execute(['user_id' => $assigned_id]);

        $task = $this->pdo()->prepare(
            'SELECT assigned_to_user_id FROM housekeeping_tasks WHERE id = :task_id'
        );
        $task->execute(['task_id' => $task_id]);

        self::assertNull($task->fetchColumn());
    }

    public function testPersistsPenaltyAndAdjustmentFolioCharges(): void
    {
        $user_id = $this->createUser('creator-folio');
        [$room_id, $tariff_id] = $this->createRoomFixture();

        $stay = $this->pdo()->prepare(
            'INSERT INTO stays '
            . '(room_id, room_tariff_id, check_in_at, expected_check_out_at, '
            . 'base_price, applied_price, status, created_by_user_id) '
            . 'VALUES (:room_id, :tariff_id, :check_in_at, :check_out_at, '
            . ':base_price, :applied_price, :status, :created_by_user_id)'
        );
        $stay->execute([
            'room_id' => $room_id,
            'tariff_id' => $tariff_id,
            'check_in_at' => '2026-09-07 12:00:00',
            'check_out_at' => '2026-09-08 12:00:00',
            'base_price' => 100.00,
            'applied_price' => 100.00,
            'status' => 'active',
            'created_by_user_id' => $user_id,
        ]);
        $stay_id = (int) $this->pdo()->lastInsertId();

        $folio = $this->pdo()->prepare('INSERT INTO folios (stay_id) VALUES (:stay_id)');
        $folio->execute(['stay_id' => $stay_id]);
        $folio_id = (int) $this->pdo()->lastInsertId();

        $charge = $this->pdo()->prepare(
            'INSERT INTO folio_charges '
            . '(folio_id, charge_type, description, quantity, unit_price, total_amount, created_by_user_id) '
            . 'VALUES (:folio_id, :charge_type, :description, 1, 10.00, 10.00, :created_by_user_id)'
        );

        foreach (['penalty', 'adjustment'] as $charge_type) {
            $charge->execute([
                'folio_id' => $folio_id,
                'charge_type' => $charge_type,
                'description' => $charge_type,
                'created_by_user_id' => $user_id,
            ]);
        }

        $count = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM folio_charges WHERE folio_id = :folio_id'
        );
        $count->execute(['folio_id' => $folio_id]);

        self::assertSame(2, (int) $count->fetchColumn());
    }

    public function testRejectsReservationPaymentWithUnknownReservation(): void
    {
        $user_id = $this->createUser('reservation-payment-user');
        $shift_id = $this->createCashboxShift($user_id);

        $statement = $this->pdo()->prepare(
            'INSERT INTO reservation_payments '
            . '(reservation_id, cashbox_shift_id, amount, payment_method, created_by_user_id) '
            . 'VALUES (:reservation_id, :cashbox_shift_id, :amount, :payment_method, :created_by_user_id)'
        );

        $this->expectException(PDOException::class);
        $statement->execute([
            'reservation_id' => 999999,
            'cashbox_shift_id' => $shift_id,
            'amount' => 20.00,
            'payment_method' => 'cash',
            'created_by_user_id' => $user_id,
        ]);
    }

    private function createCashboxShift(int $user_id): int
    {
        $statement = $this->pdo()->prepare(
            'INSERT INTO cashbox_shifts '
            . '(user_id, opened_at, initial_cash, expected_cash) '
            . 'VALUES (:user_id, :opened_at, :initial_cash, :expected_cash)'
        );
        $statement->execute([
            'user_id' => $user_id,
            'opened_at' => '2026-09-07 12:00:00',
            'initial_cash' => 100.00,
            'expected_cash' => 100.00,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    private function createUser(string $suffix): int
    {
        $role = $this->pdo()->prepare(
            'INSERT INTO roles (name, description) VALUES (:name, :description)'
        );
        $role->execute([
            'name' => 'role-' . $suffix,
            'description' => 'Test role',
        ]);
        $role_id = (int) $this->pdo()->lastInsertId();

        $user = $this->pdo()->prepare(
            'INSERT INTO users '
            . '(role_id, username, email, password_hash, full_name, is_active) '
            . 'VALUES (:role_id, :username, :email, :password_hash, :full_name, 1)'
        );
        $user->execute([
            'role_id' => $role_id,
            'username' => 'user-' . $suffix,
            'email' => 'user-' . $suffix . '@example.test',
            'password_hash' => 'test-hash',
            'full_name' => 'Test ' . $suffix,
        ]);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createRoomFixture(): array
    {
        $category = $this->pdo()->prepare(
            'INSERT INTO room_categories (name, description) VALUES (:name, :description)'
        );
        $category->execute([
            'name' => 'Test category',
            'description' => 'Test category',
        ]);
        $category_id = (int) $this->pdo()->lastInsertId();

        $room = $this->pdo()->prepare(
            'INSERT INTO rooms (room_category_id, number, status) '
            . 'VALUES (:category_id, :number, :status)'
        );
        $room->execute([
            'category_id' => $category_id,
            'number' => '900',
            'status' => 'free',
        ]);
        $room_id = (int) $this->pdo()->lastInsertId();

        $tariff = $this->pdo()->prepare(
            'INSERT INTO room_tariffs '
            . '(room_category_id, name, duration_hours, price) '
            . 'VALUES (:category_id, :name, 24, 100.00)'
        );
        $tariff->execute([
            'category_id' => $category_id,
            'name' => 'Test tariff',
        ]);

        return [$room_id, (int) $this->pdo()->lastInsertId()];
    }
}
