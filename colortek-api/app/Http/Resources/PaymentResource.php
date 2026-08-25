<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Payment */
final class PaymentResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'quotation_id' => $this->quotation_id,
            'installment_number' => $this->installment_number,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method->value,
            'paid_at' => $this->paid_at->toDateString(),
            'status' => $this->status->value,
            'notes' => $this->notes,
            'journal_id' => $this->journal_id,
            'project' => ProjectSummaryResource::make($this->whenLoaded('project')),
            'quotation' => QuotationResource::make($this->whenLoaded('quotation')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
