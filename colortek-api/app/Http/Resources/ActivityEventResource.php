<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ActivityEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = $request->user()?->locale ?? app()->getLocale();
        $payload = $this->payload ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'severity' => $this->severity->value,
            'message' => $locale === 'ar' ? $this->message_ar : $this->message_en,
            'actor' => UserSummaryResource::make($this->whenLoaded('actor')),
            'department' => DepartmentResource::make($this->whenLoaded('department')),
            'project' => ProjectSummaryResource::make($this->whenLoaded('project')),
            'link' => $payload['route'] ?? null,
            'link_params' => $payload['params'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
