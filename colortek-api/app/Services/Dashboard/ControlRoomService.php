<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
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

        return ['kpis' => [
            ['key' => 'active_projects', 'label' => 'Active projects', 'count' => (clone $pq)->count(), 'filter_href' => '/projects?status=active'],
            ['key' => 'blocked_tasks', 'label' => 'Blocked tasks', 'count' => (clone $tq)->where('status', TaskStatus::Blocked)->count(), 'filter_href' => '/tasks?status=blocked'],
            ['key' => 'overdue_tasks', 'label' => 'Overdue tasks', 'count' => (clone $tq)->where('is_overdue', true)->count(), 'filter_href' => '/tasks?overdue=1'],
            ['key' => 'awaiting_approval', 'label' => 'Awaiting approval', 'count' => 0, 'filter_href' => '/samples'],
            ['key' => 'sites_not_ready', 'label' => 'Sites not ready', 'count' => (clone $pq)->where('site_ready', false)->count(), 'filter_href' => '/site'],
            ['key' => 'workshop_timers', 'label' => 'Working now (workshop)', 'count' => 0, 'filter_href' => '/workshop'],
            ['key' => 'on_site_today', 'label' => 'On site today', 'count' => 0, 'filter_href' => '/site'],
        ], 'active_projects' => (clone $pq)->with(['client', 'salesUser', 'tasks'])->limit(20)->get()->map(fn ($p) => [
            'id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'client_name' => $p->client?->name, 'sales_user' => $p->salesUser?->name,
            'stage' => $p->stage->value, 'site_ready' => $p->site_ready, 'next_action' => $this->w->workflow($p)['next_action'],
        ])->values()->all(), 'needs_attention' => ['blockers' => (clone $tq)->where('status', TaskStatus::Blocked)->with('project')->limit(20)->get()->map(fn ($t) => ['task_id' => $t->id, 'title' => $t->localizedTitle(), 'project_reference' => $t->project?->reference]), 'waiting_approval' => [], 'sites_not_ready' => (clone $pq)->where('site_ready', false)->limit(20)->get()->map(fn ($p) => ['project_id' => $p->id, 'reference' => $p->reference, 'name' => $p->name, 'corrective_actions' => []])]];
    }
}
