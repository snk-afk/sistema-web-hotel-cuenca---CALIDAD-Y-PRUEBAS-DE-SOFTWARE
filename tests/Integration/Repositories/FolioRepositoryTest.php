<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Repositories\FolioChargeRepository;
use App\Repositories\FolioPaymentRepository;
use App\Repositories\FolioRepository;
use App\Repositories\RoomCategoryRepository;
use App\Repositories\RoomRepository;
use App\Repositories\RoomTariffRepository;
use Tests\Support\MySqlTestCase;

final class FolioRepositoryTest extends MySqlTestCase
{
    private int $stay_id;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createUserFixture(1, 'folio');

        $category_repo = new RoomCategoryRepository($this->pdo());
        $category = $category_repo->create('Folio Test');
        $room_repo = new RoomRepository($this->pdo(), $category_repo);
        $room = $room_repo->create($category->id, '301');
        $tariff_repo = new RoomTariffRepository($this->pdo());
        $tariff = $tariff_repo->create($category->id, '24 Horas', 24, 100.00);

        $stay = $this->pdo()->prepare(
            'INSERT INTO stays '
            . '(room_id, room_tariff_id, check_in_at, expected_check_out_at, '
            . 'base_price, applied_price, status, created_by_user_id) '
            . 'VALUES (:room_id, :tariff_id, :check_in_at, :check_out_at, '
            . '100.00, 100.00, :status, :created_by_user_id)'
        );
        $stay->execute([
            'room_id' => $room->id,
            'tariff_id' => $tariff->id,
            'check_in_at' => '2026-09-07 10:00:00',
            'check_out_at' => '2026-09-08 10:00:00',
            'status' => 'active',
            'created_by_user_id' => 1,
        ]);
        $this->stay_id = (int) $this->pdo()->lastInsertId();
    }

    public function testCreateAndCalculateFolioTotals(): void
    {
        $folio_repo = new FolioRepository($this->pdo());
        $charge_repo = new FolioChargeRepository($this->pdo());
        $payment_repo = new FolioPaymentRepository($this->pdo());

        $folio = $folio_repo->create(stay_id: $this->stay_id);
        $this->assertEquals('open', $folio->status);

        $charge_repo->create($folio->id, 'accommodation', 'Alojamiento', 1, 50.00, 50.00, 1);
        $payment_repo->create($folio->id, 'advance', 20.00, 'cash', 1);

        $reloaded = $folio_repo->findById($folio->id);
        $this->assertNotNull($reloaded);
        $this->assertEquals(50.00, $reloaded->total_charges);
        $this->assertEquals(20.00, $reloaded->total_payments);
        $this->assertEquals(30.00, $reloaded->pending_balance);
    }
}
