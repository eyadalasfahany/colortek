<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Journal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Journal */
final class JournalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'journal_date' => $this->journal_date->toDateString(),
            'status' => $this->status->value,
            'total_amount' => $this->total_amount,
            'submitted_at' => $this->submitted_at?->toIso8601String(),
            'accounted_at' => $this->accounted_at?->toIso8601String(),
            'payments_count' => $this->whenCounted('payments'),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
