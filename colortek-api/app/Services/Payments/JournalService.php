<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\JournalStatus;
use App\Enums\PaymentStatus;
use App\Events\JournalReopened;
use App\Events\JournalSubmitted;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class JournalService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function openJournalForDate(CarbonImmutable $date): Journal
    {
        $journal = Journal::query()
            ->whereDate('journal_date', $date->toDateString())
            ->first();

        if ($journal !== null) {
            return $journal;
        }

        try {
            return Journal::query()->create([
                'journal_date' => $date->toDateString(),
                'status' => JournalStatus::Open,
                'total_amount' => 0,
            ]);
        } catch (QueryException) {
            return Journal::query()
                ->whereDate('journal_date', $date->toDateString())
                ->firstOrFail();
        }
    }

    public function attachPayment(Journal $journal, Payment $payment): void
    {
        if ($journal->status !== JournalStatus::Open) {
            throw new TaskNotReadyToComplete(
                __('Today\'s journal is no longer open.'),
                'journal.not_open',
            );
        }

        $journal->payments()->syncWithoutDetaching([
            $payment->id => ['amount_snapshot' => $payment->amount],
        ]);

        $payment->update(['journal_id' => $journal->id]);
        $journal->recalculateTotal();
    }

    /** @param array<string, mixed> $fields */
    public function submit(Journal $journal, User $user, array $fields): void
    {
        if ($journal->status !== JournalStatus::Open) {
            throw new TaskNotReadyToComplete(
                __('This journal has already been submitted.'),
                'journal.not_open',
            );
        }

        if (! $journal->payments()->exists()) {
            throw new TaskNotReadyToComplete(
                __('Submit an empty journal only through the daily auto-close job.'),
                'journal.empty',
            );
        }

        DB::transaction(function () use ($journal, $user, $fields): void {
            foreach ($journal->payments as $payment) {
                $journal->payments()->updateExistingPivot($payment->id, [
                    'amount_snapshot' => $payment->amount,
                ]);

                $payment->update(['status' => PaymentStatus::Journaled]);
            }

            $journal->update([
                'status' => JournalStatus::Submitted,
                'prepared_by_user_id' => $user->id,
                'submitted_at' => now(),
                'odoo_journal_ref' => $fields['odoo_journal_ref'] ?? null,
            ]);

            $journal->recalculateTotal();
        });

        DB::afterCommit(fn () => event(new JournalSubmitted($journal->fresh(), $user)));
    }

    public function submitEmptyJournal(Journal $journal): void
    {
        if ($journal->status !== JournalStatus::Open || $journal->payments()->exists()) {
            return;
        }

        $journal->update([
            'status' => JournalStatus::Submitted,
            'submitted_at' => now(),
            'total_amount' => 0,
        ]);
    }

    public function reopen(Journal $journal, User $user, string $reason): void
    {
        if ($journal->status !== JournalStatus::Submitted) {
            throw new TaskNotReadyToComplete(
                __('Only a submitted journal can be reopened.'),
                'journal.not_submitted',
            );
        }

        $oldStatus = $journal->status;

        $journal->update([
            'status' => JournalStatus::Open,
            'submitted_at' => null,
            'prepared_by_user_id' => null,
        ]);

        $journal->payments()->each(function (Payment $payment): void {
            if ($payment->status === PaymentStatus::Journaled) {
                $payment->update(['status' => PaymentStatus::Reviewed]);
            }
        });

        $this->auditLogger->log(
            auditable: $journal,
            event: 'reopened',
            user: $user,
            oldValues: ['status' => $oldStatus->value],
            newValues: ['status' => JournalStatus::Open->value],
            reason: $reason,
        );

        DB::afterCommit(fn () => event(new JournalReopened($journal->fresh(), $user, $reason)));
    }

    /** @param array<string, mixed> $fields */
    public function markAccounted(Journal $journal, User $user, array $fields): void
    {
        if ($journal->status !== JournalStatus::Submitted) {
            throw new TaskNotReadyToComplete(
                __('The journal must be submitted before accounting can process it.'),
                'journal.not_submitted',
            );
        }

        DB::transaction(function () use ($journal, $user, $fields): void {
            $journal->payments()->each(function (Payment $payment): void {
                $payment->update(['status' => PaymentStatus::Accounted]);
            });

            $journal->update([
                'status' => JournalStatus::Accounted,
                'accounted_by_user_id' => $user->id,
                'accounted_at' => now(),
                'odoo_journal_ref' => $fields['odoo_reference'] ?? $journal->odoo_journal_ref,
            ]);
        });
    }
}
