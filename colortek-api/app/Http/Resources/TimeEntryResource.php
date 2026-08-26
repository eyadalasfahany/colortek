<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TimeEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TimeEntry */
final class TimeEntryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? [
                'id' => $this->employee->id,
                'name' => $this->employee->name,
            ] : null),
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'seconds' => $this->seconds,
            'source' => $this->source,
            'needs_review' => $this->needs_review,
            'note' => $this->note,
        ];
    }
}
