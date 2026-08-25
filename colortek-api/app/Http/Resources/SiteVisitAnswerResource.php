<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SiteVisitAnswer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SiteVisitAnswer */
final class SiteVisitAnswerResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['code' => $this->whenLoaded('checklistItem', fn () => $this->checklistItem?->code), 'answer_value' => $this->answer_value, 'passed' => $this->passed, 'note' => $this->note];
    }
}
