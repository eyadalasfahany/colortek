<?php

declare(strict_types=1);

namespace App\Gateways\Odoo\Data;

use App\Models\Payment;

/** The payload of a payment-confirmation push. */
final readonly class PaymentData
{
    public function __construct(
        public int $paymentId,
        public ?string $projectReference,
        public ?string $quotationNumber,
        public int $installmentNumber,
        public string $amount,
        public string $currency,
        public string $method,
        public string $paidAt,
        public string $status,
        public ?string $confirmedBy = null,
    ) {}

    public static function fromModel(Payment $payment): self
    {
        $payment->loadMissing(['project', 'quotation', 'confirmedBy']);

        return new self(
            paymentId: $payment->id,
            projectReference: $payment->project?->reference,
            quotationNumber: $payment->quotation?->number,
            installmentNumber: (int) $payment->installment_number,
            amount: (string) $payment->amount,
            currency: $payment->currency,
            method: $payment->method->value,
            paidAt: $payment->paid_at->toDateString(),
            status: $payment->status->value,
            confirmedBy: $payment->confirmedBy?->name,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'project_reference' => $this->projectReference,
            'quotation_number' => $this->quotationNumber,
            'installment_number' => $this->installmentNumber,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method,
            'paid_at' => $this->paidAt,
            'status' => $this->status,
            'confirmed_by' => $this->confirmedBy,
        ];
    }
}
