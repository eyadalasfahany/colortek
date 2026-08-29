<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Quotation */
final class QuotationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'client_id' => $this->client_id,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'total_value' => (string) $this->total_value,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'locked_at' => $this->locked_at?->toIso8601String(),
            'odoo_quotation_id' => $this->odoo_quotation_id,
        ];
    }
}
