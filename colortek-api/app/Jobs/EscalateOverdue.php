<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskEscalatedNotification;
use App\Services\Time\WorkingCalendar;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class EscalateOverdue implements ShouldQueue
{
    use Queueable;

    public function handle(WorkingCalendar $c): void
    {
        if (! $c->isWorkingTime(now())) {
            return;
        }
        Task::where('is_overdue', true)->whereNull('escalated_at')->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])->each(function ($t) {
            $t->update(['escalated_at' => now()]);
            $f = $t->fresh();
            $key = "task_escalated_{$f->id}_{$f->escalated_at?->timestamp}";
            User::whereHas('departments', fn ($q) => $q->where('departments.id', $t->department_id)->where('department_user.is_supervisor', true))->each(function ($u) use ($f, $key) {
                if (! $u->notifications()->where('data->idempotency_key', $key)->exists()) {
                    $u->notify(new TaskEscalatedNotification($f));
                }
            });
        });
    }
}
