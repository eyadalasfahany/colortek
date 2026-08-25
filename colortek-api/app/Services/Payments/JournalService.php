<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\JournalStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Journal;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

final class JournalService
{
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
}
