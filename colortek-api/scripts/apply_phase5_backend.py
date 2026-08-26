#!/usr/bin/env python3
"""Write all missing Phase 5 backend files atomically."""
from pathlib import Path

ROOT = Path("/workspace/colortek-api")

FILES = {
    "app/Services/Activity/ActivityQuery.php": '''<?php

declare(strict_types=1);

namespace App\\Services\\Activity;

use App\\Models\\ActivityEvent;
use App\\Models\\User;
use App\\Services\\Projects\\ProjectVisibility;
use Illuminate\\Database\\Eloquent\\Builder;

final class ActivityQuery
{
    public function __construct(private ProjectVisibility $visibility) {}

    /** @return Builder<ActivityEvent> */
    public function forUser(User $user): Builder
    {
        return $this->visibility->applyToActivity(
            ActivityEvent::query()->with(['actor', 'department', 'project']),
            $user,
        );
    }
}
''',
    "app/Http/Filters/ActivityFilter.php": '''<?php

declare(strict_types=1);

namespace App\\Http\\Filters;

use App\\Models\\User;
use Illuminate\\Database\\Eloquent\\Builder;
use Illuminate\\Http\\Request;

final class ActivityFilter
{
    /**
     * @param  Builder<\\App\\Models\\ActivityEvent>  $query
     * @return Builder<\\App\\Models\\ActivityEvent>
     */
    public function apply(Request $request, Builder $query, User $user): Builder
    {
        unset($user);

        if ($request->filled('since')) {
            $query->where('id', '>', $request->integer('since'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        return $query->orderByDesc('id');
    }
}
''',
    "app/Events/ActivityRecorded.php": '''<?php

declare(strict_types=1);

namespace App\\Events;

use App\\Models\\ActivityEvent;
use Illuminate\\Foundation\\Events\\Dispatchable;
use Illuminate\\Queue\\SerializesModels;

final class ActivityRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(public ActivityEvent $activityEvent) {}
}
''',
    "app/Http/Resources/UserSummaryResource.php": '''<?php

declare(strict_types=1);

namespace App\\Http\\Resources;

use App\\Models\\User;
use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

/** @mixin User */
final class UserSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
        ];
    }
}
''',
    "app/Http/Resources/ActivityEventResource.php": '''<?php

declare(strict_types=1);

namespace App\\Http\\Resources;

use Illuminate\\Http\\Request;
use Illuminate\\Http\\Resources\\Json\\JsonResource;

final class ActivityEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = $request->user()?->locale ?? app()->getLocale();
        $payload = $this->payload ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'severity' => $this->severity->value,
            'message' => $locale === 'ar' ? $this->message_ar : $this->message_en,
            'actor' => UserSummaryResource::make($this->whenLoaded('actor')),
            'department' => DepartmentResource::make($this->whenLoaded('department')),
            'project' => ProjectSummaryResource::make($this->whenLoaded('project')),
            'link' => $payload['route'] ?? null,
            'link_params' => $payload['params'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
''',
    "app/Http/Middleware/AuthenticateStream.php": '''<?php

declare(strict_types=1);

namespace App\\Http\\Middleware;

use Closure;
use Illuminate\\Http\\Request;
use Laravel\\Sanctum\\PersonalAccessToken;
use Symfony\\Component\\HttpFoundation\\Response;

final class AuthenticateStream
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            return $next($request);
        }

        foreach ([$request->bearerToken(), $request->query('token')] as $token) {
            if (! is_string($token) || $token === '') {
                continue;
            }

            $accessToken = PersonalAccessToken::findToken($token);

            if ($accessToken !== null) {
                $request->setUserResolver(fn () => $accessToken->tokenable);

                return $next($request);
            }
        }

        return response()->json(['message' => 'Unauthenticated.'], 401);
    }
}
''',
    "app/Http/Controllers/Api/V1/ActivityController.php": '''<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Http\\Filters\\ActivityFilter;
use App\\Http\\Resources\\ActivityEventResource;
use App\\Services\\Activity\\ActivityQuery;
use Illuminate\\Http\\Request;

final class ActivityController extends Controller
{
    public function __construct(
        private ActivityQuery $activityQuery,
        private ActivityFilter $activityFilter,
    ) {}

    public function index(Request $request)
    {
        $query = $this->activityFilter->apply(
            $request,
            $this->activityQuery->forUser($request->user()),
            $request->user(),
        );

        return ActivityEventResource::collection(
            $query->paginate($request->integer('per_page', 15)),
        )->response();
    }
}
''',
    "app/Http/Controllers/Api/V1/StreamController.php": '''<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Services\\Activity\\SseStream;
use Illuminate\\Http\\Request;

final class StreamController extends Controller
{
    public function __construct(private SseStream $sseStream) {}

    public function __invoke(Request $request)
    {
        $lastEventId = $request->header('Last-Event-ID');
        $cursor = is_numeric($lastEventId) ? (int) $lastEventId : null;

        return $this->sseStream->response($request->user(), $cursor);
    }
}
''',
    "app/Services/Activity/SseStream.php": '''<?php

declare(strict_types=1);

namespace App\\Services\\Activity;

use App\\Http\\Resources\\ActivityEventResource;
use App\\Models\\User;
use Symfony\\Component\\HttpFoundation\\StreamedResponse;

final class SseStream
{
    public function __construct(private ActivityQuery $activityQuery) {}

    public function response(User $user, ?int $lastEventId): StreamedResponse
    {
        return response()->stream(function () use ($user, $lastEventId): void {
            $cursor = $lastEventId ?? 0;
            $iterations = 0;

            while (! connection_aborted()) {
                $events = $this->activityQuery->forUser($user)
                    ->where('id', '>', $cursor)
                    ->orderBy('id')
                    ->limit(50)
                    ->get();

                foreach ($events as $event) {
                    echo 'id: '.$event->id."\\n";
                    echo "event: activity\\n";
                    echo 'data: '.json_encode(
                        ActivityEventResource::make($event)->resolve(),
                        JSON_THROW_ON_ERROR,
                    )."\\n\\n";
                    $cursor = $event->id;
                }

                if ($events->isNotEmpty()) {
                    @ob_flush();
                    flush();
                }

                sleep(1);

                if (app()->environment('testing') && ++$iterations >= 1) {
                    break;
                }
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
''',
    "app/Http/Controllers/Api/V1/DashboardController.php": '''<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Services\\Dashboard\\ControlRoomService;
use App\\Services\\Dashboard\\SamplesDashboardService;
use App\\Services\\Dashboard\\SiteDashboardService;
use App\\Services\\Dashboard\\WorkshopDashboardService;
use Illuminate\\Http\\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private ControlRoomService $controlRoom,
        private WorkshopDashboardService $workshop,
        private SiteDashboardService $site,
        private SamplesDashboardService $samples,
    ) {}

    public function controlRoom(Request $request)
    {
        abort_unless($request->user()->can('project.view_all'), 403);

        return response()->json(['data' => $this->controlRoom->build($request->user())]);
    }

    public function workshop(Request $request)
    {
        return response()->json(['data' => $this->workshop->build($request->user())]);
    }

    public function site(Request $request)
    {
        abort_unless($request->user()->can('site.view'), 403);

        return response()->json(['data' => $this->site->build($request->user())]);
    }

    public function samples(Request $request)
    {
        abort_unless($request->user()->can('sample.view'), 403);

        return response()->json(['data' => $this->samples->build($request->user())]);
    }
}
''',
    "app/Http/Controllers/Api/V1/NotificationController.php": '''<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Http\\Resources\\NotificationResource;
use Illuminate\\Http\\Request;

final class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return NotificationResource::collection(
            $request->user()->notifications()->latest()->paginate($request->integer('per_page', 20)),
        )->response();
    }

    public function unreadCount(Request $request)
    {
        return response()->json([
            'data' => ['count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return response()->json(['data' => NotificationResource::make($notification->fresh())]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json(['data' => null]);
    }
}
''',
}

