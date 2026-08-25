<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Project */
final class ProjectListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'reference' => $this->reference, 'name' => $this->name, 'stage' => $this->stage->value, 'status' => $this->status, 'site_ready' => $this->site_ready, 'client_name' => $this->whenLoaded('client', fn () => $this->client?->name), 'sales_user' => UserSummaryResource::make($this->whenLoaded('salesUser'))];
    }
}
