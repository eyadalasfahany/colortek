<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Enums\ActivitySeverity;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use App\Services\Audit\AuditLogger;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Support\Facades\DB;

final class SiteBlockService
{
    public function __construct(private AuditLogger $auditLogger, private ActivityRecorder $activityRecorder, private WorkflowEngine $workflowEngine) {}

    public function applyBlock(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $project->update(['site_ready' => false]);
            $blockAll = $project->block_all_when_site_not_ready || (bool) Setting::get('block_all_when_site_not_ready', false);
            Task::query()->where('project_id', $project->id)->whereIn('status', [TaskStatus::Ready, TaskStatus::Claimed, TaskStatus::InProgress, TaskStatus::Paused])
                ->when(! $blockAll, fn ($q) => $q->whereHas('definition', fn ($d) => $d->where('blocks_when_site_not_ready', true)))
                ->each(fn (Task $t) => $t->update(['status' => TaskStatus::Pending]));
        });
    }

    public function releaseBlock(Project $project): void
    {
        DB::transaction(function () use ($project): void {
            $project->update(['site_ready' => true]);
            $this->workflowEngine->releaseSiteHeldTasks($project->fresh());
        });
    }

    public function override(Task $task, User $user, string $reason): Task
    {
        return DB::transaction(function () use ($task, $user, $reason): Task {
            $task->update(['status' => TaskStatus::Ready, 'ready_at' => now()]);
            $this->auditLogger->log($task, 'override', $user, ['status' => TaskStatus::Pending->value], ['status' => TaskStatus::Ready->value], $reason);
            $this->activityRecorder->record('site.block_overridden', ActivitySeverity::Warning, "Override {$task->reference}", "تجاوز {$task->reference}", $user, $task->project, $task, $task->department);

            return $task->fresh(['department', 'project', 'definition']);
        });
    }
}
