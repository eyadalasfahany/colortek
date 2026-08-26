<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CrewLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CrewLog */
final class CrewLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'log_date' => $this->log_date instanceof \DateTimeInterface
                ? $this->log_date->format('Y-m-d')
                : $this->log_date,
            'task_id' => $this->task_id,
            'work_done' => $this->work_done,
            'weather_note' => $this->weather_note,
            'issues' => $this->issues,
            'status' => $this->status,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'supervisor' => UserSummaryResource::make($this->whenLoaded('supervisor')),
            'members' => CrewLogMemberResource::collection($this->whenLoaded('members')),
        ];
    }
}
