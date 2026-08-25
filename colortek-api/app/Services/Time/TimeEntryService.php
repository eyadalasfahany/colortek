<?php

declare(strict_types=1);

namespace App\Services\Time;

use App\Enums\TimeEntrySource;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TimeEntryService
{
    public function __construct(private AuditLogger $auditLogger, private TimerService $timerService) {}

    /**
     * @param  array{started_at?: string, ended_at?: string, seconds?: int, note: string}  $data
     */
    public function correct(TimeEntry $entry, User $user, array $data): TimeEntry
    {
        if ($entry->ended_at === null) {
            throw new TaskNotReadyToComplete(__('Stop the timer before correcting it.'), 'time.entry_still_open');
        }

        $old = $entry->only(['started_at', 'ended_at', 'seconds', 'note']);

        return DB::transaction(function () use ($entry, $user, $data, $old): TimeEntry {
            $updates = ['source' => TimeEntrySource::ManualCorrection->value, 'note' => $data['note']];

            if (isset($data['started_at'])) {
                $updates['started_at'] = CarbonImmutable::parse($data['started_at']);
            }

            if (isset($data['ended_at'])) {
                $updates['ended_at'] = CarbonImmutable::parse($data['ended_at']);
            }

            if (isset($data['seconds'])) {
                $updates['seconds'] = (int) $data['seconds'];
            } elseif (isset($updates['started_at'], $updates['ended_at'])) {
                $updates['seconds'] = max(0, $updates['ended_at']->diffInSeconds($updates['started_at']));
            }

            $entry->update($updates);

            $this->auditLogger->log(
                auditable: $entry,
                event: 'corrected',
                user: $user,
                oldValues: $old,
                newValues: $entry->fresh()?->only(['started_at', 'ended_at', 'seconds', 'note']) ?? [],
                reason: $data['note'],
            );

            $this->timerService->recalculateActiveSeconds($entry->task()->firstOrFail());

            return $entry->fresh(['task', 'employee']) ?? $entry;
        });
    }
}
