<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CorrectiveAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CorrectiveAction */
final class CorrectiveActionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'site_visit_id' => $this->site_visit_id,
            'checklist_item_id' => $this->checklist_item_id,
            'description' => $this->description,
            'responsible_party' => $this->responsible_party->value,
            'status' => $this->status->value,
            'resolution_note' => $this->resolution_note,
            'task_id' => $this->task_id,
        ];
    }
}
