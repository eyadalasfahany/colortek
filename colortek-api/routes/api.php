<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ActivityController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CorrectiveActionController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EnumController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SiteChecklistItemController;
use App\Http\Controllers\Api\V1\SiteVisitController;
use App\Http\Controllers\Api\V1\StreamController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Middleware\AuthenticateStream;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('stream', [StreamController::class, '__invoke'])->middleware(AuthenticateStream::class);

    Route::post('auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('tasks', [TaskController::class, 'index']);
        Route::get('tasks/{id}', [TaskController::class, 'show'])->whereNumber('id');
        Route::post('tasks/{id}/claim', [TaskController::class, 'claim'])->whereNumber('id');
        Route::post('tasks/{id}/release', [TaskController::class, 'release'])->whereNumber('id');
        Route::post('tasks/{id}/start', [TaskController::class, 'start'])->whereNumber('id');
        Route::post('tasks/{id}/pause', [TaskController::class, 'pause'])->whereNumber('id');
        Route::post('tasks/{id}/block', [TaskController::class, 'block'])->whereNumber('id');
        Route::post('tasks/{id}/complete', [TaskController::class, 'complete'])->whereNumber('id');
        Route::post('tasks/{id}/attachments', [TaskController::class, 'attach'])->whereNumber('id');

        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::get('attachments/{id}', [AttachmentController::class, 'show'])->whereNumber('id');

        Route::get('payments', [PaymentController::class, 'index']);
        Route::get('payments/{id}', [PaymentController::class, 'show'])->whereNumber('id');
        Route::post('projects/{id}/payments', [PaymentController::class, 'storeForProject'])->whereNumber('id');

        Route::get('journals', [JournalController::class, 'index']);
        Route::get('journals/{date}', [JournalController::class, 'show']);

        Route::get('site-visits', [SiteVisitController::class, 'index']);
        Route::get('site-visits/{id}', [SiteVisitController::class, 'show'])->whereNumber('id');
        Route::patch('site-visits/{id}', [SiteVisitController::class, 'update'])->whereNumber('id');
        Route::post('site-visits/{id}/measurements', [SiteVisitController::class, 'measurements'])->whereNumber('id');
        Route::post('site-visits/{id}/submit', [SiteVisitController::class, 'submit'])->whereNumber('id');
        Route::get('site-visits/{id}/pdf', [SiteVisitController::class, 'pdf'])->whereNumber('id');
        Route::post('projects/{id}/site-visits', [SiteVisitController::class, 'store'])->whereNumber('id');
        Route::get('projects/{id}/site-visits', [SiteVisitController::class, 'forProject'])->whereNumber('id');
        Route::get('site-checklist-items', [SiteChecklistItemController::class, 'index']);
        Route::get('options/checklist-items', [SiteChecklistItemController::class, 'options']);
        Route::get('corrective-actions', [CorrectiveActionController::class, 'index']);
        Route::get('corrective-actions/{id}', [CorrectiveActionController::class, 'show'])->whereNumber('id');
        Route::patch('corrective-actions/{id}', [CorrectiveActionController::class, 'update'])->whereNumber('id');
        Route::post('tasks/{id}/override-site-block', [TaskController::class, 'overrideSiteBlock'])->whereNumber('id');

        Route::get('activity', [ActivityController::class, 'index']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);
        Route::get('projects', [ProjectController::class, 'index']);
        Route::get('projects/by-reference/{reference}', [ProjectController::class, 'showByReference']);
        Route::get('projects/{id}', [ProjectController::class, 'show'])->whereNumber('id');
        Route::get('projects/{id}/workflow', [ProjectController::class, 'workflow'])->whereNumber('id');
        Route::get('projects/{id}/tasks', [ProjectController::class, 'tasks'])->whereNumber('id');
        Route::get('projects/{id}/payments', [ProjectController::class, 'payments'])->whereNumber('id');
        Route::get('projects/{id}/hours', [ProjectController::class, 'hours'])->whereNumber('id');
        Route::get('projects/{id}/samples', [ProjectController::class, 'samples'])->whereNumber('id');
        Route::get('projects/{id}/activity', [ProjectController::class, 'activity'])->whereNumber('id');
        Route::get('dashboard/control-room', [DashboardController::class, 'controlRoom']);
        Route::get('dashboard/workshop', [DashboardController::class, 'workshop']);
        Route::get('dashboard/site', [DashboardController::class, 'site']);
        Route::get('dashboard/samples', [DashboardController::class, 'samples']);
        Route::get('search', [SearchController::class, '__invoke']);

        Route::get('enums/{name}', [EnumController::class, 'show']);
    });
});
