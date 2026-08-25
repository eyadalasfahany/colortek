<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Sample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sample */
final class SampleResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'parent_sample_id' => $this->parent_sample_id,
            'root_sample_id' => $this->root_sample_id,
            'attempt_number' => $this->attempt_number,
            'color' => $this->color,
            'texture' => $this->texture,
            'client_reference' => $this->client_reference,
            'size' => $this->size,
            'finish_requirement' => $this->finish_requirement,
            'notes' => $this->notes,
            'modification_reason' => $this->modification_reason,
            'status' => $this->status->value,
            'is_presale' => $this->is_presale,
            'approved_formula_id' => $this->approved_formula_id,
            'requested_at' => $this->requested_at->toIso8601String(),
            'needed_by' => $this->needed_by?->toDateString(),
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'project' => ProjectSummaryResource::make($this->whenLoaded('project')),
            'parent_sample' => SampleListResource::make($this->whenLoaded('parentSample')),
            'formulas' => FormulaResource::collection($this->whenLoaded('formulas')),
            'approvals' => SampleApprovalResource::collection($this->whenLoaded('approvals')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'approved_formula' => FormulaResource::make($this->whenLoaded('approvedFormula')),
        ];
    }
}
