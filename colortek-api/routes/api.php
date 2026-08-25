<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AttachmentController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\EnumController;
use App\Http\Controllers\Api\V1\FormulaController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\SampleController;
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

        Route::get('samples', [SampleController::class, 'index']);
        Route::post('samples', [SampleController::class, 'store']);
        Route::get('samples/{identifier}', [SampleController::class, 'show']);
        Route::get('samples/{identifier}/chain', [SampleController::class, 'chain']);
        Route::get('projects/{id}/samples', [SampleController::class, 'forProject'])->whereNumber('id');
        Route::post('samples/{identifier}/modification', [SampleController::class, 'modification']);
        Route::post('samples/{identifier}/approval-form', [SampleController::class, 'approvalForm']);
        Route::post('samples/{identifier}/client-decision', [SampleController::class, 'clientDecision']);
        Route::get('samples/{identifier}/formulas', [FormulaController::class, 'index']);
        Route::post('samples/{identifier}/formulas', [FormulaController::class, 'store']);
        Route::post('formulas/{id}/register', [FormulaController::class, 'register'])->whereNumber('id');
        Route::patch('formulas/{id}', [FormulaController::class, 'update'])->whereNumber('id');

        Route::get('journals', [JournalController::class, 'index']);
        Route::get('journals/{date}', [JournalController::class, 'show']);

        Route::get('enums/{name}', [EnumController::class, 'show']);
    });
});
