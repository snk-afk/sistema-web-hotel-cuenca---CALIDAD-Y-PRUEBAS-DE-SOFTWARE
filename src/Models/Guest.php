<?php

declare(strict_types=1);

namespace App\Models;

final readonly class Guest
{
    public function __construct(
        public int $id,
        public string $document_type,
        public string $document_number,
        public string $name,
        public ?string $phone,
        public ?string $email,
        public ?string $address,
        public ?string $notes,
        public string $created_at,
        public string $updated_at,
        public ?string $first_name = null,
        public ?string $last_name = null,
        public ?string $company_name = null,
    ) {
    }

    public function getFullName(): string
    {
        if (trim($this->name) !== '') {
            return $this->name;
        }
        $fullName = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        if ($fullName !== '') {
            return $fullName;
        }
        return $this->company_name ?? 'Huésped Registrado';
    }
}
