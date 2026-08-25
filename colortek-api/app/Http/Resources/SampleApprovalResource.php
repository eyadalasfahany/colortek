<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\SampleApproval */
class SampleApprovalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'decision' => $this->decision?->value,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'form_generated_at' => $this->form_generated_at?->toIso8601String(),
            'comments' => $this->comments,
            'client_signatory_name' => $this->client_signatory_name,
        ];
    }
}
