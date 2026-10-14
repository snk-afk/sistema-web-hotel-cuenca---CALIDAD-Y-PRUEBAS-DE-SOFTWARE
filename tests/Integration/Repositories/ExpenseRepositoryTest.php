<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\ExpenseRepository;
use Tests\Support\MySqlTestCase;

final class ExpenseRepositoryTest extends MySqlTestCase
{
    private int $cashbox_shift_id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUserFixture(1, 'expense');

        $shift = $this->pdo()->prepare(
            'INSERT INTO cashbox_shifts '
            . '(user_id, opened_at, initial_cash, expected_cash, status, balance_status) '
            . 'VALUES (:user_id, :opened_at, 0.00, 0.00, :status, :balance_status)'
        );
        $shift->execute([
            'user_id' => 1,
            'opened_at' => '2026-09-07 10:00:00',
            'status' => 'open',
            'balance_status' => 'pending',
        ]);
        $this->cashbox_shift_id = (int) $this->pdo()->lastInsertId();
    }

    public function testCreateAndListExpenses(): void
    {
        $repo = new ExpenseRepository($this->pdo());

        $created = $repo->create(
            cashbox_shift_id: $this->cashbox_shift_id,
            category: 'maintenance',
            concept: 'Reparación de caño',
            amount: 25.00,
            payment_method: 'cash',
            created_by_user_id: 1
        );

        $this->assertEquals('Reparación de caño', $created->concept);
        $this->assertEquals(25.00, $created->amount);

        $all = $repo->findAll();
        $this->assertCount(1, $all);
    }
}
