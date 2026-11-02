<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\CashboxShiftRepository;
use Tests\Support\MySqlTestCase;

final class CashboxShiftRepositoryTest extends MySqlTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->createUserFixture(1, 'cashbox-one');
        $this->createUserFixture(2, 'cashbox-two');
    }

    public function testCreateAndCloseShiftRepository(): void
    {
        $repo = new CashboxShiftRepository($this->pdo());

        $shift = $repo->create(user_id: 2, initial_cash: 50.00);
        $this->assertEquals('open', $shift->status);

        $active = $repo->findActiveByUserId(2);
        $this->assertNotNull($active);
        $this->assertEquals($shift->id, $active->id);

        $repo->closeShift($shift->id, 50.00, 50.00, 0.0, 'balanced');

        $reloaded = $repo->findById($shift->id);
        $this->assertNotNull($reloaded);
        $this->assertEquals('closed', $reloaded->status);
        $this->assertEquals('balanced', $reloaded->balance_status);
    }

    public function testFindAllReturnsAllShiftsOrderedDesc(): void
    {
        $repo = new CashboxShiftRepository($this->pdo());

        $shift1 = $repo->create(user_id: 1, initial_cash: 100.00);
        $shift2 = $repo->create(user_id: 2, initial_cash: 200.00);
        $shift3 = $repo->create(user_id: 1, initial_cash: 50.00);

        $all = $repo->findAll();

        $this->assertCount(3, $all);
        $this->assertEquals($shift3->id, $all[0]->id);
        $this->assertEquals($shift2->id, $all[1]->id);
        $this->assertEquals($shift1->id, $all[2]->id);
        $this->assertSame([], $all[0]->movements);
    }

    public function testFindAllFiltersByStatus(): void
    {
        $repo = new CashboxShiftRepository($this->pdo());

        $repo->create(user_id: 1, initial_cash: 100.00);
        $shift_closed = $repo->create(user_id: 1, initial_cash: 200.00);
        $repo->closeShift($shift_closed->id, 200.00, 200.00, 0.0, 'balanced');

        $closed_only = $repo->findAll(status: 'closed');
        $this->assertCount(1, $closed_only);
        $this->assertEquals('closed', $closed_only[0]->status);

        $open_only = $repo->findAll(status: 'open');
        $this->assertCount(1, $open_only);
        $this->assertEquals('open', $open_only[0]->status);
    }

    public function testFindAllFiltersByUserId(): void
    {
        $repo = new CashboxShiftRepository($this->pdo());

        $repo->create(user_id: 1, initial_cash: 100.00);
        $repo->create(user_id: 2, initial_cash: 200.00);
        $repo->create(user_id: 1, initial_cash: 50.00);

        $user1_shifts = $repo->findAll(user_id: 1);
        $this->assertCount(2, $user1_shifts);
        foreach ($user1_shifts as $shift) {
            $this->assertEquals(1, $shift->user_id);
        }
    }

    public function testFindAllFiltersByDateRange(): void
    {
        $repo = new CashboxShiftRepository($this->pdo());
        $insert = $this->pdo()->prepare(
            'INSERT INTO cashbox_shifts '
            . '(user_id, opened_at, initial_cash, expected_cash, status, balance_status) '
            . 'VALUES (:user_id, :opened_at, 100.00, 100.00, :status, :balance_status)'
        );

        foreach ([
            ['opened_at' => '2026-07-15 10:00:00', 'status' => 'closed', 'balance_status' => 'balanced'],
            ['opened_at' => '2026-08-10 10:00:00', 'status' => 'closed', 'balance_status' => 'balanced'],
            ['opened_at' => '2026-09-05 10:00:00', 'status' => 'open', 'balance_status' => 'pending'],
        ] as $fixture) {
            $insert->execute([
                'user_id' => 1,
                'opened_at' => $fixture['opened_at'],
                'status' => $fixture['status'],
                'balance_status' => $fixture['balance_status'],
            ]);
        }

        $august = $repo->findAll(from: '2026-08-01', to: '2026-08-31');
        $this->assertCount(1, $august);
        $this->assertEquals('2026-08-10 10:00:00', $august[0]->opened_at);
    }

    public function testFindAllReturnsEmptyArrayWhenNoMatches(): void
    {
        $repo = new CashboxShiftRepository($this->pdo());

        $repo->create(user_id: 1, initial_cash: 100.00);

        $no_results = $repo->findAll(user_id: 999);
        $this->assertSame([], $no_results);
    }

    public function testFindAllIgnoresInvalidStatusFilter(): void
    {
        $repo = new CashboxShiftRepository($this->pdo());

        $repo->create(user_id: 1, initial_cash: 100.00);
        $repo->create(user_id: 2, initial_cash: 200.00);

        $all = $repo->findAll(status: 'invalid');
        $this->assertCount(2, $all);
    }

    public function testFindAllAppliesDefaultLimit(): void
    {
        $repo = new CashboxShiftRepository($this->pdo());
        $insert = $this->pdo()->prepare(
            'INSERT INTO cashbox_shifts '
            . '(user_id, opened_at, initial_cash, expected_cash, status, balance_status) '
            . 'VALUES (1, :opened_at, 100.00, 100.00, :status, :balance_status)'
        );

        for ($index = 0; $index < 105; $index++) {
            $insert->execute([
                'opened_at' => '2026-08-01 10:00:00',
                'status' => 'closed',
                'balance_status' => 'balanced',
            ]);
        }

        $all = $repo->findAll();
        $this->assertCount(100, $all);
    }
}
