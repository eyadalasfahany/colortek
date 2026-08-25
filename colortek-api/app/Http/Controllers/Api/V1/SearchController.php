<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Services\Projects\ProjectVisibility;

final class SearchController extends Controller
{
    public function __construct(private ProjectVisibility $v) {}

    public function __invoke($r)
    {
        $q = trim($r->string('q')->toString());
        if ($q === '') {
            return response()->json(['data' => []]);
        }
        $u = $r->user();
        $pq = Project::where(fn ($b) => $b->where('reference', 'like', "%$q%")->orWhere('name', 'like', "%$q%"));
        $this->v->applyToProjects($pq, $u);
        $ids = (clone $pq)->pluck('id');

        return response()->json(['data' => ['projects' => $pq->limit(10)->get()->map(fn ($p) => ['type' => 'project', 'id' => $p->id, 'label' => $p->reference.' — '.$p->name, 'reference' => $p->reference]),
            'tasks' => Task::whereIn('project_id', $ids)->where('reference', 'like', "%$q%")->with('project')->limit(10)->get()->map(fn ($t) => ['type' => 'task', 'id' => $t->id, 'label' => $t->reference.' — '.$t->localizedTitle(), 'project_reference' => $t->project?->reference]),
            'clients' => Client::where('name', 'like', "%$q%")->limit(5)->get()->map(fn ($c) => ['type' => 'client', 'id' => $c->id, 'label' => $c->name]), 'samples' => [], 'site_visits' => []]]);
    }
}
