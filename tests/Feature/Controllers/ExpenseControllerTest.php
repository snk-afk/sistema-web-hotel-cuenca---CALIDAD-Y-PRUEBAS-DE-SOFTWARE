<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers;

use App\Controllers\ExpenseController;
use App\Repositories\ExpenseRepository;
use App\Services\CashboxService;
use App\Services\ExpenseService;
use Tests\Support\MySqlTestCase;

final class ExpenseControllerTest extends MySqlTestCase
{
    private ExpenseService $expense_service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createUserFixture(1, 'expense');
        $shift = (new CashboxService($this->pdo))->openShift(user_id: 1, initial_cash: 0.0);
        $repo = new ExpenseRepository($this->pdo);
        $repo->create($shift->id, 'petty_cash', 'Foco hall', 5.00, 'cash', 1);

        $this->expense_service = new ExpenseService($this->pdo);
    }

    public function testIndexReturnsExpensesJson(): void
    {
        $controller = new ExpenseController($this->expense_service);

        ob_start();
        $controller->index();
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);

        $this->assertIsArray($data);
        $this->assertEquals('success', $data['status']);
        $this->assertCount(1, $data['data']);
        $this->assertEquals('Foco hall', $data['data'][0]['concept']);
    }
}
