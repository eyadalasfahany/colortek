<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Client */
final class ClientResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'notes' => $this->notes,
            'odoo_client_id' => $this->odoo_client_id,
            'projects_count' => $this->whenCounted('projects'),
            'quotations_count' => $this->whenCounted('quotations'),
        ];
    }
}
