from pathlib import Path
R = Path(__file__).resolve().parent.parent

def w(p, c):
    f = R/p
    f.parent.mkdir(parents=True, exist_ok=True)
    f.write_text(c.strip()+"\n")

# ActivityQuery
w("app/Services/Activity/ActivityQuery.php", """<?php
declare(strict_types=1);
namespace App\\Services\\Activity;
use App\\Models\\ActivityEvent; use App\\Models\\User; use App\\Services\\Projects\\ProjectVisibility; use Illuminate\\Database\\Eloquent\\Builder;
final class ActivityQuery {
 public function __construct(private ProjectVisibility $visibility) {}
 public function forUser(User $user): Builder {
  return $this->visibility->applyToActivity(ActivityEvent::query()->with(['actor','department','project']), $user);
 }
}""")

w("app/Services/Activity/SseStream.php", """<?php
declare(strict_types=1);
namespace App\\Services\\Activity;
use App\\Http\\Resources\\ActivityEventResource; use App\\Models\\User; use Symfony\\Component\\HttpFoundation\\StreamedResponse;
final class SseStream {
 public function __construct(private ActivityQuery $activityQuery) {}
 public function response(User $user, ?int $lastEventId): StreamedResponse {
  return response()->stream(function () use ($user, $lastEventId): void {
   $cursor = $lastEventId ?? 0; $i=0;
   while (!connection_aborted()) {
    $events = $this->activityQuery->forUser($user)->where('id','>',$cursor)->orderBy('id')->limit(50)->get();
    foreach ($events as $event) {
     echo 'id: '.$event->id."\\nevent: activity\\ndata: ".json_encode(ActivityEventResource::make($event)->resolve(), JSON_THROW_ON_ERROR)."\\n\\n";
     $cursor = $event->id;
    }
    if ($events->isNotEmpty()) { @ob_flush(); flush(); }
    sleep(1);
    if (app()->environment('testing') && ++$i >= 1) break;
   }
  }, 200, ['Content-Type'=>'text/event-stream','Cache-Control'=>'no-cache','X-Accel-Buffering'=>'no']);
 }
}""")

w("app/Events/ActivityRecorded.php", """<?php
declare(strict_types=1);
namespace App\\Events;
use App\\Models\\ActivityEvent; use Illuminate\\Foundation\\Events\\Dispatchable; use Illuminate\\Queue\\SerializesModels;
final class ActivityRecorded { use Dispatchable, SerializesModels; public function __construct(public ActivityEvent $activityEvent) {} }""")

w("app/Http/Middleware/AuthenticateStream.php", """<?php
declare(strict_types=1);
namespace App\\Http\\Middleware;
use Closure; use Illuminate\\Http\\Request; use Laravel\\Sanctum\\PersonalAccessToken; use Symfony\\Component\\HttpFoundation\\Response;
final class AuthenticateStream {
 public function handle(Request $request, Closure $next): Response {
  if ($request->user()) return $next($request);
  foreach ([$request->bearerToken(), $request->query('token')] as $token) {
   if (!is_string($token) || $token==='') continue;
   $a = PersonalAccessToken::findToken($token);
   if ($a) { $request->setUserResolver(fn()=> $a->tokenable); return $next($request); }
  }
  return response()->json(['message'=>'Unauthenticated.'], 401);
 }
}""")

w("app/Http/Resources/UserSummaryResource.php", """<?php
declare(strict_types=1);
namespace App\\Http\\Resources;
use Illuminate\\Http\\Resources\\Json\\JsonResource;
final class UserSummaryResource extends JsonResource { public function toArray($request): array { return ['id'=>$this->id,'name'=>$this->name]; } }""")

w("app/Http/Resources/ActivityEventResource.php", """<?php
declare(strict_types=1);
namespace App\\Http\\Resources;
use Illuminate\\Http\\Resources\\Json\\JsonResource;
final class ActivityEventResource extends JsonResource {
 public function toArray($request): array {
  $locale = $request->user()?->locale ?? app()->getLocale();
  $payload = $this->payload ?? [];
  return ['id'=>$this->id,'type'=>$this->type,'severity'=>$this->severity->value,
   'message'=>$locale==='ar'?$this->message_ar:$this->message_en,
   'actor'=>UserSummaryResource::make($this->whenLoaded('actor')),
   'department'=>DepartmentResource::make($this->whenLoaded('department')),
   'project'=>ProjectSummaryResource::make($this->whenLoaded('project')),
   'link'=>$payload['route']??null,'link_params'=>$payload['params']??null,
   'created_at'=>$this->created_at?->toIso8601String()];
 }
}""")

w("app/Http/Filters/ActivityFilter.php", """<?php
declare(strict_types=1);
namespace App\\Http\\Filters;
use App\\Models\\User; use Illuminate\\Http\\Request;
final class ActivityFilter {
 public function apply(Request $request, $query, User $user) {
  if ($request->filled('since')) $query->where('id','>',$request->integer('since'));
  if ($request->filled('project_id')) $query->where('project_id',$request->integer('project_id'));
  if ($request->filled('department_id')) $query->where('department_id',$request->integer('department_id'));
  if ($request->filled('severity')) $query->where('severity',$request->string('severity')->toString());
  if ($request->filled('type')) $query->where('type',$request->string('type')->toString());
  return $query->orderByDesc('id');
 }
}""")

w("app/Http/Controllers/Api/V1/ActivityController.php", """<?php
declare(strict_types=1);
namespace App\\Http\\Controllers\\Api\\V1;
use App\\Http\\Controllers\\Controller; use App\\Http\\Filters\\ActivityFilter; use App\\Http\\Resources\\ActivityEventResource; use App\\Services\\Activity\\ActivityQuery;
final class ActivityController extends Controller {
 public function __construct(private ActivityQuery $q, private ActivityFilter $f) {}
 public function index($request) {
  $query = $this->f->apply($request, $this->q->forUser($request->user()), $request->user());
  return ActivityEventResource::collection($query->paginate($request->integer('per_page',15)))->response();
 }
}""")

w("app/Http/Controllers/Api/V1/StreamController.php", """<?php
declare(strict_types=1);
namespace App\\Http\\Controllers\\Api\\V1;
use App\\Http\\Controllers\\Controller; use App\\Services\\Activity\\SseStream;
final class StreamController extends Controller {
 public function __construct(private SseStream $sse) {}
 public function __invoke($request) {
  $last = $request->header('Last-Event-ID');
  return $this->sse->response($request->user(), is_numeric($last)?(int)$last:null);
 }
}""")

print('core ok')
