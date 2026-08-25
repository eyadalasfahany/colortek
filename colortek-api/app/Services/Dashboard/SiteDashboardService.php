<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectVisibility;

final class SiteDashboardService
{
    public function __construct(private ProjectVisibility $v) {}

    public function build(User $u): array
    {
        $q = Project::where('status', 'active');
        $this->v->applyToProjects($q, $u);

        return ['active_sites' => [], 'awaiting_inspection' => [], 'not_ready' => (clone $q)->where('site_ready', false)->get()->map(fn ($p) => ['id' => $p->id, 'reference' => $p->reference, 'name' => $p->name])->all(), 'reinspection_due' => [], 'corrective_actions' => [], 'crew_logs_today' => [], 'not_yet_reported' => [], 'stub' => true];
    }
}
