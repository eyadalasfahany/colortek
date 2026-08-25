<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\WorkflowTaskDefinition;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowTaskDefinition */
final class WorkflowTaskDefinitionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        /** @var WorkflowTaskDefinition $definition */
        $definition = $this->resource;

        return [
            'id' => $definition->id,
            'code' => $definition->code,
            'title_en' => $definition->title_en,
            'title_ar' => $definition->title_ar,
            'instructions_en' => $definition->instructions_en,
            'instructions_ar' => $definition->instructions_ar,
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $definition->department->id,
                'code' => $definition->department->code,
                'name' => $definition->department->getTranslation('name', 'en'),
            ]),
            'sla_minutes' => $definition->sla_minutes,
            'escalate_after_minutes' => $definition->escalate_after_minutes,
            'priority' => $definition->priority?->value,
            'is_entry_point' => $definition->is_entry_point,
        ];
    }
}
