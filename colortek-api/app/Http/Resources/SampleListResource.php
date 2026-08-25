<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Sample;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Sample */
final class SampleListResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'status' => $this->status->value,
            'attempt_number' => $this->attempt_number,
            'color' => $this->color,
            'is_presale' => $this->is_presale,
            'client' => $this->whenLoaded('client', fn () => [
                'id' => $this->client->id,
                'name' => $this->client->name,
            ]),
            'project' => ProjectSummaryResource::make($this->whenLoaded('project')),
        ];
    }
}
