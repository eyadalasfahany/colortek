<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\WorkflowTemplate;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowTemplate */
final class WorkflowTemplateResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        /** @var WorkflowTemplate $template */
        $template = $this->resource;

        return [
            'id' => $template->id,
            'code' => $template->code,
            'version' => $template->version,
            'name_en' => $template->name_en,
            'name_ar' => $template->name_ar,
            'scope' => $template->scope,
            'is_active' => $template->is_active,
            'is_draft' => $template->published_at === null,
            'published_at' => $template->published_at?->toIso8601String(),
            'definitions' => WorkflowTaskDefinitionResource::collection($this->whenLoaded('definitions')),
            'transitions' => WorkflowTransitionResource::collection($this->whenLoaded('transitions')),
        ];
    }
}
