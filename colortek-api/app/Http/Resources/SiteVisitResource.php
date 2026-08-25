<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SiteVisitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'project_id' => $this->project_id,
            'visit_number' => $this->visit_number,
            'parent_visit_id' => $this->parent_visit_id,
            'visited_on' => $this->visited_on?->toDateString(),
            'project_name_on_form' => $this->project_name_on_form,
            'address_on_form' => $this->address_on_form,
            'quotation_number_on_form' => $this->quotation_number_on_form,
            'client_reference_note' => $this->client_reference_note,
            'client_signatory_name' => $this->client_signatory_name,
            'general_notes' => $this->general_notes,
            'readiness' => $this->readiness->value,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'is_submitted' => $this->isSubmitted(),
            'engineer' => UserResource::make($this->whenLoaded('engineer')),
            'answers' => SiteVisitAnswerResource::collection($this->whenLoaded('answers')),
            'measurements' => SiteMeasurementResource::collection($this->whenLoaded('measurements')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
