<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\CashboxShift;
use PDO;

/**
 * @phpstan-type CashboxShiftRow array{
     *     id: int|string,
     *     user_id: int|string,
     *     opened_at: string,
     *     closed_at: string|null,
     *     initial_cash: float|string,
     *     expected_cash: float|string,
     *     real_cash_declared: float|string|null,
     *     difference: float|string|null,
     *     status: string,
     *     balance_status: string,
     *     notes: string|null,
     *     expected_yape: float|string|null,
     *     expected_plin: float|string|null,
     *     expected_card: float|string|null,
     *     expected_transfer: float|string|null,
     *     real_yape_declared: float|string|null,
     *     real_plin_declared: float|string|null,
     *     real_card_declared: float|string|null,
     *     real_transfer_declared: float|string|null,
     *     difference_yape: float|string|null,
     *     difference_plin: float|string|null,
     *     difference_card: float|string|null,
     *     difference_transfer: float|string|null,
     *     created_at: string,
     *     updated_at: string
 * }
 */
final class CashboxShiftRepository
{
    private const SELECT_COLUMNS = 'id, user_id, opened_at, closed_at, initial_cash, expected_cash, '
        . 'real_cash_declared, difference, status, balance_status, notes, '
        . 'expected_yape, expected_plin, expected_card, expected_transfer, '
        . 'real_yape_declared, real_plin_declared, real_card_declared, real_transfer_declared, '
        . 'difference_yape, difference_plin, difference_card, difference_transfer, '
        . 'created_at, updated_at';

    private PDO $db;
    private CashMovementRepository $movement_repository;

    public function __construct(?PDO $db = null, ?CashMovementRepository $movement_repository = null)
    {
        $this->db = $db ?? Database::connection();
        $this->movement_repository = $movement_repository ?? new CashMovementRepository($this->db);
    }

    /**
     * @return array<int, CashboxShift>
     */
    public function findAll(
        ?string $status = null,
        ?int $user_id = null,
        ?string $from = null,
        ?string $to = null,
        int $limit = 100
    ): array {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' FROM cashbox_shifts';
        $where = [];
        $params = [];

        if ($status !== null && in_array($status, ['open', 'closed'], true)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        if ($user_id !== null && $user_id > 0) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = $user_id;
        }

        if ($from !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) === 1) {
            $where[] = 'opened_at >= :from';
            $params['from'] = $from . ' 00:00:00';
        }

