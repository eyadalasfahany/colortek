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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectController extends Controller
{
    public function __construct(
        private ProjectFilter $projectFilter,
        private ProjectWorkflowService $workflowService,
        private ActivityQuery $activityQuery,
        private ActivityFilter $activityFilter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        $query = $this->projectFilter->apply(
            $request,
            Project::with(['client', 'salesUser']),
            $request->user(),
        );

        return ProjectListResource::collection($query->paginate(15))->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::with(['client', 'salesUser'])->findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => ProjectResource::make($project)]);
    }

    public function showByReference(Request $request, string $ref): JsonResponse
    {
        $project = Project::with(['client', 'salesUser'])->where('reference', $ref)->firstOrFail();
        $this->authorize('view', $project);

        return response()->json(['data' => ProjectResource::make($project)]);
    }

    public function workflow(Request $request, int $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json([
            'data' => ProjectWorkflowResource::make($this->workflowService->workflow($project)),
        ]);
    }

    public function tasks(Request $request, int $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return TaskListResource::collection(
            $project->tasks()->with(['department', 'claimant'])->paginate(15),
        )->response();
    }

    public function payments(Request $request, int $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json([
            'data' => PaymentResource::collection(
                $project->payments()->orderBy('installment_number')->get(),
            ),
        ]);
    }

    public function hours(Request $request, int $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json([
            'data' => [
                'workshop_timers' => [],
                'site_crew_today' => [],
                'totals_by_department' => [],
                'stub' => true,
            ],
        ]);
    }

    public function samples(Request $request, int $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => ['chains' => [], 'stub' => true]]);
    }

    public function siteVisits(Request $request, int $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => ['visits' => [], 'stub' => true]]);
    }

    public function activity(Request $request, int $id): JsonResponse
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);
        $request->merge(['project_id' => $id]);

        $query = $this->activityFilter->apply(
            $request,
            $this->activityQuery->forUser($request->user()),
            $request->user(),
        );

        return ActivityEventResource::collection($query->paginate(15))->response();
    }
}
