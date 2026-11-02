<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Guest;
use App\Repositories\GuestRepository;
use App\Support\DocumentLookup;
use DomainException;
use InvalidArgumentException;

final class GuestService
{
    private GuestRepository $guest_repository;
    private DocumentLookup $document_lookup;

    public function __construct(
        ?GuestRepository $guest_repository = null,
        ?DocumentLookup $document_lookup = null
    ) {
        $this->guest_repository = $guest_repository ?? new GuestRepository();
        $this->document_lookup = $document_lookup ?? new DocumentLookup();
    }

    /**
     * @return array{guest: ?Guest, external_data: ?array{first_name: ?string, last_name: ?string, company_name: ?string, address: ?string}}
     */
    public function lookup(string $document_type, string $document_number, bool $autocomplete = false): array
    {
        $existing = $this->guest_repository->findByDocumentNumber($document_number);
        if ($existing !== null) {
            return ['guest' => $existing, 'external_data' => null];
        }

        $external_data = null;
        if ($autocomplete) {
            $external_data = $this->document_lookup->lookup($document_type, $document_number);
        }

        return ['guest' => null, 'external_data' => $external_data];
    }

    public function getGuestById(int $id): ?Guest
    {
        return $this->guest_repository->findById($id);
    }

    public function createGuest(
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
        $allowed_types = ['DNI', 'RUC', 'CE', 'PASAPORTE'];
        if (!in_array(strtoupper($document_type), $allowed_types, true)) {
            throw new InvalidArgumentException("Tipo de documento inválido: {$document_type}");
        }

        $trimmed_doc = trim($document_number);
        if ($trimmed_doc === '') {
            throw new InvalidArgumentException("El número de documento no puede estar vacío.");
        }

        $existing = $this->guest_repository->findByDocumentNumber($trimmed_doc);
        if ($existing !== null) {
            return $existing;
        }

        return $this->guest_repository->create(
            document_type: strtoupper($document_type),
            document_number: $trimmed_doc,
            first_name: $first_name,
            last_name: $last_name,
            company_name: $company_name,
            phone: $phone,
            email: $email,
            address: $address,
            notes: $notes
        );
    }

    public function updateGuest(
        int $id,
        string $document_type,
        string $document_number,
        string $name,
        ?string $phone = null,
        ?string $email = null,
        ?string $address = null,
        ?string $notes = null
    ): Guest {
        $allowed_types = ['DNI', 'RUC', 'CE', 'PASAPORTE'];
        if (!in_array(strtoupper($document_type), $allowed_types, true)) {
            throw new InvalidArgumentException("Tipo de documento inválido: {$document_type}");
        }

        $trimmed_doc = trim($document_number);
        if ($trimmed_doc === '') {
            throw new InvalidArgumentException("El número de documento no puede estar vacío.");
        }

        $trimmed_name = trim($name);
        if ($trimmed_name === '') {
            throw new InvalidArgumentException("El nombre del huésped no puede estar vacío.");
        }

        $existing = $this->guest_repository->findByDocumentNumber($trimmed_doc);
        if ($existing !== null && $existing->id !== $id) {
            throw new DomainException("El documento {$trimmed_doc} ya está registrado para otro huésped.");
        }

        return $this->guest_repository->update(
            id: $id,
            document_type: strtoupper($document_type),
            document_number: $trimmed_doc,
            name: $trimmed_name,
            phone: $phone,
            email: $email,
            address: $address,
            notes: $notes
        );
    }

    /**
     * @return array<int, array{id: int, document_type: string, document_number: string, name: string, phone: ?string, email: ?string, address: ?string, notes: ?string, created_at: string, stays_count: int, total_spent: float}>
     */
    public function getGuestsCatalog(?string $search = null): array
    {
        $guests = ($search !== null && trim($search) !== '')
            ? $this->guest_repository->search(trim($search))
            : $this->guest_repository->findAll();

        return array_map(function (Guest $g): array {
            $stays = $this->guest_repository->getStaysHistory($g->id);
            $total_spent = 0.0;
            foreach ($stays as $stay) {
                $price_val = $stay['applied_price'] ?? 0;
                $total_spent += is_numeric($price_val) ? (float) $price_val : 0.0;
            }

            return [
                'id' => $g->id,
                'document_type' => $g->document_type,
                'document_number' => $g->document_number,
                'name' => $g->name,
                'phone' => $g->phone,
                'email' => $g->email,
                'address' => $g->address,
                'notes' => $g->notes,
                'created_at' => $g->created_at,
                'stays_count' => count($stays),
                'total_spent' => $total_spent,
            ];
        }, $guests);
    }

    /**
     * @return array{guest: Guest, stays_count: int, total_spent: float, stays: array<int, array<string, mixed>>}|null
     */
    public function getGuestProfile(int $guest_id): ?array
    {
        $guest = $this->guest_repository->findById($guest_id);
        if ($guest === null) {
            return null;
        }

        $stays = $this->guest_repository->getStaysHistory($guest_id);
        $total_spent = 0.0;
        foreach ($stays as $stay) {
            $price_val = $stay['applied_price'] ?? 0;
            $total_spent += is_numeric($price_val) ? (float) $price_val : 0.0;
        }

        return [
            'guest' => $guest,
            'stays_count' => count($stays),
            'total_spent' => $total_spent,
            'stays' => $stays,
        ];
    }

    /**
     * @return array<int, Guest>
     */
    public function search(string $term): array
    {
        if (trim($term) === '') {
            return [];
        }
        return $this->guest_repository->search($term);
    }
}
