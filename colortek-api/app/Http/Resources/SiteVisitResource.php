<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SiteVisit */
final class SiteVisitResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'reference' => $this->reference, 'project_id' => $this->project_id, 'visit_number' => $this->visit_number, 'readiness' => $this->readiness->value, 'submitted_at' => $this->submitted_at?->toIso8601String(), 'is_submitted' => $this->isSubmitted(), 'answers' => SiteVisitAnswerResource::collection($this->whenLoaded('answers')), 'measurements' => SiteMeasurementResource::collection($this->whenLoaded('measurements')), 'attachments' => AttachmentResource::collection($this->whenLoaded('attachments'))];
    }
}
