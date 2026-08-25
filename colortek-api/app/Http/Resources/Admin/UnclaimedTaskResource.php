<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Task;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Task */ final class UnclaimedTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($r): array
    {
        return ['id' => $this->id, 'reference' => $this->reference, 'title' => $this->localizedTitle(), 'department' => $this->whenLoaded('department', fn () => ['id' => $this->department->id, 'code' => $this->department->code, 'name' => $this->department->getTranslation('name', 'en')]), 'due_at' => $this->due_at?->toIso8601String(), 'minutes_past_due' => $this->due_at ? abs((int) now()->diffInMinutes($this->due_at, false)) : 0, 'project' => $this->whenLoaded('project', fn () => $this->project ? ['id' => $this->project->id, 'reference' => $this->project->reference] : null)];
    }
}
