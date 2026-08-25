<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SiteBlockOverrideRequest;
use App\Http\Requests\TaskBlockRequest;
use App\Http\Requests\TaskCompleteRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CreatedTaskResource;
use App\Http\Resources\TaskListResource;
use App\Http\Resources\TaskResource;
use App\Models\BlockerCategory;
use App\Models\Task;
use App\Services\Attachments\AttachmentService;
use App\Services\Tasks\TaskQueryService;
use App\Services\Tasks\TaskService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService,
        private TaskQueryService $taskQueryService,
        private AttachmentService $attachmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        $paginator = $this->taskQueryService->paginateForUser($request, $request->user());

        return TaskListResource::collection($paginator)->response();
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('view', $task);

        return response()->json([
            'data' => TaskResource::make($task->load([
                'department',
                'claimant',
                'project',
                'definition',
                'subject',
                'instance.tasks.definition',
                'instance.tasks.fieldValues',
            ])),
        ]);
    }

    public function claim(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('claim', $task);

        $claimed = $this->taskService->claim($task, $request->user());

        return response()->json([
            'data' => TaskResource::make($claimed->load(['department', 'claimant'])),
        ]);
    }

    public function release(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('release', $task);

        $released = $this->taskService->release($task, $request->user());

        return response()->json([
            'data' => TaskResource::make($released->load(['department', 'claimant'])),
        ]);
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('start', $task);

        $started = $this->taskService->start($task, $request->user());

        return response()->json([
            'data' => TaskResource::make($started->load(['department', 'claimant'])),
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('pause', $task);

        $paused = $this->taskService->pause($task, $request->user());

        return response()->json([
            'data' => TaskResource::make($paused->load(['department', 'claimant'])),
        ]);
    }

    public function block(TaskBlockRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('block', $task);

        $category = BlockerCategory::query()->findOrFail($request->integer('blocker_category_id'));

        $blocked = $this->taskService->block(
            $task,
            $request->user(),
            $category,
            $request->validated('reason'),
            $request->filled('expected_resolution')
                ? CarbonImmutable::parse($request->validated('expected_resolution'))
                : null,
        );

        return response()->json([
            'data' => TaskResource::make($blocked->load(['department', 'claimant'])),
        ]);
    }

    public function complete(TaskCompleteRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('complete', $task);

        $result = $this->taskService->complete(
            $task,
            $request->user(),
            $request->validated('fields') ?? [],
            $request->validated('attachment_ids') ?? [],
        );

        return response()->json([
            'data' => TaskResource::make($result['task']),
            'meta' => [
                'created_tasks' => CreatedTaskResource::collection($result['created'])->resolve(),
                'project_stage' => $result['task']->project?->stage,
            ],
        ]);
    }

    public function overrideSiteBlock(SiteBlockOverrideRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('complete', $task);

        return response()->json(['data' => TaskResource::make($this->taskService->overrideSiteBlock($task, $request->user(), $request->validated('reason'))->load(['department', 'project', 'definition']))]);
    }

    public function attach(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('complete', $task);

        $request->validate([
            'attachment_id' => ['required', 'integer', 'exists:attachments,id'],
        ]);

        $attachment = $this->attachmentService->findOrFail($request->integer('attachment_id'));
        $linked = $this->attachmentService->attachToTask($task, $attachment);

        return response()->json([
            'data' => AttachmentResource::make($linked),
        ], 201);
    }

    private function findTaskOrFail(int $id): Task
    {
        try {
            return $this->taskQueryService->findForUser($id, request()->user());
        } catch (ModelNotFoundException) {
            throw new ModelNotFoundException(__('Task not found'));
        }
    }
}
