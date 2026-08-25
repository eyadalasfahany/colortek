<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SiteMeasurement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SiteMeasurement */
final class SiteMeasurementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['page_number' => $this->page_number, 'line_number' => $this->line_number, 'element_name' => $this->element_name, 'element_group_id' => $this->element_group_id, 'length_m' => $this->length_m, 'width_m' => $this->width_m, 'sort_order' => $this->sort_order, 'deductions' => SiteMeasurementDeductionResource::collection($this->whenLoaded('deductions'))];
    }
}
