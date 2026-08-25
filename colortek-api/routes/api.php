<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AdminAccessController;
use App\Http\Controllers\Api\V1\Admin\AdminCalendarController;
use App\Http\Controllers\Api\V1\Admin\AdminEmployeeController;
use App\Http\Controllers\Api\V1\Admin\AdminFailedJobController;
use App\Http\Controllers\Api\V1\Admin\AdminHolidayController;
use App\Http\Controllers\Api\V1\Admin\AdminPermissionController;
use App\Http\Controllers\Api\V1\Admin\AdminRoleController;
use App\Http\Controllers\Api\V1\Admin\AdminSettingController;
use App\Http\Controllers\Api\V1\Admin\AdminSiteChecklistItemController;
use App\Http\Controllers\Api\V1\Admin\AdminStalledInstanceController;
use App\Http\Controllers\Api\V1\Admin\AdminUnclaimedTaskController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminWorkflowTemplateController;
use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EnumController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\OptionsController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\SiteChecklistItemController;
use App\Http\Controllers\Api\V1\TaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
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

        Route::get('enums/{name}', [EnumController::class, 'show']);

        Route::get('options/checklist-items', [OptionsController::class, 'checklistItems']);
        Route::get('options/departments', [OptionsController::class, 'departments']);
        Route::get('options/users', [OptionsController::class, 'users']);
        Route::get('site-checklist-items', [SiteChecklistItemController::class, 'index']);

        Route::prefix('admin')->group(function (): void {
            Route::get('settings', [AdminSettingController::class, 'index']);
            Route::patch('settings', [AdminSettingController::class, 'update']);
            Route::post('calendar/impact', [AdminCalendarController::class, 'impact']);

            Route::get('holidays', [AdminHolidayController::class, 'index']);
            Route::post('holidays', [AdminHolidayController::class, 'store']);
            Route::patch('holidays/{id}', [AdminHolidayController::class, 'update'])->whereNumber('id');
            Route::delete('holidays/{id}', [AdminHolidayController::class, 'destroy'])->whereNumber('id');

            Route::get('roles', [AdminRoleController::class, 'index']);
            Route::post('roles', [AdminRoleController::class, 'store']);
            Route::patch('roles/{id}', [AdminRoleController::class, 'update'])->whereNumber('id');
            Route::delete('roles/{id}', [AdminRoleController::class, 'destroy'])->whereNumber('id');

            Route::get('permissions', [AdminPermissionController::class, 'index']);

            Route::get('users', [AdminUserController::class, 'index']);
            Route::post('users', [AdminUserController::class, 'store']);
            Route::patch('users/{id}', [AdminUserController::class, 'update'])->whereNumber('id');
            Route::post('users/{id}/roles', [AdminUserController::class, 'syncRoles'])->whereNumber('id');
            Route::get('users/{id}/effective-permissions', [AdminUserController::class, 'effectivePermissions'])->whereNumber('id');

            Route::get('employees', [AdminEmployeeController::class, 'index']);
            Route::post('employees', [AdminEmployeeController::class, 'store']);
            Route::patch('employees/{id}', [AdminEmployeeController::class, 'update'])->whereNumber('id');

            Route::get('access/coverage', [AdminAccessController::class, 'coverage']);

            Route::get('workflow-templates', [AdminWorkflowTemplateController::class, 'index']);
            Route::get('workflow-templates/{id}', [AdminWorkflowTemplateController::class, 'show'])->whereNumber('id');
            Route::patch('workflow-templates/{id}', [AdminWorkflowTemplateController::class, 'update'])->whereNumber('id');
            Route::post('workflow-templates/{id}/draft', [AdminWorkflowTemplateController::class, 'createDraft'])->whereNumber('id');
            Route::post('workflow-templates/{id}/publish', [AdminWorkflowTemplateController::class, 'publish'])->whereNumber('id');

            Route::get('site-checklist-items', [AdminSiteChecklistItemController::class, 'index']);
            Route::post('site-checklist-items', [AdminSiteChecklistItemController::class, 'store']);
            Route::patch('site-checklist-items/{id}', [AdminSiteChecklistItemController::class, 'update'])->whereNumber('id');

            Route::get('stalled-instances', [AdminStalledInstanceController::class, 'index']);
            Route::get('unclaimed-tasks', [AdminUnclaimedTaskController::class, 'index']);
            Route::get('failed-jobs', [AdminFailedJobController::class, 'index']);
            Route::post('failed-jobs/{uuid}/retry', [AdminFailedJobController::class, 'retry']);
        });
    });
});
