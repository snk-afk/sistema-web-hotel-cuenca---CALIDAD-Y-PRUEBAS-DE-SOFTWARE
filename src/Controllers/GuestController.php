<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\GuestService;
use DomainException;
use InvalidArgumentException;

final class GuestController
{
    private GuestService $guest_service;

    public function __construct(?GuestService $guest_service = null)
    {
        $this->guest_service = $guest_service ?? new GuestService();
    }

    public function index(): void
    {
        $search = is_string($_GET['search'] ?? null) ? $_GET['search'] : null;
        $catalog = $this->guest_service->getGuestsCatalog($search);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => $catalog,
        ]);
    }

    public function show(int $id): void
    {
        $profile = $this->guest_service->getGuestProfile($id);

        if ($profile === null) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Huésped no encontrado.']);
            return;
        }

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => [
                'id' => $profile['guest']->id,
                'document_type' => $profile['guest']->document_type,
                'document_number' => $profile['guest']->document_number,
                'name' => $profile['guest']->name,
                'phone' => $profile['guest']->phone,
                'email' => $profile['guest']->email,
                'address' => $profile['guest']->address,
                'notes' => $profile['guest']->notes,
                'created_at' => $profile['guest']->created_at,
                'stays_count' => $profile['stays_count'],
                'total_spent' => $profile['total_spent'],
                'stays' => $profile['stays'],
            ],
        ]);
    }

    public function lookup(): void
    {
        $document_type = is_string($_GET['document_type'] ?? null) ? $_GET['document_type'] : 'DNI';
        $document_number = is_string($_GET['document_number'] ?? null) ? $_GET['document_number'] : '';
        $autocomplete = isset($_GET['autocomplete']) && ($_GET['autocomplete'] === 'true' || $_GET['autocomplete'] === '1');

        if (trim($document_number) === '') {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Número de documento requerido.']);
            return;
        }

        $result = $this->guest_service->lookup($document_type, $document_number, $autocomplete);

        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'success',
            'data' => [
                'guest' => $result['guest'],
                'external_data' => $result['external_data'],
            ],
        ]);
    }

    public function create(): void
    {
        /** @var string|false $raw_input */
        $raw_input = file_get_contents('php://input');
        /** @var array<string, mixed>|null $body */
        $body = is_string($raw_input) && $raw_input !== '' ? json_decode($raw_input, true) : $_POST;

        if (!is_array($body)) {
            $body = [];
        }

        $id = is_numeric($body['id'] ?? null) ? (int) $body['id'] : null;
        $document_type = is_string($body['document_type'] ?? null) ? $body['document_type'] : 'DNI';
        $document_number = is_string($body['document_number'] ?? null) ? $body['document_number'] : '';
        $name = is_string($body['name'] ?? null) ? $body['name'] : (is_string($body['first_name'] ?? null) ? $body['first_name'] : '');
        $first_name = is_string($body['first_name'] ?? null) ? $body['first_name'] : null;
        $last_name = is_string($body['last_name'] ?? null) ? $body['last_name'] : null;
        $company_name = is_string($body['company_name'] ?? null) ? $body['company_name'] : null;
        $phone = is_string($body['phone'] ?? null) ? $body['phone'] : null;
        $email = is_string($body['email'] ?? null) ? $body['email'] : null;
        $address = is_string($body['address'] ?? null) ? $body['address'] : null;
        $notes = is_string($body['notes'] ?? null) ? $body['notes'] : null;

        try {
            if ($id !== null && $id > 0) {
                $guest = $this->guest_service->updateGuest(
                    id: $id,
                    document_type: $document_type,
                    document_number: $document_number,
                    name: $name !== '' ? $name : ($company_name ?? 'Huésped Registrado'),
                    phone: $phone,
                    email: $email,
                    address: $address,
                    notes: $notes
                );
                $msg = "Huésped {$guest->name} actualizado exitosamente";
            } else {
                $guest = $this->guest_service->createGuest(
                    document_type: $document_type,
                    document_number: $document_number,
                    first_name: $first_name,
                    last_name: $last_name,
                    company_name: $company_name,
                    phone: $phone,
                    email: $email,
                    address: $address,
                    notes: $notes
                );
                http_response_code(201);
                $msg = "Huésped {$guest->name} registrado exitosamente";
            }

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'message' => $msg,
                'data' => $guest,
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (DomainException $e) {
            http_response_code(409);
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
}
