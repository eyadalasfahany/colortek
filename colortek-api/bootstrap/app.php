<?php

declare(strict_types=1);

use App\Exceptions\InvalidTaskTransition;
use App\Exceptions\TaskAlreadyClaimed;
use App\Exceptions\TaskNotReadyToComplete;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            SetLocale::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request): ?string {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (TaskAlreadyClaimed $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => $e->getErrorCode(),
                ], 409);
            }
        });

        $exceptions->render(function (TaskNotReadyToComplete $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => $e->getErrorCode(),
                    'errors' => $e->getErrors(),
                ], 422);
            }
        });

        $exceptions->render(function (InvalidTaskTransition $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'code' => 'task.invalid_transition',
                ], 422);
            }
        });
    })->create();
