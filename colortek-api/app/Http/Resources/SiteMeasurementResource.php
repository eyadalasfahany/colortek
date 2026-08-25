<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SiteMeasurementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'page_number' => $this->page_number,
            'line_number' => $this->line_number,
            'element_name' => $this->element_name,
            'element_group_id' => $this->element_group_id,
            'height_m' => $this->height_m,
            'length_m' => $this->length_m,
            'width_m' => $this->width_m,
            'thickness_m' => $this->thickness_m,
            'diameter_m' => $this->diameter_m,
            'other_note' => $this->other_note,
            'area_sqm' => $this->area_sqm,
            'verified' => $this->verified,
            'sort_order' => $this->sort_order,
            'deductions' => SiteMeasurementDeductionResource::collection($this->whenLoaded('deductions')),
        ];
    }
}
