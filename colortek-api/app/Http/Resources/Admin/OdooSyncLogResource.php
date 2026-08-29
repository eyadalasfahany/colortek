<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\OdooSyncLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin OdooSyncLog */
final class OdooSyncLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation' => $this->operation,
            'subject_type' => $this->subject_type === null ? null : class_basename($this->subject_type),
            'subject_id' => $this->subject_id,
            'idempotency_key' => $this->idempotency_key,
            'driver' => $this->driver,
            'status' => $this->status,
            'payload' => $this->payload,
            'response' => $this->response,
            'odoo_reference' => $this->odoo_reference,
            'error' => $this->error,
            'actor' => $this->whenLoaded('actor', fn () => [
                'id' => $this->actor->id,
                'name' => $this->actor->name,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
