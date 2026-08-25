#!/usr/bin/env python3
"""Apply spec compliance route/controller/policy wiring."""
from pathlib import Path

ROOT = Path("/workspace/colortek-api")

# Patch routes/api.php imports and task routes block
routes = (ROOT / "routes/api.php").read_text()

imports_add = """
use App\\Http\\Controllers\\Api\\V1\\AuditLogController;
use App\\Http\\Controllers\\Api\\V1\\CrewLogController;
use App\\Http\\Controllers\\Api\\V1\\EmployeeController;
use App\\Http\\Controllers\\Api\\V1\\TimeEntryController;
use App\\Http\\Controllers\\Api\\V1\\TimerController;
use App\\Http\\Middleware\\HandleIdempotencyKey;
"""

for line in imports_add.strip().splitlines():
    if line.strip() not in routes:
        routes = routes.replace(
            "use App\\Http\\Middleware\\AuthenticateStream;",
            "use App\\Http\\Middleware\\AuthenticateStream;\n" + line,
        )

old_tasks = """        Route::get('tasks', [TaskController::class, 'index']);
        Route::get('tasks/{id}', [TaskController::class, 'show'])->whereNumber('id');
        Route::post('tasks/{id}/claim', [TaskController::class, 'claim'])->whereNumber('id');
        Route::post('tasks/{id}/release', [TaskController::class, 'release'])->whereNumber('id');
        Route::post('tasks/{id}/start', [TaskController::class, 'start'])->whereNumber('id');
        Route::post('tasks/{id}/pause', [TaskController::class, 'pause'])->whereNumber('id');
        Route::post('tasks/{id}/block', [TaskController::class, 'block'])->whereNumber('id');
        Route::post('tasks/{id}/complete', [TaskController::class, 'complete'])->whereNumber('id');"""

new_tasks = """        Route::get('tasks', [TaskController::class, 'index']);
        Route::post('tasks', [TaskController::class, 'store']);
        Route::get('tasks/{id}', [TaskController::class, 'show'])->whereNumber('id');
        Route::post('tasks/{id}/claim', [TaskController::class, 'claim'])->whereNumber('id')->middleware(HandleIdempotencyKey::class);
        Route::post('tasks/{id}/release', [TaskController::class, 'release'])->whereNumber('id');
        Route::post('tasks/{id}/start', [TaskController::class, 'start'])->whereNumber('id');
        Route::post('tasks/{id}/pause', [TaskController::class, 'pause'])->whereNumber('id');
        Route::post('tasks/{id}/resume', [TaskController::class, 'resume'])->whereNumber('id');
        Route::post('tasks/{id}/block', [TaskController::class, 'block'])->whereNumber('id');
        Route::post('tasks/{id}/unblock', [TaskController::class, 'unblock'])->whereNumber('id');
        Route::post('tasks/{id}/complete', [TaskController::class, 'complete'])->whereNumber('id')->middleware(HandleIdempotencyKey::class);
        Route::post('tasks/{id}/comments', [TaskController::class, 'comment'])->whereNumber('id');
        Route::post('tasks/{id}/reassign', [TaskController::class, 'reassign'])->whereNumber('id');
        Route::patch('tasks/{id}/deadline', [TaskController::class, 'updateDeadline'])->whereNumber('id');
        Route::post('tasks/{id}/timer/start', [TimerController::class, 'start'])->whereNumber('id')->middleware(HandleIdempotencyKey::class);
        Route::post('tasks/{id}/timer/stop', [TimerController::class, 'stop'])->whereNumber('id');
        Route::get('timers/active', [TimerController::class, 'active']);
        Route::patch('time-entries/{id}', [TimeEntryController::class, 'update'])->whereNumber('id');
        Route::get('crew-logs', [CrewLogController::class, 'index']);
        Route::post('projects/{id}/crew-logs', [CrewLogController::class, 'store'])->whereNumber('id');
        Route::patch('crew-logs/{id}', [CrewLogController::class, 'update'])->whereNumber('id');
        Route::post('crew-logs/{id}/submit', [CrewLogController::class, 'submit'])->whereNumber('id');
        Route::get('employees', [EmployeeController::class, 'index']);
        Route::get('audit-logs', [AuditLogController::class, 'index']);"""

routes = routes.replace(old_tasks, new_tasks)

routes = routes.replace(
    "        Route::get('attachments/{id}', [AttachmentController::class, 'show'])->whereNumber('id');",
    "        Route::get('attachments/{id}', [AttachmentController::class, 'show'])->whereNumber('id');\n        Route::delete('attachments/{id}', [AttachmentController::class, 'destroy'])->whereNumber('id');",
)

routes = routes.replace(
    "        Route::get('journals/{date}', [JournalController::class, 'show']);",
    "        Route::get('journals/{date}', [JournalController::class, 'show']);\n        Route::post('journals/{date}/submit', [JournalController::class, 'submit']);\n        Route::post('journals/{date}/reopen', [JournalController::class, 'reopen']);",
)

routes = routes.replace(
    "        Route::post('site-visits/{id}/measurements', [SiteVisitController::class, 'measurements'])->whereNumber('id');",
    "        Route::post('site-visits/{id}/measurements', [SiteVisitController::class, 'measurements'])->whereNumber('id')->middleware(HandleIdempotencyKey::class);",
)

routes = routes.replace(
    "        Route::post('site-visits/{id}/submit', [SiteVisitController::class, 'submit'])->whereNumber('id');",
    "        Route::post('site-visits/{id}/submit', [SiteVisitController::class, 'submit'])->whereNumber('id');\n        Route::post('site-visits/{id}/readiness', [SiteVisitController::class, 'readiness'])->whereNumber('id');",
)

routes = routes.replace(
    "        Route::get('options/users', [OptionsController::class, 'users']);",
    "        Route::get('options/users', [OptionsController::class, 'users']);\n        Route::get('options/employees', [OptionsController::class, 'employees']);\n        Route::get('options/clients', [OptionsController::class, 'clients']);\n        Route::get('options/projects', [OptionsController::class, 'projects']);\n        Route::get('options/blocker-categories', [OptionsController::class, 'blockerCategories']);",
)

routes = routes.replace(
    "        Route::get('projects', [ProjectController::class, 'index']);\n        Route::get('projects/by-reference/{reference}', [ProjectController::class, 'showByReference']);\n        Route::get('projects/{id}', [ProjectController::class, 'show'])->whereNumber('id');",
    "        Route::get('projects', [ProjectController::class, 'index']);\n        Route::post('projects', [ProjectController::class, 'store']);\n        Route::get('projects/by-reference/{reference}', [ProjectController::class, 'showByReference']);\n        Route::get('projects/{id}', [ProjectController::class, 'show'])->whereNumber('id');\n        Route::patch('projects/{id}', [ProjectController::class, 'update'])->whereNumber('id');\n        Route::post('projects/{id}/complete', [ProjectController::class, 'complete'])->whereNumber('id');\n        Route::post('projects/{id}/cancel', [ProjectController::class, 'cancel'])->whereNumber('id');",
)

(ROOT / "routes/api.php").write_text(routes)
print("routes/api.php updated")
