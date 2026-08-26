<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\WorkflowTransition;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WorkflowTransition */ final class WorkflowTransitionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($r): array
    {
        return ['id' => $this->id, 'from_task_definition_id' => $this->from_task_definition_id, 'to_task_definition_id' => $this->to_task_definition_id, 'condition' => $this->condition, 'join_mode' => $this->join_mode, 'sort_order' => $this->sort_order];
    }
}