PROJECT_CONTROLLER = '''<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Http\\Filters\\ActivityFilter;
use App\\Http\\Filters\\ProjectFilter;
use App\\Http\\Resources\\ActivityEventResource;
use App\\Http\\Resources\\PaymentResource;
use App\\Http\\Resources\\ProjectListResource;
use App\\Http\\Resources\\ProjectResource;
use App\\Http\\Resources\\ProjectWorkflowResource;
use App\\Http\\Resources\\TaskListResource;
use App\\Models\\Project;
use App\\Services\\Activity\\ActivityQuery;
use App\\Services\\Projects\\ProjectWorkflowService;
use Illuminate\\Http\\Request;

final class ProjectController extends Controller
{
    public function __construct(
        private ProjectFilter $projectFilter,
        private ProjectWorkflowService $workflowService,
        private ActivityQuery $activityQuery,
        private ActivityFilter $activityFilter,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', Project::class);

        $query = $this->projectFilter->apply(
            $request,
            Project::with(['client', 'salesUser']),
            $request->user(),
        );

        return ProjectListResource::collection($query->paginate(15))->response();
    }

    public function show(Request $request, int $id)
    {
        $project = Project::with(['client', 'salesUser'])->findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => ProjectResource::make($project)]);
    }

    public function showByReference(Request $request, string $ref)
    {
        $project = Project::with(['client', 'salesUser'])->where('reference', $ref)->firstOrFail();
        $this->authorize('view', $project);

        return response()->json(['data' => ProjectResource::make($project)]);
    }

    public function workflow(Request $request, int $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json([
            'data' => ProjectWorkflowResource::make($this->workflowService->workflow($project)),
        ]);
    }

    public function tasks(Request $request, int $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return TaskListResource::collection(
            $project->tasks()->with(['department', 'claimant'])->paginate(15),
        )->response();
    }

    public function payments(Request $request, int $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json([
            'data' => PaymentResource::collection(
                $project->payments()->orderBy('installment_number')->get(),
            ),
        ]);
    }

    public function hours(Request $request, int $id)
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

    public function samples(Request $request, int $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => ['chains' => [], 'stub' => true]]);
    }

    public function siteVisits(Request $request, int $id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('view', $project);

        return response()->json(['data' => ['visits' => [], 'stub' => true]]);
    }

    public function activity(Request $request, int $id)
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
'''

