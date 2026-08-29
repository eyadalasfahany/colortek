<?php

declare(strict_types=1);

namespace App\Gateways\Odoo\Data;

use App\Models\Quotation;

/** A quotation as Odoo would describe it. `specs/02-architecture.md` §5. */
final readonly class QuotationData
{
    public function __construct(
        public string $number,
        public string $totalValue,
        public string $currency,
        public string $status,
        public ?string $odooId = null,
        public ?int $clientLocalId = null,
        public ?int $localId = null,
    ) {}

    public static function fromModel(Quotation $quotation): self
    {
        return new self(
            number: $quotation->number,
            totalValue: (string) $quotation->total_value,
            currency: $quotation->currency,
            status: $quotation->status->value,
            odooId: $quotation->odoo_quotation_id === null ? null : (string) $quotation->odoo_quotation_id,
            clientLocalId: $quotation->client_id,
            localId: $quotation->id,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'total_value' => $this->totalValue,
            'currency' => $this->currency,
            'status' => $this->status,
            'odoo_id' => $this->odooId,
            'client_local_id' => $this->clientLocalId,
            'local_id' => $this->localId,
        ];
    }
}
