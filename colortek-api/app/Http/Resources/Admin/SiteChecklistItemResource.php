<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\SiteChecklistItem;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SiteChecklistItem */ final class SiteChecklistItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray($r): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'label_en' => $this->label_en, 'label_ar' => $this->label_ar, 'answer_type' => $this->answer_type->value, 'unit' => $this->unit, 'is_readiness_critical' => $this->is_readiness_critical, 'allows_note' => $this->allows_note, 'sort_order' => $this->sort_order, 'active' => $this->active];
    }
}
