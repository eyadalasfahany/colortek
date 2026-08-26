<?php

declare(strict_types=1);

namespace App\Services\Time;

use App\Enums\TaskStatus;
use App\Enums\TimeEntrySource;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TimerService
{
    public function __construct(private WorkingCalendar $calendar) {}

    public function start(Task $task, User $user, ?Employee $employee = null): TimeEntry
    {
        if (! $user->can('time.timer_run')) {
            throw new TaskNotReadyToComplete(__('You cannot run timers.'), 'time.timer_forbidden');
        }

        if ($employee !== null && ! $user->can('time.timer_run_for_others')) {
            throw new TaskNotReadyToComplete(__('You cannot run timers for other employees.'), 'time.timer_forbidden');
        }

        if (! in_array($task->status, [TaskStatus::InProgress, TaskStatus::Claimed], true)) {
            throw new TaskNotReadyToComplete(__('Start the task before running the timer.'), 'time.task_not_started');
        }

        return DB::transaction(function () use ($task, $user, $employee): TimeEntry {
            $this->pauseRunningTimerForUser($user);

            return TimeEntry::query()->create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'employee_id' => $employee?->id,
                'started_at' => now(),
                'source' => TimeEntrySource::Timer->value,
            ]);
        });
    }

    public function stop(Task $task, User $user): ?TimeEntry
    {
        $entry = TimeEntry::query()
            ->where('task_id', $task->id)
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();

        if ($entry === null) {
            return null;
        }

        return $this->closeEntry($entry);
    }

    public function stopForTask(Task $task): void
    {
        $openEntries = TimeEntry::query()
            ->where('task_id', $task->id)
            ->whereNull('ended_at')
            ->get();

        foreach ($openEntries as $entry) {
            $this->closeEntry($entry);
        }

        $this->recalculateActiveSeconds($task);
    }

    public function activeForUser(User $user): ?TimeEntry
    {
        return TimeEntry::query()
            ->with(['task.department', 'task.project', 'employee'])
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->latest('id')
            ->first();
    }

    public function closeStaleEntries(CarbonImmutable $asOf): int
    {
        $closed = 0;
        $openEntries = TimeEntry::query()->whereNull('ended_at')->get();

        foreach ($openEntries as $entry) {
            $shiftEnd = $this->shiftEndFor(CarbonImmutable::parse($entry->started_at));
            if ($asOf->greaterThan($shiftEnd)) {
                $entry->update([
                    'ended_at' => $shiftEnd,
                    'seconds' => max(0, $shiftEnd->diffInSeconds($entry->started_at)),
                    'source' => TimeEntrySource::AutoClosed->value,
                    'needs_review' => true,
                ]);
                $this->recalculateActiveSeconds($entry->task);
                $closed++;
            }
        }

        return $closed;
    }

    public function recalculateActiveSeconds(Task $task): void
    {
        $seconds = (int) TimeEntry::query()
            ->where('task_id', $task->id)
            ->whereNotNull('ended_at')
            ->where('source', '!=', TimeEntrySource::AutoClosed->value)
            ->sum('seconds');

        $task->update(['active_seconds' => $seconds]);
    }

    private function pauseRunningTimerForUser(User $user): void
    {
        $running = TimeEntry::query()
            ->where('user_id', $user->id)
            ->whereNull('ended_at')
            ->get();

        foreach ($running as $entry) {
            $this->closeEntry($entry);
            $this->recalculateActiveSeconds($entry->task);
        }
    }

    private function closeEntry(TimeEntry $entry): TimeEntry
    {
        $endedAt = CarbonImmutable::now();
        $seconds = max(0, $endedAt->diffInSeconds($entry->started_at));

        $entry->update([
            'ended_at' => $endedAt,
            'seconds' => $seconds,
        ]);

        $this->recalculateActiveSeconds($entry->task);

        return $entry->fresh() ?? $entry;
    }

    private function shiftEndFor(CarbonImmutable $startedAt): CarbonImmutable
    {
        $endTime = (string) (Setting::get('work_end') ?? '17:00');
        [$hour, $minute] = array_map(intval(...), explode(':', $endTime));

        $shiftEnd = $startedAt->setTime($hour, $minute, 0);
        if ($startedAt->greaterThan($shiftEnd)) {
            $shiftEnd = $this->calendar->addWorkingMinutes($startedAt, 0)->setTime($hour, $minute, 0);
        }

        return $shiftEnd;
    }
}
