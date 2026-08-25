<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class ProjectResource extends JsonResource
{
    public function toArray($r): array
    {
        return ['id' => $this->id, 'reference' => $this->reference, 'name' => $this->name, 'stage' => $this->stage->value, 'status' => $this->status, 'site_ready' => $this->site_ready, 'block_all_when_site_not_ready' => $this->block_all_when_site_not_ready, 'client' => $this->whenLoaded('client', fn () => ['id' => $this->client?->id, 'name' => $this->client?->name]), 'sales_user' => UserSummaryResource::make($this->whenLoaded('salesUser')), 'quotation_id' => $this->quotation_id, 'created_at' => $this->created_at?->toIso8601String(), 'updated_at' => $this->updated_at?->toIso8601String()];
    }
}
