<?php

declare(strict_types=1);

namespace App\Services\Time;

use App\Exceptions\TaskNotReadyToComplete;
use App\Models\CrewLog;
use App\Models\CrewLogMember;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class CrewLogService
{
    public function __construct(private AuditLogger $auditLogger) {}

    /** @return LengthAwarePaginator<int, CrewLog> */
    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        $query = CrewLog::query()
            ->with(['project', 'supervisor', 'members.employee'])
            ->latest('log_date');

        if (! $user->can('time.view_all')) {
            $departmentIds = $user->departments()->pluck('departments.id');
            $query->whereHas('project.tasks', fn ($q) => $q->whereIn('department_id', $departmentIds));
        }

        return $query->paginate($perPage);
    }

    /**
     * @param  array{log_date?: string, task_id?: int, work_done: string, weather_note?: string, issues?: string, members: list<array{employee_id: int, hours: float, role_note?: string}>}  $data
     */
    public function createForProject(Project $project, User $supervisor, array $data): CrewLog
    {
        $logDate = isset($data['log_date'])
            ? CarbonImmutable::parse($data['log_date'])->toDateString()
            : now()->toDateString();

        return DB::transaction(function () use ($project, $supervisor, $data, $logDate): CrewLog {
            $log = CrewLog::query()->create([
                'project_id' => $project->id,
                'log_date' => $logDate,
                'supervisor_user_id' => $supervisor->id,
                'task_id' => $data['task_id'] ?? null,
                'work_done' => $data['work_done'],
                'weather_note' => $data['weather_note'] ?? null,
                'issues' => $data['issues'] ?? null,
                'status' => 'draft',
            ]);

            $this->syncMembers($log, $data['members']);

            return $log->load(['project', 'supervisor', 'members.employee']);
        });
    }

    /**
     * @param  array{task_id?: int, work_done?: string, weather_note?: string|null, issues?: string|null, members?: list<array{employee_id: int, hours: float, role_note?: string}>}  $data
     */
    public function update(CrewLog $log, User $user, array $data): CrewLog
    {
        if ($log->status === 'submitted' && ! $user->can('time.correct')) {
            throw new TaskNotReadyToComplete(__('Submitted crew logs cannot be edited without correction permission.'), 'crew_log.submitted');
        }

        return DB::transaction(function () use ($log, $user, $data): CrewLog {
            $old = $log->only(['task_id', 'work_done', 'weather_note', 'issues']);
            $updates = [];

            foreach (['task_id', 'work_done', 'weather_note', 'issues'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $data[$field];
                }
            }

            if ($updates !== []) {
                $log->update($updates);
            }

            if (isset($data['members'])) {
                $this->syncMembers($log, $data['members']);
            }

            if ($log->status === 'submitted') {
                $this->auditLogger->log(
                    auditable: $log,
                    event: 'corrected',
                    user: $user,
                    oldValues: $old,
                    newValues: $log->fresh()?->only(['task_id', 'work_done', 'weather_note', 'issues']) ?? [],
                    reason: 'crew_log correction',
                );
            }

            return $log->fresh(['project', 'supervisor', 'members.employee']) ?? $log;
        });
    }

    public function submit(CrewLog $log, User $user): CrewLog
    {
        if ($log->status === 'submitted') {
            throw new TaskNotReadyToComplete(__('This crew log was already submitted.'), 'crew_log.already_submitted');
        }

        if ($log->members()->count() === 0) {
            throw new TaskNotReadyToComplete(__('Add at least one crew member before submitting.'), 'crew_log.no_members');
        }

        $log->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return $log->fresh(['project', 'supervisor', 'members.employee']) ?? $log;
    }

    public function findOrFail(int $id): CrewLog
    {
        $log = CrewLog::query()->with(['project', 'supervisor', 'members.employee'])->find($id);
        if ($log === null) {
            throw new ModelNotFoundException(__('Crew log not found'));
        }

        return $log;
    }

    /** @param list<array{employee_id: int, hours: float, role_note?: string}> $members */
    private function syncMembers(CrewLog $log, array $members): void
    {
        $log->members()->delete();

        foreach ($members as $member) {
            $employee = Employee::query()->find($member['employee_id']);
            if ($employee === null) {
                throw new ModelNotFoundException(__('Employee not found'));
            }

            CrewLogMember::query()->create([
                'crew_log_id' => $log->id,
                'employee_id' => $employee->id,
                'hours' => $member['hours'],
                'role_note' => $member['role_note'] ?? null,
            ]);
        }
    }
}
