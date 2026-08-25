<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Formula;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Formula */
final class FormulaResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'sample_id' => $this->sample_id,
            'version' => $this->version,
            'body' => $this->body,
            'status' => $this->status->value,
            'authored_at' => $this->authored_at?->toDateString(),
            'registered_at' => $this->registered_at?->toIso8601String(),
            'author_employee' => $this->whenLoaded('authorEmployee', fn () => $this->authorEmployee ? [
                'id' => $this->authorEmployee->id,
                'name' => $this->authorEmployee->name,
            ] : null),
            'registered_by' => UserResource::make($this->whenLoaded('registeredBy')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
