<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\Holiday;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Holiday */
final class HolidayResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($request): array
    {
        /** @var Holiday $holiday */
        $holiday = $this->resource;

        return [
            'id' => $holiday->id,
            'date' => $holiday->date->format('Y-m-d'),
            'name' => $holiday->getTranslations('name'),
            'type' => $holiday->type->value,
            'is_recurring' => $holiday->is_recurring,
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id' => $holiday->createdBy->id,
                'name' => $holiday->createdBy->name,
            ]),
        ];
    }
}
