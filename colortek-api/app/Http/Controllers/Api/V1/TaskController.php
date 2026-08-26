<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Concerns\LoadsRequestedRelations;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdhocTaskRequest;
use App\Http\Requests\SiteBlockOverrideRequest;
use App\Http\Requests\TaskBlockRequest;
use App\Http\Requests\TaskCommentRequest;
use App\Http\Requests\TaskCompleteRequest;
use App\Http\Requests\TaskDeadlineRequest;
use App\Http\Requests\TaskReassignRequest;
use App\Http\Requests\TaskUnblockRequest;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\CreatedTaskResource;
use App\Http\Resources\TaskCommentResource;
use App\Http\Resources\TaskListResource;
use App\Http\Resources\TaskResource;
use App\Models\BlockerCategory;
use App\Models\Task;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use App\Services\Tasks\TaskQueryService;
use App\Services\Tasks\TaskService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TaskController extends Controller
{
    use LoadsRequestedRelations;

    /** @var list<string> */
    private const DETAIL_RELATIONS = [
        'department', 'claimant', 'project', 'definition', 'subject', 'instance',
        'instance.tasks.definition', 'instance.tasks.fieldValues', 'comments', 'comments.user',
        'timeEntries', 'timeEntries.employee', 'statusEvents', 'blockerCategory',
    ];

    public function __construct(
        private TaskService $taskService,
        private TaskQueryService $taskQueryService,
        private AttachmentService $attachmentService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Task::class);

        return TaskListResource::collection(
            $this->taskQueryService->paginateForUser($request, $request->user()),
        )->response();
    }

    public function store(AdhocTaskRequest $request): JsonResponse
    {
        $this->authorize('createAdhoc', Task::class);

        $task = $this->taskService->createAdhoc($request->validated(), $request->user());

        return response()->json([
            'data' => TaskResource::make($task->load(['department', 'project'])),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('view', $task);
        $this->loadRequestedRelations($request, $task, self::DETAIL_RELATIONS);

        return response()->json(['data' => TaskResource::make($task)]);
    }

    public function claim(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('claim', $task);

        return response()->json([
            'data' => TaskResource::make($this->taskService->claim($task, $request->user())->load(['department', 'claimant'])),
        ]);
    }

    public function release(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('release', $task);

        return response()->json([
            'data' => TaskResource::make($this->taskService->release($task, $request->user())->load(['department', 'claimant'])),
        ]);
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('start', $task);

        return response()->json([
            'data' => TaskResource::make($this->taskService->start($task, $request->user())->load(['department', 'claimant'])),
        ]);
    }

    public function pause(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('pause', $task);

        return response()->json([
            'data' => TaskResource::make($this->taskService->pause($task, $request->user())->load(['department', 'claimant'])),
        ]);
    }

    public function resume(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('start', $task);

        return response()->json([
            'data' => TaskResource::make($this->taskService->resume($task, $request->user())->load(['department', 'claimant'])),
        ]);
    }

    public function unblock(TaskUnblockRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('unblock', $task);

        return response()->json([
            'data' => TaskResource::make($this->taskService->unblock($task, $request->user(), $request->validated('resolution_note'))->load(['department', 'claimant'])),
        ]);
    }

    public function comment(TaskCommentRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('comment', $task);

        return response()->json([
            'data' => TaskCommentResource::make($this->taskService->comment($task, $request->user(), $request->validated('body'))->load('user')),
        ], 201);
    }

    public function reassign(TaskReassignRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('reassign', $task);
        $assignee = User::query()->findOrFail($request->integer('assignee_user_id'));

        return response()->json([
            'data' => TaskResource::make($this->taskService->reassign($task, $request->user(), $assignee)),
        ]);
    }

    public function updateDeadline(TaskDeadlineRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('overrideDeadline', $task);

        return response()->json([
            'data' => TaskResource::make($this->taskService->updateDeadline($task, $request->user(), CarbonImmutable::parse($request->validated('due_at')))->load(['department', 'claimant'])),
        ]);
    }

    public function block(TaskBlockRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('block', $task);
        $category = BlockerCategory::query()->findOrFail($request->integer('blocker_category_id'));

        return response()->json([
            'data' => TaskResource::make($this->taskService->block(
                $task,
                $request->user(),
                $category,
                $request->validated('reason'),
                $request->filled('expected_resolution') ? CarbonImmutable::parse($request->validated('expected_resolution')) : null,
            )->load(['department', 'claimant'])),
        ]);
    }

    public function complete(TaskCompleteRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('complete', $task);
        $result = $this->taskService->complete($task, $request->user(), $request->validated('fields') ?? [], $request->validated('attachment_ids') ?? []);

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

        return response()->json([
            'data' => TaskResource::make($this->taskService->overrideSiteBlock($task, $request->user(), $request->validated('reason'))->load(['department', 'project', 'definition'])),
        ]);
    }

    public function attach(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id);
        $this->authorize('complete', $task);
        $request->validate(['attachment_id' => ['required', 'integer', 'exists:attachments,id']]);
        $attachment = $this->attachmentService->findOrFail($request->integer('attachment_id'));

        return response()->json([
            'data' => AttachmentResource::make($this->attachmentService->attachToTask($task, $attachment)),
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
