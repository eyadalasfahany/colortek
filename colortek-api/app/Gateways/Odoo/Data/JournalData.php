<?php

declare(strict_types=1);

namespace App\Gateways\Odoo\Data;

use App\Models\Journal;
use App\Models\Payment;

/** The payload of a journal push: the header plus one line per payment. */
final readonly class JournalData
{
    /** @param list<array<string, mixed>> $lines */
    public function __construct(
        public int $journalId,
        public string $journalDate,
        public string $status,
        public string $totalAmount,
        public array $lines,
        public ?string $preparedBy = null,
        public ?string $odooJournalRef = null,
    ) {}

    public static function fromModel(Journal $journal): self
    {
        $journal->loadMissing(['payments.project', 'preparedBy']);

        // The snapshot, not the live amount: a later edit must not silently
        // change what was already sent. Workflow 01 §5.8.
        $snapshots = $journal->payments()
            ->pluck('journal_payment.amount_snapshot', 'payments.id');

        $lines = $journal->payments
            ->map(fn (Payment $payment): array => [
                'payment_id' => $payment->id,
                'project_reference' => $payment->project?->reference,
                'installment_number' => (int) $payment->installment_number,
                'amount' => (string) $snapshots->get($payment->id),
                'currency' => $payment->currency,
            ])
            ->values()
            ->all();

        return new self(
            journalId: $journal->id,
            journalDate: $journal->journal_date->toDateString(),
            status: $journal->status->value,
            totalAmount: (string) $journal->total_amount,
            lines: $lines,
            preparedBy: $journal->preparedBy?->name,
            odooJournalRef: $journal->odoo_journal_ref,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'journal_id' => $this->journalId,
            'journal_date' => $this->journalDate,
            'status' => $this->status,
            'total_amount' => $this->totalAmount,
            'prepared_by' => $this->preparedBy,
            'odoo_journal_ref' => $this->odooJournalRef,
            'lines' => $this->lines,
        ];
    }
}
