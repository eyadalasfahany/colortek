<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Formula */
class FormulaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'version' => $this->version,
            'body' => $this->body,
            'status' => $this->status->value,
            'authored_at' => $this->authored_at?->toDateString(),
            'registered_at' => $this->registered_at?->toIso8601String(),
            'author_employee' => $this->whenLoaded('authorEmployee', fn () => [
                'id' => $this->authorEmployee->id,
                'name' => $this->authorEmployee->name,
            ]),
            'registered_by' => UserResource::make($this->whenLoaded('registeredBy')),
        ];
    }
}
