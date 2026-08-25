<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Sample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sample */
class SampleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'color' => $this->color,
            'texture' => $this->texture,
            'size' => $this->size,
            'attempt_number' => $this->attempt_number,
            'is_presale' => $this->is_presale,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'parent_sample_id' => $this->parent_sample_id,
            'approved_formula_id' => $this->approved_formula_id,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'project' => ProjectListResource::make($this->whenLoaded('project')),
            'formulas' => FormulaResource::collection($this->whenLoaded('formulas')),
            'approvals' => SampleApprovalResource::collection($this->whenLoaded('approvals')),
        ];
    }
}
