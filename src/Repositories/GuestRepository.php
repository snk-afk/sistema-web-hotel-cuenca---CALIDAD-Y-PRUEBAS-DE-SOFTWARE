<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Database\Database;
use App\Models\Guest;
use PDO;

final class GuestRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function findById(int $id): ?Guest
    {
        $stmt = $this->db->prepare(
            'SELECT id, document_type, document_number, name, address, phone, email, notes, created_at, updated_at
             FROM guests
             WHERE id = :id'
        );

        $stmt->execute(['id' => $id]);
        /** @var array{id: int|string, document_type: string, document_number: string, name: string, phone: ?string, email: ?string, address: ?string, notes: ?string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $parts = explode(' ', $row['name'], 2);
        $first_name = $parts[0] ?? $row['name'];
        $last_name = $parts[1] ?? '';

        return new Guest(
            id: (int) $row['id'],
            document_type: $row['document_type'],
            document_number: $row['document_number'],
            name: $row['name'],
            phone: $row['phone'],
            email: $row['email'],
            address: $row['address'],
            notes: $row['notes'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            first_name: $first_name,
            last_name: $last_name,
            company_name: $row['name'],
        );
    }

    public function findByDocumentNumber(string $document_number): ?Guest
    {
        $stmt = $this->db->prepare(
            'SELECT id, document_type, document_number, name, address, phone, email, notes, created_at, updated_at
             FROM guests
             WHERE document_number = :document_number'
        );

        $stmt->execute(['document_number' => $document_number]);
        /** @var array{id: int|string, document_type: string, document_number: string, name: string, phone: ?string, email: ?string, address: ?string, notes: ?string, created_at: string, updated_at: string}|false $row */
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        $parts = explode(' ', $row['name'], 2);
        $first_name = $parts[0] ?? $row['name'];
        $last_name = $parts[1] ?? '';

        return new Guest(
            id: (int) $row['id'],
            document_type: $row['document_type'],
            document_number: $row['document_number'],
            name: $row['name'],
            phone: $row['phone'],
            email: $row['email'],
            address: $row['address'],
            notes: $row['notes'],
            created_at: $row['created_at'],
            updated_at: $row['updated_at'],
            first_name: $first_name,
            last_name: $last_name,
            company_name: $row['name'],
        );
    }

    /**
     * @return array<int, Guest>
     */
    public function findAll(): array
    {
        $stmt = $this->db->query(
            'SELECT id, document_type, document_number, name, address, phone, email, notes, created_at, updated_at
             FROM guests
             ORDER BY id DESC'
        );

        if ($stmt === false) {
            return [];
        }

        /** @var array<int, array{id: int|string, document_type: string, document_number: string, name: string, phone: ?string, email: ?string, address: ?string, notes: ?string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): Guest {
                $parts = explode(' ', $row['name'], 2);
                $first_name = $parts[0] ?? $row['name'];
                $last_name = $parts[1] ?? '';

                return new Guest(
                    id: (int) $row['id'],
                    document_type: $row['document_type'],
                    document_number: $row['document_number'],
                    name: $row['name'],
                    phone: $row['phone'],
                    email: $row['email'],
                    address: $row['address'],
                    notes: $row['notes'],
                    created_at: $row['created_at'],
                    updated_at: $row['updated_at'],
                    first_name: $first_name,
                    last_name: $last_name,
                    company_name: $row['name'],
                );
            },
            $rows
        );
    }

    /**
     * @return array<int, Guest>
     */
    public function search(string $term): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, document_type, document_number, name, address, phone, email, notes, created_at, updated_at
             FROM guests
             WHERE document_number LIKE :document_term
                OR name LIKE :name_term
                OR phone LIKE :phone_term
                OR email LIKE :email_term
             ORDER BY id DESC
             LIMIT 30'
        );

        $like = '%' . $term . '%';
        $stmt->execute([
            'document_term' => $like,
            'name_term' => $like,
            'phone_term' => $like,
            'email_term' => $like,
        ]);
        /** @var array<int, array{id: int|string, document_type: string, document_number: string, name: string, phone: ?string, email: ?string, address: ?string, notes: ?string, created_at: string, updated_at: string}> $rows */
        $rows = $stmt->fetchAll();

        return array_map(
            function (array $row): Guest {
                $parts = explode(' ', $row['name'], 2);
                $first_name = $parts[0] ?? $row['name'];
                $last_name = $parts[1] ?? '';

                return new Guest(
                    id: (int) $row['id'],
                    document_type: $row['document_type'],
                    document_number: $row['document_number'],
                    name: $row['name'],
                    phone: $row['phone'],
                    email: $row['email'],
                    address: $row['address'],
                    notes: $row['notes'],
                    created_at: $row['created_at'],
                    updated_at: $row['updated_at'],
                    first_name: $first_name,
                    last_name: $last_name,
                    company_name: $row['name'],
                );
            },
            $rows
        );
    }

    public function create(
        string $document_type,
        string $document_number,
        ?string $first_name = null,
        ?string $last_name = null,
        ?string $company_name = null,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
        ?string $notes = null
    ): Guest {
        $name = trim(($first_name ?? '') . ' ' . ($last_name ?? ''));
        if ($name === '' && $company_name !== null) {
            $name = $company_name;
        }
        if ($name === '') {
            $name = 'Huésped Registrado';
        }

        $stmt = $this->db->prepare(
            'INSERT INTO guests (document_type, document_number, name, address, phone, email, notes)
             VALUES (:document_type, :document_number, :name, :address, :phone, :email, :notes)'
        );

        $stmt->execute([
            'document_type' => $document_type,
            'document_number' => $document_number,
            'name' => $name,
            'address' => $address,
            'phone' => $phone,
            'email' => $email,
            'notes' => $notes,
        ]);

        $id = (int) $this->db->lastInsertId();
        /** @var Guest */
        return $this->findById($id);
    }

    public function update(
        int $id,
        string $document_type,
        string $document_number,
        string $name,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
        ?string $notes = null
    ): Guest {
        $stmt = $this->db->prepare(
            'UPDATE guests
             SET document_type = :document_type,
                 document_number = :document_number,
                 name = :name,
                 phone = :phone,
                 email = :email,
                 address = :address,
                 notes = :notes
             WHERE id = :id'
        );

        $stmt->execute([
            'id' => $id,
            'document_type' => $document_type,
            'document_number' => $document_number,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'address' => $address,
            'notes' => $notes,
        ]);

        /** @var Guest */
        return $this->findById($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getStaysHistory(int $guest_id): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.id AS stay_id, s.room_id, r.number AS room_number, rc.name AS category_name,
                    rt.name AS tariff_name, s.check_in_at, s.expected_check_out_at, s.actual_check_out_at,
                    s.applied_price, s.status AS stay_status, f.id AS folio_id, f.status AS folio_status
             FROM stays s
             LEFT JOIN rooms r ON s.room_id = r.id
             LEFT JOIN room_categories rc ON r.room_category_id = rc.id
             LEFT JOIN room_tariffs rt ON s.room_tariff_id = rt.id
             LEFT JOIN folios f ON f.stay_id = s.id
             WHERE s.guest_id = :guest_id
             ORDER BY s.id DESC'
        );

        $stmt->execute(['guest_id' => $guest_id]);
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }
}
