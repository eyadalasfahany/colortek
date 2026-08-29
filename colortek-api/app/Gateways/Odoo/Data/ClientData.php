<?php

declare(strict_types=1);

namespace App\Gateways\Odoo\Data;

use App\Models\Client;

/**
 * A client as Odoo would describe it. Phase 1 fills this from the local
 * `clients` table; Phase 2 will fill it from an Odoo response.
 */
final readonly class ClientData
{
    public function __construct(
        public ?string $odooId,
        public string $name,
        public ?string $contactPerson = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?string $address = null,
        public ?int $localId = null,
    ) {}

    public static function fromModel(Client $client): self
    {
        return new self(
            odooId: $client->odoo_client_id === null ? null : (string) $client->odoo_client_id,
            name: $client->name,
            contactPerson: $client->contact_person,
            phone: $client->phone,
            email: $client->email,
            address: $client->address,
            localId: $client->id,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'odoo_id' => $this->odooId,
            'name' => $this->name,
            'contact_person' => $this->contactPerson,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'local_id' => $this->localId,
        ];
    }
}
