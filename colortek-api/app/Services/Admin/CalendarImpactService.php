<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Services\Tasks\DeadlineCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

final class CalendarImpactService
{
    public function __construct(private DeadlineCalculator $dc) {}

    public function countAffectedTasks(array $settings = [], ?array $holiday = null, ?int $deleteHolidayId = null): int
    {
        return $this->openTasksQuery()->count();
    }

    public function openTasksQuery(): Builder
    {
        return Task::query()->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->where('due_at_overridden', false)->whereNotNull('due_at');
    }

    public function recalculateAllOpenTasks(): int
    {
        $n = 0;
        Task::query()->with(['definition', 'project'])->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
            ->where('due_at_overridden', false)->whereNotNull('task_definition_id')->chunkById(100, function ($tasks) use (&$n) {
                foreach ($tasks as $t) {
                    if (! $t->definition) {
                        continue;
                    }
                    $from = $t->ready_at ?? $t->created_at;
                    if (! $from) {
                        continue;
                    }
                    $new = $this->dc->for($t->definition, $t->project, CarbonImmutable::parse($from));
                    if ($new && ! $t->due_at?->equalTo($new)) {
                        $t->update(['due_at' => $new, 'is_overdue' => $new->isPast()]);
                        $n++;
                    }
                }
            });

        return $n;
    }
}
