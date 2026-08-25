<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin object */
final class ProjectWorkflowResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['stages' => $this->resource['stages'] ?? [], 'next_action' => $this->resource['next_action'] ?? null, 'current_stage' => $this->resource['current_stage'] ?? null];
    }
}
