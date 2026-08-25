<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SiteChecklistItem;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SiteChecklistItem */
final class SiteChecklistItemResource extends JsonResource
{
    public function toArray($r): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'label' => $this->localizedLabel($r->getPreferredLanguage(['en', 'ar']) ?? 'en'), 'label_ar' => $this->label_ar, 'answer_type' => $this->answer_type->value, 'is_readiness_critical' => $this->is_readiness_critical, 'sort_order' => $this->sort_order];
    }
}
