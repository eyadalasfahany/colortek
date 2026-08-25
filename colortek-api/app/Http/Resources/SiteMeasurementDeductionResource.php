<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SiteMeasurementDeduction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SiteMeasurementDeduction */
final class SiteMeasurementDeductionResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['count' => $this->count, 'length_m' => $this->length_m, 'width_m' => $this->width_m, 'sign' => $this->sign->value, 'label' => $this->label];
    }
}
