<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Repositories\InvoiceSeriesRepository;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas de numeración fiscal atómica (SPEC-0036 — CA-7).
 */
final class InvoiceSeriesRepositoryTest extends TestCase
{
    private PDO $pdo;
    private InvoiceSeriesRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $this->pdo->exec('
            CREATE TABLE invoice_series (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_type TEXT NOT NULL,
                series TEXT NOT NULL,
                current_number INTEGER DEFAULT 0,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                document_type TEXT NOT NULL,
                series TEXT NOT NULL,
                number INTEGER NOT NULL
            );
        ');

        $this->repo = new InvoiceSeriesRepository($this->pdo);
    }

    public function testGetNextNumberReturnsFirstNumber(): void
    {
        $result = $this->repo->getNextNumberAndIncrement('BOLETA');

        $this->assertEquals('B001', $result['series']);
        $this->assertEquals(1, $result['number']);
        $this->assertEquals('B001-00000001', $result['full_number']);
    }

    public function testGetNextNumberIncrementsConsecutively(): void
    {
        $first = $this->repo->getNextNumberAndIncrement('BOLETA');
        $second = $this->repo->getNextNumberAndIncrement('BOLETA');
        $third = $this->repo->getNextNumberAndIncrement('BOLETA');

        $this->assertEquals(1, $first['number']);
        $this->assertEquals(2, $second['number']);
        $this->assertEquals(3, $third['number']);
    }

    public function testGetNextNumberProducesUniqueConsecutiveNumbers(): void
    {
        // CA-7: Each call must produce a unique, consecutive number.
        $numbers = [];
        for ($i = 1; $i <= 5; $i++) {
            $result = $this->repo->getNextNumberAndIncrement('FACTURA');
            $numbers[] = $result['number'];
        }

        // All numbers must be unique
        $this->assertCount(5, array_unique($numbers));

        // Numbers must be consecutive: 1, 2, 3, 4, 5
        $this->assertEquals([1, 2, 3, 4, 5], $numbers);
    }

    public function testDifferentDocumentTypesHaveIndependentNumbering(): void
    {
        $boleta = $this->repo->getNextNumberAndIncrement('BOLETA');
        $factura = $this->repo->getNextNumberAndIncrement('FACTURA');
        $boleta2 = $this->repo->getNextNumberAndIncrement('BOLETA');

        $this->assertEquals(1, $boleta['number']);
        $this->assertEquals(1, $factura['number']);
        $this->assertEquals(2, $boleta2['number']);
    }
}
