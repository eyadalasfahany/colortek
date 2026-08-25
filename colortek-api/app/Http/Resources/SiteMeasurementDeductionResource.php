<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

final class SiteMeasurementDeductionResource extends JsonResource
{
    public function toArray($r): array
    {
        return ['count' => $this->count, 'length_m' => $this->length_m, 'width_m' => $this->width_m, 'sign' => $this->sign->value, 'label' => $this->label];
    }
}