SEARCH_CONTROLLER = '''<?php

declare(strict_types=1);

namespace App\\Http\\Controllers\\Api\\V1;

use App\\Http\\Controllers\\Controller;
use App\\Models\\Client;
use App\\Models\\Project;
use App\\Models\\Task;
use App\\Services\\Projects\\ProjectVisibility;
use Illuminate\\Http\\Request;

final class SearchController extends Controller
{
    public function __construct(private ProjectVisibility $visibility) {}

    public function __invoke(Request $request)
    {
        $query = trim($request->string('q')->toString());

        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $projectQuery = Project::query()->where(
            fn ($builder) => $builder
                ->where('reference', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%"),
        );
        $this->visibility->applyToProjects($projectQuery, $user);
        $projectIds = (clone $projectQuery)->pluck('id');

        return response()->json([
            'data' => [
                'projects' => $projectQuery->limit(10)->get()->map(fn (Project $project) => [
                    'type' => 'project',
                    'id' => $project->id,
                    'label' => $project->reference.' — '.$project->name,
                    'reference' => $project->reference,
                ]),
                'tasks' => Task::query()
                    ->whereIn('project_id', $projectIds)
                    ->where('reference', 'like', "%{$query}%")
                    ->with('project')
                    ->limit(10)
                    ->get()
                    ->map(fn (Task $task) => [
                        'type' => 'task',
                        'id' => $task->id,
                        'label' => $task->reference.' — '.$task->localizedTitle(),
                        'project_reference' => $task->project?->reference,
                    ]),
                'clients' => Client::query()
                    ->where('name', 'like', "%{$query}%")
                    ->limit(5)
                    ->get()
                    ->map(fn (Client $client) => [
                        'type' => 'client',
                        'id' => $client->id,
                        'label' => $client->name,
                    ]),
                'samples' => [],
                'site_visits' => [],
            ],
        ]);
    }
}
'''

TASK_ACTIVITY_APPEND = '''
    public function handleTaskStarted(TaskStarted $event): void
    {
        $this->safely(fn () => $this->recordStarted($event));
    }

    public function handleTaskUnblocked(TaskUnblocked $event): void
    {
        $this->safely(fn () => $this->recordUnblocked($event));
    }

    public function handleTaskOverdue(TaskOverdue $event): void
    {
        $this->safely(fn () => $this->recordOverdue($event));
    }
'''

