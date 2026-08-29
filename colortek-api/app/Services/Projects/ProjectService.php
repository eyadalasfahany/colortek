<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Events\ProjectCompleted;
use App\Events\ProjectStageChanged;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\CrewLog;
use App\Models\Project;
use App\Models\Sample;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Samples\SampleChain;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ProjectService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private SampleChain $sampleChain,
    ) {}

    /** @param array<string, mixed> $data */
    public function store(array $data, User $user): Project
    {
        return DB::transaction(function () use ($data, $user): Project {
            $reference = (string) ($data['reference'] ?? Str::upper(Str::random(8)));

            $project = Project::query()->create([
                'reference' => $reference,
                'name' => $data['name'],
                'client_id' => $data['client_id'],
                'quotation_id' => $data['quotation_id'] ?? null,
                'stage' => ProjectStage::Lead,
                'status' => ProjectStatus::Active->value,
                'sales_user_id' => $data['sales_user_id'] ?? $user->id,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
            ]);

            $this->auditLogger->log($project, 'created', $user, newValues: ['reference' => $project->reference]);

            return $project->load(['client', 'salesUser', 'responsibleUser']);
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Project $project, array $data, User $user): Project
    {
        return DB::transaction(function () use ($project, $data, $user): Project {
            $old = $project->only(array_keys($data));
            $previousStage = $project->stage;
            // `status` is a plain string column; normalise so an enum instance
            // from a validated request is stored as its value.
            if (isset($data['status'])) {
                $data['status'] = $data['status'] instanceof ProjectStatus
                    ? $data['status']->value
                    : (string) $data['status'];
            }
            if (isset($data['stage']) && $data['stage'] instanceof ProjectStage) {
                $data['stage'] = $data['stage']->value;
            }
            $project->update($data);

            $this->auditLogger->log($project, 'updated', $user, oldValues: $old, newValues: $project->only(array_keys($data)));

            if (isset($data['stage']) && $previousStage->value !== (string) $data['stage']) {
                event(new ProjectStageChanged(
                    $project->fresh(),
                    $user,
                    $previousStage,
                    ProjectStage::from((string) $data['stage']),
                ));
            }

            return $project->fresh(['client', 'salesUser', 'responsibleUser']) ?? $project;
        });
    }

    public function complete(Project $project, User $user, ?string $note = null): Project
    {
        if ($project->status === ProjectStatus::Completed->value) {
            throw new TaskNotReadyToComplete(__('This project is already completed.'), 'project.already_completed');
        }

        return DB::transaction(function () use ($project, $user, $note): Project {
            $project->update([
                'status' => ProjectStatus::Completed->value,
                'stage' => ProjectStage::Completed,
                'completed_at' => now(),
                'completed_by_user_id' => $user->id,
                'completion_note' => $note,
            ]);

            $project->tasks()
                ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
                ->update(['status' => TaskStatus::Cancelled, 'cancelled_reason' => 'project completed']);

            event(new ProjectCompleted($project->fresh(), $user));

            return $project->fresh(['client', 'salesUser', 'responsibleUser']) ?? $project;
        });
    }

    public function cancel(Project $project, User $user, string $reason): Project
    {
        return DB::transaction(function () use ($project, $user, $reason): Project {
            $project->update([
                'status' => ProjectStatus::Cancelled->value,
                'completion_note' => $reason,
            ]);

            $project->tasks()
                ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
                ->update(['status' => TaskStatus::Cancelled, 'cancelled_reason' => $reason]);

            $this->auditLogger->log($project, 'cancelled', $user, reason: $reason);

            return $project->fresh(['client', 'salesUser', 'responsibleUser']) ?? $project;
        });
    }

    /** @return array<string, mixed> */
    public function hoursSummary(Project $project): array
    {
        $taskIds = $project->tasks()->pluck('id');

        $workshopTimers = TimeEntry::query()
            ->with(['employee', 'user'])
            ->whereIn('task_id', $taskIds)
            ->whereNotNull('ended_at')
            ->get()
            ->map(fn (TimeEntry $entry): array => [
                'task_id' => $entry->task_id,
                'employee' => $entry->employee?->name,
                'supervisor' => $entry->user->name,
                'seconds' => $entry->seconds,
                'needs_review' => $entry->needs_review,
            ])
            ->values()
            ->all();

        $today = now()->toDateString();
        $siteCrewToday = CrewLog::query()
            ->with(['members.employee'])
            ->where('project_id', $project->id)
            ->whereDate('log_date', $today)
            ->where('status', 'submitted')
            ->get()
            ->map(fn (CrewLog $log): array => [
                'log_id' => $log->id,
                'workers' => $log->members->count(),
                'hours' => $log->members->sum('hours'),
            ])
            ->all();

        $totalsByDepartment = DB::table('time_entries')
            ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->join('departments', 'departments.id', '=', 'tasks.department_id')
            ->where('tasks.project_id', $project->id)
            ->whereNotNull('time_entries.ended_at')
            ->groupBy('departments.id', 'departments.code')
            ->selectRaw('departments.code as department, SUM(time_entries.seconds) as seconds')
            ->get()
            ->map(fn ($row): array => ['department' => (string) $row->department, 'seconds' => (int) $row->seconds])
            ->all();

        return [
            'workshop_timers' => $workshopTimers,
            'site_crew_today' => $siteCrewToday,
            'totals_by_department' => $totalsByDepartment,
        ];
    }

    /** @return array<string, mixed> */
    public function samplesSummary(Project $project): array
    {
        $roots = Sample::query()
            ->where('project_id', $project->id)
            ->whereNull('parent_sample_id')
            ->with(['client'])
            ->get();

        $chains = $roots->map(fn (Sample $sample): array => $this->sampleChain->build($sample))->all();

        return ['chains' => $chains];
    }
}
