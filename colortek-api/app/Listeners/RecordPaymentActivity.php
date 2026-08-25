<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ActivitySeverity;
use App\Events\JournalReopened;
use App\Events\JournalSubmitted;
use App\Events\PaymentConfirmed;
use App\Events\PaymentQueried;
use App\Services\Activity\ActivityRecorder;

final class RecordPaymentActivity
{
    public function __construct(private ActivityRecorder $r) {}

    public function handlePaymentConfirmed(PaymentConfirmed $e): void
    {
        $this->s(fn () => $this->r->record('payment.confirmed', ActivitySeverity::Success, 'Payment confirmed', 'تم تأكيد الدفعة', actor: $e->user, project: $e->payment->project, subject: $e->payment));
    }

    public function handlePaymentQueried(PaymentQueried $e): void
    {
        $this->s(fn () => $this->r->record('payment.queried', ActivitySeverity::Warning, 'Payment queried', 'استفسار', actor: $e->user, project: $e->payment->project, subject: $e->payment));
    }

    public function handleJournalSubmitted(JournalSubmitted $e): void
    {
        $this->s(fn () => $this->r->record('journal.submitted', ActivitySeverity::Info, 'Journal submitted', 'تم تقديم اليومية', actor: $e->user, subject: $e->journal));
    }

    public function handleJournalReopened(JournalReopened $e): void
    {
        $this->s(fn () => $this->r->record('journal.reopened', ActivitySeverity::Warning, 'Journal reopened', 'أُعيد فتح اليومية', actor: $e->user, subject: $e->journal, visibleToPermission: 'journal.reopen'));
    }

    private function s(callable $c): void
    {
        try {
            $c();
        } catch (\Throwable) {
        }
    }
}
