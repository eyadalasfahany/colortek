<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;

final class TimerService
{
    public function stopForTask(Task $task): void
    {
        $openEntries = TimeEntry::query()
            ->where('task_id', $task->id)
            ->whereNull('ended_at')
            ->get();

        foreach ($openEntries as $entry) {
            $endedAt = CarbonImmutable::now();
            $seconds = max(0, $endedAt->diffInSeconds($entry->started_at));

            $entry->update([
                'ended_at' => $endedAt,
                'seconds' => $seconds,
            ]);
        }
    }
}
