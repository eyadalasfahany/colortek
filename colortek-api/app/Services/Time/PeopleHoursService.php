<?php

declare(strict_types=1);

namespace App\Services\Time;

use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectVisibility;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class PeopleHoursService
{
    public function __construct(private ProjectVisibility $visibility) {}

    /**
     * @param  array{from: string, to: string, project_id?: int|null, department_id?: int|null, employee_id?: int|null}  $filters
     * @return array{
     *     from: string,
     *     to: string,
     *     workshop: array{source: string, label_en: string, label_ar: string, by_project: list<array<string, mixed>>, by_department: list<array<string, mixed>>, by_employee: list<array<string, mixed>>},
     *     site: array{source: string, label_en: string, label_ar: string, by_project: list<array<string, mixed>>, by_department: list<array<string, mixed>>, by_employee: list<array<string, mixed>>}
     * }
     */
    public function report(User $user, array $filters): array
    {
        $from = CarbonImmutable::parse($filters['from'])->toDateString();
        $to = CarbonImmutable::parse($filters['to'])->toDateString();
        $projectId = isset($filters['project_id']) ? (int) $filters['project_id'] : null;
        $departmentId = isset($filters['department_id']) ? (int) $filters['department_id'] : null;
        $employeeId = isset($filters['employee_id']) ? (int) $filters['employee_id'] : null;

        return [
            'from' => $from,
            'to' => $to,
            'workshop' => [
                'source' => 'timers',
                'label_en' => 'Workshop — live timers',
                'label_ar' => 'الورشة — مؤقتات حية',
                'by_project' => $this->workshopByProject($user, $from, $to, $projectId, $departmentId, $employeeId),
                'by_department' => $this->workshopByDepartment($user, $from, $to, $projectId, $departmentId, $employeeId),
                'by_employee' => $this->workshopByEmployee($user, $from, $to, $projectId, $departmentId, $employeeId),
            ],
            'site' => [
                'source' => 'crew_logs',
                'label_en' => 'Site — crew logs',
                'label_ar' => 'الموقع — سجلات الطاقم',
                'by_project' => $this->siteByProject($user, $from, $to, $projectId, $departmentId, $employeeId),
                'by_department' => $this->siteByDepartment($user, $from, $to, $projectId, $departmentId, $employeeId),
                'by_employee' => $this->siteByEmployee($user, $from, $to, $projectId, $departmentId, $employeeId),
            ],
        ];
    }

    /**
     * @return list<array{project_id: int, reference: string, name: string, seconds: int, hours: float}>
     */
    private function workshopByProject(
        User $user,
        string $from,
        string $to,
        ?int $projectId,
        ?int $departmentId,
        ?int $employeeId,
    ): array {
        $rows = $this->workshopBaseQuery($user, $from, $to, $projectId, $departmentId, $employeeId)
            ->join('projects', 'projects.id', '=', 'tasks.project_id')
            ->groupBy('projects.id', 'projects.reference', 'projects.name')
            ->orderBy('projects.reference')
            ->select([
                'projects.id as project_id',
                'projects.reference',
                'projects.name',
                DB::raw('SUM(time_entries.seconds) as seconds'),
            ])
            ->get();

        return $rows->map(fn (object $row): array => [
            'project_id' => (int) $row->project_id,
            'reference' => (string) $row->reference,
            'name' => (string) $row->name,
            'seconds' => (int) $row->seconds,
            'hours' => $this->secondsToHours((int) $row->seconds),
        ])->all();
    }

    /**
     * @return list<array{department_id: int, code: string, name: string, seconds: int, hours: float}>
     */
    private function workshopByDepartment(
        User $user,
        string $from,
        string $to,
        ?int $projectId,
        ?int $departmentId,
        ?int $employeeId,
    ): array {
        $rows = $this->workshopBaseQuery($user, $from, $to, $projectId, $departmentId, $employeeId)
            ->join('departments', 'departments.id', '=', 'tasks.department_id')
            ->groupBy('departments.id', 'departments.code', 'departments.name')
            ->orderBy('departments.code')
            ->select([
                'departments.id as department_id',
                'departments.code',
                'departments.name',
                DB::raw('SUM(time_entries.seconds) as seconds'),
            ])
            ->get();

        return $rows->map(fn (object $row): array => [
            'department_id' => (int) $row->department_id,
            'code' => (string) $row->code,
            'name' => $this->departmentName($row->name),
            'seconds' => (int) $row->seconds,
            'hours' => $this->secondsToHours((int) $row->seconds),
        ])->all();
    }

    /**
     * @return list<array{employee_id: int, name: string, seconds: int, hours: float}>
     */
    private function workshopByEmployee(
        User $user,
        string $from,
        string $to,
        ?int $projectId,
        ?int $departmentId,
        ?int $employeeId,
    ): array {
        $rows = $this->workshopBaseQuery($user, $from, $to, $projectId, $departmentId, $employeeId)
            ->join('employees', 'employees.id', '=', 'time_entries.employee_id')
            ->whereNotNull('time_entries.employee_id')
            ->groupBy('employees.id', 'employees.name')
            ->orderBy('employees.name')
            ->select([
                'employees.id as employee_id',
                'employees.name',
                DB::raw('SUM(time_entries.seconds) as seconds'),
            ])
            ->get();

        return $rows->map(fn (object $row): array => [
            'employee_id' => (int) $row->employee_id,
            'name' => (string) $row->name,
            'seconds' => (int) $row->seconds,
            'hours' => $this->secondsToHours((int) $row->seconds),
        ])->all();
    }

    /**
     * @return list<array{project_id: int, reference: string, name: string, seconds: int, hours: float}>
     */
    private function siteByProject(
        User $user,
        string $from,
        string $to,
        ?int $projectId,
        ?int $departmentId,
        ?int $employeeId,
    ): array {
        $rows = $this->siteBaseQuery($user, $from, $to, $projectId, $departmentId, $employeeId)
            ->join('projects', 'projects.id', '=', 'crew_logs.project_id')
            ->groupBy('projects.id', 'projects.reference', 'projects.name')
            ->orderBy('projects.reference')
            ->select([
                'projects.id as project_id',
                'projects.reference',
                'projects.name',
                DB::raw('SUM(crew_log_members.hours) as hours'),
            ])
            ->get();

        return $rows->map(function (object $row): array {
            $hours = round((float) $row->hours, 2);

            return [
                'project_id' => (int) $row->project_id,
                'reference' => (string) $row->reference,
                'name' => (string) $row->name,
                'seconds' => (int) round($hours * 3600),
                'hours' => $hours,
            ];
        })->all();
    }

    /**
     * @return list<array{department_id: int, code: string, name: string, seconds: int, hours: float}>
     */
    private function siteByDepartment(
        User $user,
        string $from,
        string $to,
        ?int $projectId,
        ?int $departmentId,
        ?int $employeeId,
    ): array {
        $rows = $this->siteBaseQuery($user, $from, $to, $projectId, $departmentId, $employeeId)
            ->join('employees', 'employees.id', '=', 'crew_log_members.employee_id')
            ->join('departments', 'departments.id', '=', 'employees.department_id')
            ->groupBy('departments.id', 'departments.code', 'departments.name')
            ->orderBy('departments.code')
            ->select([
                'departments.id as department_id',
                'departments.code',
                'departments.name',
                DB::raw('SUM(crew_log_members.hours) as hours'),
            ])
            ->get();

        return $rows->map(function (object $row): array {
            $hours = round((float) $row->hours, 2);

            return [
                'department_id' => (int) $row->department_id,
                'code' => (string) $row->code,
                'name' => $this->departmentName($row->name),
                'seconds' => (int) round($hours * 3600),
                'hours' => $hours,
            ];
        })->all();
    }

    /**
     * @return list<array{employee_id: int, name: string, hours: float}>
     */
    private function siteByEmployee(
        User $user,
        string $from,
        string $to,
        ?int $projectId,
        ?int $departmentId,
        ?int $employeeId,
    ): array {
        $rows = $this->siteBaseQuery($user, $from, $to, $projectId, $departmentId, $employeeId)
            ->join('employees', 'employees.id', '=', 'crew_log_members.employee_id')
            ->groupBy('employees.id', 'employees.name')
            ->orderBy('employees.name')
            ->select([
                'employees.id as employee_id',
                'employees.name',
                DB::raw('SUM(crew_log_members.hours) as hours'),
            ])
            ->get();

        return $rows->map(fn (object $row): array => [
            'employee_id' => (int) $row->employee_id,
            'name' => (string) $row->name,
            'hours' => round((float) $row->hours, 2),
        ])->all();
    }

    private function workshopBaseQuery(
        User $user,
        string $from,
        string $to,
        ?int $projectId,
        ?int $departmentId,
        ?int $employeeId,
    ): Builder {
        $query = DB::table('time_entries')
            ->join('tasks', 'tasks.id', '=', 'time_entries.task_id')
            ->whereNotNull('time_entries.ended_at')
            ->whereDate('time_entries.started_at', '>=', $from)
            ->whereDate('time_entries.started_at', '<=', $to)
            ->whereNotNull('tasks.project_id')
            ->whereIn('tasks.project_id', $this->visibleProjectIds($user));

        if ($projectId !== null) {
            $query->where('tasks.project_id', $projectId);
        }

        if ($departmentId !== null) {
            $query->where('tasks.department_id', $departmentId);
        }

        if ($employeeId !== null) {
            $query->where('time_entries.employee_id', $employeeId);
        }

        return $query;
    }

    private function siteBaseQuery(
        User $user,
        string $from,
        string $to,
        ?int $projectId,
        ?int $departmentId,
        ?int $employeeId,
    ): Builder {
        $query = DB::table('crew_log_members')
            ->join('crew_logs', 'crew_logs.id', '=', 'crew_log_members.crew_log_id')
            ->where('crew_logs.status', 'submitted')
            ->whereDate('crew_logs.log_date', '>=', $from)
            ->whereDate('crew_logs.log_date', '<=', $to)
            ->whereIn('crew_logs.project_id', $this->visibleProjectIds($user));

        if ($projectId !== null) {
            $query->where('crew_logs.project_id', $projectId);
        }

        if ($employeeId !== null) {
            $query->where('crew_log_members.employee_id', $employeeId);
        }

        if ($departmentId !== null) {
            $query->whereExists(function (Builder $sub) use ($departmentId): void {
                $sub->selectRaw('1')
                    ->from('employees')
                    ->whereColumn('employees.id', 'crew_log_members.employee_id')
                    ->where('employees.department_id', $departmentId);
            });
        }

        return $query;
    }

    /** @return Collection<int, int> */
    private function visibleProjectIds(User $user): Collection
    {
        return $this->visibility
            ->applyToProjects(Project::query(), $user)
            ->pluck('id');
    }

    private function secondsToHours(int $seconds): float
    {
        return round($seconds / 3600, 2);
    }

    private function departmentName(mixed $name): string
    {
        if (is_array($name)) {
            return (string) ($name['en'] ?? $name[array_key_first($name)] ?? '');
        }

        if (is_string($name) && str_starts_with($name, '{')) {
            /** @var array<string, string>|null $decoded */
            $decoded = json_decode($name, true);
            if (is_array($decoded)) {
                return (string) ($decoded['en'] ?? $decoded[array_key_first($decoded)] ?? '');
            }
        }

        return (string) $name;
    }
}
