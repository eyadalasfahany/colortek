<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Events\TaskOverdue;
use App\Models\Task;
use App\Services\Time\WorkingCalendar;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class RecalculateOverdueTasks implements ShouldQueue
{
    use Queueable;

    public function handle(WorkingCalendar $c): void
    {
        if (! $c->isWorkingTime(now())) {
            return;
        }
        Task::whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])->where('is_overdue', false)->whereNotNull('due_at')->where('due_at', '<', now())->each(function ($t) {
            $t->update(['is_overdue' => true]);
            DB::afterCommit(fn () => event(new TaskOverdue($t->fresh())));
        });
    }
}
