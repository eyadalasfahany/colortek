<?php

declare(strict_types=1);

namespace App\Gateways\Odoo;

use App\Gateways\Odoo\Data\ClientData;
use App\Gateways\Odoo\Data\JournalData;
use App\Gateways\Odoo\Data\PaymentData;
use App\Gateways\Odoo\Data\PushResult;
use App\Gateways\Odoo\Data\QuotationData;
use App\Models\Client;
use App\Models\Journal;
use App\Models\OdooSyncLog;
use App\Models\Payment;
use App\Models\Quotation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Phase 1 driver. Reads the local tables and records — rather than sends — every
 * push, so the intended payloads are auditable before the real integration
 * exists. `specs/13-odoo-gateway-and-seed-data.md` §1.
 */
final class FakeOdooGateway implements OdooGateway
{
    private const DRIVER = 'fake';

    public function findClient(string $odooId): ?ClientData
    {
        $client = Client::query()->where('odoo_client_id', $odooId)->first();

        return $client === null ? null : ClientData::fromModel($client);
    }

    /** @return Collection<int, ClientData> */
    public function searchClients(string $query): Collection
    {
        return Client::query()
            ->where(function ($builder) use ($query): void {
                $builder->where('name', 'like', "%{$query}%")
                    ->orWhere('contact_person', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->map(fn (Client $client): ClientData => ClientData::fromModel($client))
            ->values();
    }

    public function findQuotation(string $number): ?QuotationData
    {
        $quotation = Quotation::query()->where('number', $number)->first();

        return $quotation === null ? null : QuotationData::fromModel($quotation);
    }

    public function pushJournal(JournalData $journal): PushResult
    {
        return $this->record(
            operation: 'push_journal',
            subjectType: Journal::class,
            subjectId: $journal->journalId,
            // The status is part of the key: a journal legitimately pushes once
            // on submit and again once accounting has processed it.
            idempotencyKey: sprintf('journal:%d:%s', $journal->journalId, $journal->status),
            payload: $journal->toArray(),
        );
    }

    public function pushPaymentConfirmation(PaymentData $payment): PushResult
    {
        return $this->record(
            operation: 'push_payment_confirmation',
            subjectType: Payment::class,
            subjectId: $payment->paymentId,
            idempotencyKey: sprintf('payment:%d:%s', $payment->paymentId, $payment->status),
            payload: $payment->toArray(),
        );
    }

    /** @param array<string, mixed> $payload */
    private function record(
        string $operation,
        string $subjectType,
        int $subjectId,
        string $idempotencyKey,
        array $payload,
    ): PushResult {
        $existing = OdooSyncLog::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($existing !== null) {
            return PushResult::duplicate($idempotencyKey, $existing->odoo_reference);
        }

        OdooSyncLog::query()->create([
            'operation' => $operation,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'idempotency_key' => $idempotencyKey,
            'driver' => self::DRIVER,
            'status' => 'simulated',
            'payload' => $payload,
            'response' => null,
            'actor_user_id' => Auth::id(),
        ]);

        return PushResult::simulated($idempotencyKey);
    }
}
