<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\TaskStatus;
use App\Models\CrewLog;
use App\Models\Department;
use App\Models\Project;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Projects\ProjectVisibility;
use App\Services\Projects\ProjectWorkflowService;

final class ControlRoomService
{
    public function __construct(private ProjectVisibility $v, private ProjectWorkflowService $w) {}

    public function build(User $u): array
    {
        $pq = Project::where('status', 'active');
        $this->v->applyToProjects($pq, $u);
        $ids = (clone $pq)->pluck('id');
        $tq = Task::whereIn('project_id', $ids);

        // specs/09-screens/01-control-room.md §"KPI row": awaiting approval is
        // open manager_approve_sample tasks, not all pending samples.
        $awaitingApprovalQuery = (clone $tq)
            ->whereIn('status', [TaskStatus::Ready, TaskStatus::Claimed, TaskStatus::InProgress])
            ->whereHas('definition', fn ($q) => $q->where('code', 'manager_approve_sample'));

        // "Sites not ready" is the latest visit per project, not the project's
        // cached site_ready flag — the flag can lag a visit that was just filed.
        $notReadyProjectIds = SiteVisit::query()
            ->whereIn('project_id', $ids)
            ->whereIn('id', function ($query) use ($ids): void {
                $query->selectRaw('max(id)')
                    ->from('site_visits')
                    ->whereIn('project_id', $ids)
                    ->groupBy('project_id');
            })
            ->where('readiness', 'not_ready')
            ->pluck('project_id');

        $notReadyProjectsQuery = (clone $pq)->whereIn('id', $notReadyProjectIds);

        $workshopDepartmentIds = Department::query()
            ->whereIn('code', ['workshop', 'tinting'])
            ->pluck('id');

        $workshopTimersCount = TimeEntry::query()
            ->whereNull('ended_at')
            ->whereHas('task', fn ($q) => $q->whereIn('department_id', $workshopDepartmentIds))
            ->count();

        $onSiteTodayCount = CrewLog::query()
            ->whereDate('log_date', now()->toDateString())
            ->where('status', 'submitted')
            ->withCount('members')
            ->get()
            ->sum('members_count');

        return ['kpis' => [
            ['key' => 'active_projects', 'label' => 'Active projects', 'count' => (clone $pq)->count(), 'filter_href' => '/projects?status=active'],
            ['key' => 'blocked_tasks', 'label' => 'Blocked tasks', 'count' => (clone $tq)->where('status', TaskStatus::Blocked)->count(), 'filter_href' => '/tasks?status=blocked'],
            ['key' => 'overdue_tasks', 'label' => 'Overdue tasks', 'count' => (clone $tq)->where('is_overdue', true)->count(), 'filter_href' => '/tasks?overdue=1'],
            ['key' => 'awaiting_approval', 'label' => 'Awaiting approval', 'count' => (clone $awaitingApprovalQuery)->count(), 'filter_href' => '/samples?status=pending_manager_approval'],
            ['key' => 'sites_not_ready', 'label' => 'Sites not ready', 'count' => (clone $notReadyProjectsQuery)->count(), 'filter_href' => '/site'],
            ['key' => 'workshop_timers', 'label' => 'Working now (workshop)', 'count' => $workshopTimersCount, 'filter_href' => '/workshop'],
            ['key' => 'on_site_today', 'label' => 'On site today', 'count' => $onSiteTodayCount, 'filter_href' => '/site'],
        ], 'active_projects' => (clone $pq)->with(['client', 'salesUser', 'tasks'])->limit(20)->get()->map(fn ($p) => [
            'id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'client_name' => $p->client?->name, 'sales_user' => $p->salesUser?->name,
            'stage' => $p->stage->value, 'site_ready' => $p->site_ready, 'next_action' => $this->w->workflow($p)['next_action'],
        ])->values()->all(), 'needs_attention' => [
            'blockers' => (clone $tq)->where('status', TaskStatus::Blocked)->with('project')->limit(20)->get()->map(fn ($t) => ['task_id' => $t->id, 'title' => $t->localizedTitle(), 'project_reference' => $t->project?->reference]),
            'waiting_approval' => (clone $awaitingApprovalQuery)->with('project')->limit(20)->get()->map(fn ($t) => ['task_id' => $t->id, 'title' => $t->localizedTitle(), 'project_reference' => $t->project?->reference]),
            'sites_not_ready' => (clone $notReadyProjectsQuery)->limit(20)->get()->map(fn ($p) => ['project_id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'corrective_actions' => []]),
        ]];
    }
}