TASK_ACTIVITY_METHODS = '''
    private function recordStarted(TaskStarted $event): void
    {
        $task = $event->task->loadMissing(['department', 'project']);

        $this->recorder->record(
            type: 'task.started',
            severity: ActivitySeverity::Info,
            messageEn: __(':user started :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('en'),
            ], 'en'),
            messageAr: __(':user started :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('ar'),
            ], 'ar'),
            actor: $event->user,
            project: $task->project,
            subject: $task,
            department: $task->department,
        );
    }

    private function recordUnblocked(TaskUnblocked $event): void
    {
        $task = $event->task->loadMissing(['department', 'project']);

        $this->recorder->record(
            type: 'task.unblocked',
            severity: ActivitySeverity::Success,
            messageEn: __(':user unblocked :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('en'),
            ], 'en'),
            messageAr: __(':user unblocked :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('ar'),
            ], 'ar'),
            actor: $event->user,
            project: $task->project,
            subject: $task,
            department: $task->department,
        );
    }

    private function recordOverdue(TaskOverdue $event): void
    {
        $task = $event->task->loadMissing(['department', 'project']);

        $this->recorder->record(
            type: 'task.overdue',
            severity: ActivitySeverity::Warning,
            messageEn: __(':title is overdue.', ['title' => $task->localizedTitle('en')], 'en'),
            messageAr: __(':title is overdue.', ['title' => $task->localizedTitle('ar')], 'ar'),
            project: $task->project,
            subject: $task,
            department: $task->department,
        );
    }
'''


def patch_activity_recorder(content: str) -> str:
    if "ActivityRecorded" in content:
        return content
    content = content.replace(
        "use App\\Enums\\ActivitySeverity;\nuse App\\Models\\ActivityEvent;",
        "use App\\Enums\\ActivitySeverity;\nuse App\\Events\\ActivityRecorded;\nuse App\\Models\\ActivityEvent;",
    )
    old = """        return ActivityEvent::query()->create([
            'type' => $type,
            'severity' => $severity,
            'message_en' => $messageEn,
            'message_ar' => $messageAr,
            'actor_user_id' => $actor?->id,
            'project_id' => $project?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'department_id' => $department?->id,
            'visible_to_permission' => $visibleToPermission,
            'payload' => $payload,
            'created_at' => now(),
        ]);
    }
}"""
    new = """        $event = ActivityEvent::query()->create([
            'type' => $type,
            'severity' => $severity,
            'message_en' => $messageEn,
            'message_ar' => $messageAr,
            'actor_user_id' => $actor?->id,
            'project_id' => $project?->id,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'department_id' => $department?->id,
            'visible_to_permission' => $visibleToPermission,
            'payload' => $payload,
            'created_at' => now(),
        ]);

        event(new ActivityRecorded($event));

        return $event;
    }
}"""
    return content.replace(old, new)


def patch_record_task_activity(content: str) -> str:
    for use in ["TaskStarted", "TaskUnblocked", "TaskOverdue"]:
        if use not in content:
            content = content.replace(
                "use App\\Events\\TaskCreated;",
                f"use App\\Events\\TaskCreated;\nuse App\\Events\\{use};",
            )
    if "handleTaskStarted" not in content:
        content = content.replace(
            "    public function handleTaskBlocked(TaskBlocked $event): void\n    {\n        $this->safely(fn () => $this->recordBlocked($event));\n    }",
            "    public function handleTaskBlocked(TaskBlocked $event): void\n    {\n        $this->safely(fn () => $this->recordBlocked($event));\n    }"
            + TASK_ACTIVITY_APPEND,
        )
    if "recordStarted" not in content:
        content = content.replace(
            "            department: $task->department,\n        );\n    }\n}",
            "            department: $task->department,\n        );\n    }"
            + TASK_ACTIVITY_METHODS
            + "\n}",
            1,
        )
    return content


def patch_routes(content: str) -> str:
    content = content.replace(
        "Route::get('stream', StreamController::class)",
        "Route::get('stream', [StreamController::class, '__invoke'])",
    )
    content = content.replace(
        "Route::get('search', SearchController::class)",
        "Route::get('search', [SearchController::class, '__invoke'])",
    )
    return content


def main() -> None:
    for rel, body in FILES.items():
        path = ROOT / rel
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(body)

    (ROOT / "app/Http/Controllers/Api/V1/ProjectController.php").write_text(PROJECT_CONTROLLER)
    (ROOT / "app/Http/Controllers/Api/V1/SearchController.php").write_text(SEARCH_CONTROLLER)

    recorder = ROOT / "app/Services/Activity/ActivityRecorder.php"
    recorder.write_text(patch_activity_recorder(recorder.read_text()))

    task_activity = ROOT / "app/Listeners/RecordTaskActivity.php"
    task_activity.write_text(patch_record_task_activity(task_activity.read_text()))

    routes = Path("/workspace/colortek-api/routes/api.php")
    routes.write_text(patch_routes(routes.read_text()))

    print("phase5 backend files applied")


if __name__ == "__main__":
    main()
