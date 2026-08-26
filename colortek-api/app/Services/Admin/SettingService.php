<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ActivitySeverity;
use App\Jobs\RecalculateDeadlines;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\SettingRepository;
use App\Services\Activity\ActivityRecorder;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SettingService
{
    private const KEYS = ['work_start', 'work_end', 'weekend_days', 'humidity_max', 'sample_repeat_attempt_threshold', 'block_all_when_site_not_ready', 'default_locale'];

    public function __construct(private SettingRepository $repo, private AuditLogger $audit, private CalendarImpactService $impact, private ActivityRecorder $activity) {}

    public function all(): Collection
    {
        return $this->repo->all()->filter(fn (Setting $s) => in_array($s->key, self::KEYS, true))->values();
    }

    public function update(array $payload, User $user, bool $confirm = false): array
    {
        $values = collect($payload)->only(self::KEYS)->all();
        $count = $this->impact->countAffectedTasks($values);
        if ($count > 0 && ! $confirm) {
            abort(422, 'Confirm calendar impact before saving settings.');
        }
        $old = [];
        foreach (array_keys($values) as $k) {
            $old[$k] = Setting::get($k);
        }
        DB::transaction(function () use ($values, $old, $user, $count) {
            $this->repo->upsertValues($values);
            $this->audit->log(Setting::query()->firstOrCreate(['key' => 'work_start']), 'updated', $user, $old, $values);
            if ($count > 0) {
                RecalculateDeadlines::dispatchSync();
                $this->activity->record('calendar_changed', ActivitySeverity::Info, "Calendar changed by {$user->name} — {$count} task deadlines recalculated.", '', $user);
            }
        });

        return ['settings' => $this->all(), 'affected_task_count' => $count];
    }
}