        if ($to !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) === 1) {
            $where[] = 'opened_at <= :to';
            $params['to'] = $to . ' 23:59:59';
        }

        if ($where !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        /** @var array<int, CashboxShiftRow> $rows */
        $rows = $statement->fetchAll();

        return array_map(
            fn(array $row): CashboxShift => $this->mapRow($row, []),
            $rows
        );
    }

    public function findById(int $id): ?CashboxShift
    {
        $statement = $this->db->prepare(
            'SELECT ' . self::SELECT_COLUMNS . ' FROM cashbox_shifts WHERE id = :id'
        );
        $statement->execute(['id' => $id]);

        /** @var CashboxShiftRow|false $row */
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return $this->mapRow(
            $row,
            $this->movement_repository->findByShiftId((int) $row['id'])
        );
    }

    public function findActiveByUserId(int $user_id): ?CashboxShift
    {
        return $this->findActiveByUserIdInternal($user_id, false);
    }

    public function findActiveByUserIdForUpdate(int $user_id): ?CashboxShift
    {
        return $this->findActiveByUserIdInternal($user_id, true);
    }

    public function create(int $user_id, float $initial_cash, ?string $notes = null): CashboxShift
    {
        $statement = $this->db->prepare(
            'INSERT INTO cashbox_shifts '
            . '(user_id, opened_at, initial_cash, expected_cash, status, balance_status, notes) '
            . 'VALUES (:user_id, :opened_at, :initial_cash, :expected_cash, "open", "pending", :notes)'
        );
        $statement->execute([
            'user_id' => $user_id,
            'opened_at' => date('Y-m-d H:i:s'),
            'initial_cash' => $initial_cash,
            'expected_cash' => $initial_cash,
            'notes' => $notes,
        ]);

        $shift = $this->findById((int) $this->db->lastInsertId());
        if ($shift === null) {
            throw new \RuntimeException('No se pudo recuperar el turno de caja recién creado.');
        }

        return $shift;
    }

    public function closeShift(
        int $shift_id,
        float $expected_cash,
        float $real_cash_declared,
        float $difference,
        string $balance_status,
        ?string $notes = null,
        float $expected_yape = 0.0,
        float $expected_plin = 0.0,
        float $expected_card = 0.0,
        float $expected_transfer = 0.0,
        ?float $real_yape_declared = null,
        ?float $real_plin_declared = null,
        ?float $real_card_declared = null,
        ?float $real_transfer_declared = null,
        ?float $difference_yape = null,
        ?float $difference_plin = null,
        ?float $difference_card = null,
        ?float $difference_transfer = null
    ): int {
        $statement = $this->db->prepare(
            'UPDATE cashbox_shifts SET '
            . 'closed_at = :closed_at, expected_cash = :expected_cash, '
            . 'real_cash_declared = :real_cash_declared, difference = :difference, '
            . 'expected_yape = :expected_yape, expected_plin = :expected_plin, '
            . 'expected_card = :expected_card, expected_transfer = :expected_transfer, '
            . 'real_yape_declared = :real_yape_declared, real_plin_declared = :real_plin_declared, '
            . 'real_card_declared = :real_card_declared, real_transfer_declared = :real_transfer_declared, '
            . 'difference_yape = :difference_yape, difference_plin = :difference_plin, '
            . 'difference_card = :difference_card, difference_transfer = :difference_transfer, '
            . 'status = "closed", balance_status = :balance_status, notes = COALESCE(:notes, notes) '
            . 'WHERE id = :id AND status = "open"'
        );
        $statement->execute([
            'id' => $shift_id,
            'closed_at' => date('Y-m-d H:i:s'),
            'expected_cash' => $expected_cash,
            'real_cash_declared' => $real_cash_declared,
            'difference' => $difference,
            'expected_yape' => $expected_yape,
            'expected_plin' => $expected_plin,
            'expected_card' => $expected_card,
            'expected_transfer' => $expected_transfer,
            'real_yape_declared' => $real_yape_declared ?? 0.0,
            'real_plin_declared' => $real_plin_declared ?? 0.0,
            'real_card_declared' => $real_card_declared ?? 0.0,
            'real_transfer_declared' => $real_transfer_declared ?? 0.0,
            'difference_yape' => $difference_yape ?? 0.0,
            'difference_plin' => $difference_plin ?? 0.0,
            'difference_card' => $difference_card ?? 0.0,
            'difference_transfer' => $difference_transfer ?? 0.0,
            'balance_status' => $balance_status,
            'notes' => $notes,
        ]);

        return $statement->rowCount();
    }

    public function updateExpectedCash(int $shift_id, float $expected_cash): int
    {
        return $this->updateExpectedByPaymentMethod($shift_id, 'cash', $expected_cash);
    }

    public function updateExpectedByPaymentMethod(int $shift_id, string $payment_method, float $expected_amount): int
    {
        $column = match ($payment_method) {
            'cash' => 'expected_cash',
            'yape' => 'expected_yape',
            'plin' => 'expected_plin',
            'card' => 'expected_card',
            'transfer' => 'expected_transfer',
            default => throw new \InvalidArgumentException('Método de pago inválido.'),
        };

        $statement = $this->db->prepare(
            'UPDATE cashbox_shifts SET ' . $column . ' = :expected_amount '
            . 'WHERE id = :id AND status = "open"'
        );
        $statement->execute([
            'id' => $shift_id,
            'expected_amount' => $expected_amount,
        ]);

        return $statement->rowCount();
    }

    private function findActiveByUserIdInternal(int $user_id, bool $for_update): ?CashboxShift
    {
        $sql = 'SELECT ' . self::SELECT_COLUMNS . ' FROM cashbox_shifts '
            . 'WHERE user_id = :user_id AND status = "open" '
            . 'ORDER BY id DESC LIMIT 1';

        if ($for_update && $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }

        $statement = $this->db->prepare($sql);
        $statement->execute(['user_id' => $user_id]);

        /** @var CashboxShiftRow|false $row */
        $row = $statement->fetch();
        if ($row === false) {
            return null;
        }

        return $this->mapRow(
            $row,
            $this->movement_repository->findByShiftId((int) $row['id'])
        );
    }

    /**
     * @param CashboxShiftRow $row
     * @param array<int, \App\Models\CashMovement> $movements
     */
    private function mapRow(array $row, array $movements): CashboxShift
    {
        return new CashboxShift(
            id: (int) $row['id'],
            user_id: (int) $row['user_id'],
            opened_at: $row['opened_at'],
            closed_at: $row['closed_at'],
            initial_cash: (float) $row['initial_cash'],
            expected_cash: (float) $row['expected_cash'],
            real_cash_declared: $row['real_cash_declared'] !== null ? (float) $row['real_cash_declared'] : null,
            difference: $row['difference'] !== null ? (float) $row['difference'] : null,
            status: $row['status'],
            balance_status: $row['balance_status'],
            notes: $row['notes'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            movements: $movements,
            expected_yape: (float) ($row['expected_yape'] ?? 0.0),
            expected_plin: (float) ($row['expected_plin'] ?? 0.0),
            expected_card: (float) ($row['expected_card'] ?? 0.0),
            expected_transfer: (float) ($row['expected_transfer'] ?? 0.0),
            real_yape_declared: $row['real_yape_declared'] !== null ? (float) $row['real_yape_declared'] : null,
            real_plin_declared: $row['real_plin_declared'] !== null ? (float) $row['real_plin_declared'] : null,
            real_card_declared: $row['real_card_declared'] !== null ? (float) $row['real_card_declared'] : null,
            real_transfer_declared: $row['real_transfer_declared'] !== null ? (float) $row['real_transfer_declared'] : null,
            difference_yape: $row['difference_yape'] !== null ? (float) $row['difference_yape'] : null,
            difference_plin: $row['difference_plin'] !== null ? (float) $row['difference_plin'] : null,
            difference_card: $row['difference_card'] !== null ? (float) $row['difference_card'] : null,
            difference_transfer: $row['difference_transfer'] !== null ? (float) $row['difference_transfer'] : null,
        );
    }
}
