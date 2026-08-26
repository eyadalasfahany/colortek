<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\LoadsRequestedRelations;
use App\Http\Controllers\Controller;
use App\Http\Filters\ActivityFilter;
use App\Http\Filters\ProjectFilter;
use App\Http\Requests\ProjectCancelRequest;
use App\Http\Requests\ProjectCompleteRequest;
use App\Http\Requests\ProjectStoreRequest;
use App\Http\Requests\ProjectUpdateRequest;
use App\Http\Resources\ActivityEventResource;
use App\Http\Resources\PaymentResource;
use App\Http\Resources\ProjectListResource;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\ProjectWorkflowResource;
use App\Http\Resources\SiteVisitResource;
use App\Http\Resources\TaskListResource;
use App\Models\Project;
use App\Services\Activity\ActivityQuery;
use App\Services\Projects\ProjectService;
use App\Services\Projects\ProjectWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectController extends Controller
{
    use LoadsRequestedRelations;

    /** @var list<string> */
    private const DETAIL_RELATIONS = ['client', 'salesUser', 'quotation', 'tasks.department'];

    public function __construct(
        private ProjectFilter $pf,
        private ProjectWorkflowService $wf,
        private ActivityQuery $aq,
        private ActivityFilter $af,
        private ProjectService $projectService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Project::class);

        return ProjectListResource::collection(
            $this->pf->apply($request, Project::with(['client', 'salesUser']), $request->user())->paginate(15),
        )->response();
    }

    public function store(ProjectStoreRequest $request): JsonResponse
    {
        $this->authorize('create', Project::class);

        return response()->json([
            'data' => ProjectResource::make($this->projectService->store($request->validated(), $request->user())),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('view', $project);
        $this->loadRequestedRelations($request, $project, self::DETAIL_RELATIONS);

        return response()->json(['data' => ProjectResource::make($project)]);
    }

    public function update(ProjectUpdateRequest $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('update', $project);

        return response()->json([
            'data' => ProjectResource::make($this->projectService->update($project, $request->validated(), $request->user())),
        ]);
    }

    public function complete(ProjectCompleteRequest $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('complete', $project);

        return response()->json([
            'data' => ProjectResource::make($this->projectService->complete($project, $request->user(), $request->validated('completion_note'))),
        ]);
    }

    public function cancel(ProjectCancelRequest $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('cancel', $project);

        return response()->json([
            'data' => ProjectResource::make($this->projectService->cancel($project, $request->user(), $request->validated('reason'))),
        ]);
    }

    public function showByReference(Request $request, string $ref): JsonResponse
    {
        $project = Project::with(['client', 'salesUser'])->where('reference', $ref)->firstOrFail();
        $this->authorize('view', $project);

        return response()->json(['data' => ProjectResource::make($project)]);
    }

    public function workflow(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => ProjectWorkflowResource::make($this->wf->workflow($project))]);
    }

    public function tasks(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('view', $project);

        return TaskListResource::collection($project->tasks()->with(['department', 'claimant'])->paginate(15))->response();
    }

    public function payments(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => PaymentResource::collection($project->payments()->orderBy('installment_number')->get())]);
    }

    public function hours(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => $this->projectService->hoursSummary($project)]);
    }

    public function samples(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => $this->projectService->samplesSummary($project)]);
    }

    public function siteVisits(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => SiteVisitResource::collection($project->siteVisits()->with(['engineer'])->get())]);
    }

    public function activity(Request $request, int $id): JsonResponse
    {
        $project = Project::query()->findOrFail($id);
        $this->authorize('view', $project);
        $request->merge(['project_id' => $id]);

        return ActivityEventResource::collection(
            $this->af->apply($request, $this->aq->forUser($request->user()), $request->user())->paginate(15),
        )->response();
    }
}
