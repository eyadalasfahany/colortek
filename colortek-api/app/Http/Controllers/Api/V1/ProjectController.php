<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Filters\ActivityFilter;
use App\Http\Filters\ProjectFilter;
use App\Http\Resources\ActivityEventResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectWorkflowResource;
use App\Http\Resources\TaskListResource;
use App\Models\Project;
use App\Services\Activity\ActivityQuery;
use App\Services\Projects\ProjectWorkflowService;

final class ProjectController extends Controller
{
    public function __construct(private ProjectFilter $pf, private ProjectWorkflowService $wf, private ActivityQuery $aq, private ActivityFilter $af) {}

    public function index($r)
    {
        $this->authorize('viewAny', Project::class);

        return ProjectListResource::collection($this->pf->apply($r, Project::with(['client', 'salesUser']), $r->user())->paginate(15))->response();
    }

    public function show($r, $id)
    {
        $p = Project::with(['client', 'salesUser'])->findOrFail($id);
        $this->authorize('view', $p);

        return response()->json(['data' => ProjectResource::make($p)]);
    }

    public function showByReference($r, $ref)
    {
        $p = Project::with(['client', 'salesUser'])->where('reference', $ref)->firstOrFail();
        $this->authorize('view', $p);

        return response()->json(['data' => ProjectResource::make($p)]);
    }

    public function workflow($r, $id)
    {
        $p = Project::findOrFail($id);
        $this->authorize('view', $p);

        return response()->json(['data' => ProjectWorkflowResource::make($this->wf->workflow($p))]);
    }

    public function tasks($r, $id)
    {
        $p = Project::findOrFail($id);
        $this->authorize('view', $p);

        return TaskListResource::collection($p->tasks()->with(['department', 'claimant'])->paginate(15))->response();
    }

    public function payments($r, $id)
    {
        $p = Project::findOrFail($id);
        $this->authorize('view', $p);

        return response()->json(['data' => PaymentResource::collection($p->payments()->orderBy('installment_number')->get())]);
    }

    public function hours($r, $id)
    {
        $p = Project::findOrFail($id);
        $this->authorize('view', $p);

        return response()->json(['data' => ['workshop_timers' => [], 'site_crew_today' => [], 'totals_by_department' => [], 'stub' => true]]);
    }

    public function samples($r, $id)
    {
        $p = Project::findOrFail($id);
        $this->authorize('view', $p);

        return response()->json(['data' => ['chains' => [], 'stub' => true]]);
    }

    public function siteVisits($r, $id)
    {
        $p = Project::findOrFail($id);
        $this->authorize('view', $p);

        return response()->json(['data' => ['visits' => [], 'stub' => true]]);
    }

    public function activity($r, $id)
    {
        $p = Project::findOrFail($id);
        $this->authorize('view', $p);
        $r->merge(['project_id' => $id]);

        return ActivityEventResource::collection($this->af->apply($r, $this->aq->forUser($r->user()), $r->user())->paginate(15))->response();
    }
}
