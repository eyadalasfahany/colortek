<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\Payments\JournalWorkflowService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class OpenDailyJournal implements ShouldQueue
{
    use Queueable;

    public function handle(JournalWorkflowService $journalWorkflowService): void
    {
        $today = CarbonImmutable::today();

        $journalWorkflowService->autoCloseEmptyJournalForDate($today->subDay());
        $journalWorkflowService->ensureDailyJournalTask($today);
    }
}
