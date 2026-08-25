<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\WorkflowInstance;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowInstance */ final class StalledInstanceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($r): array
    {
        $last = $this->tasks->where('status', 'completed')->sortByDesc('completed_at')->first();

        return ['id' => $this->id, 'template_code' => $this->template?->code, 'template_version' => $this->template?->version, 'project' => $this->whenLoaded('project', fn () => $this->project ? ['id' => $this->project->id, 'reference' => $this->project->reference, 'name' => $this->project->name] : null), 'last_completed_task' => $last ? ['id' => $last->id, 'reference' => $last->reference, 'title' => $last->localizedTitle()] : null, 'stalled_since' => ($last !== null ? $last->completed_at : $this->started_at)?->toIso8601String()];
    }
}
