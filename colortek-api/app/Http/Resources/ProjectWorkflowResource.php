<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectWorkflowResource extends JsonResource
{
    public function toArray($r): array
    {
        return ['stages' => $this->resource['stages'] ?? [], 'next_action' => $this->resource['next_action'] ?? null, 'current_stage' => $this->resource['current_stage'] ?? null];
    }
}
