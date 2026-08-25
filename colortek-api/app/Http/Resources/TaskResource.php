<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */
class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = $request->getPreferredLanguage(['en', 'ar']) ?? app()->getLocale();

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->localizedTitle($locale),
            'instructions' => is_array($this->instructions)
                ? ($this->instructions[$locale] ?? $this->instructions['en'] ?? null)
                : $this->instructions,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'due_at' => $this->due_at?->toIso8601String(),
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->is_overdue,
            'department' => DepartmentResource::make($this->whenLoaded('department')),
            'claimant' => UserResource::make($this->whenLoaded('claimant')),
            'project_id' => $this->project_id,
        ];
    }
}
