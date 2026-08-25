<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\TimerStartRequest;
use App\Http\Resources\TimeEntryResource;
use App\Models\Employee;
use App\Models\Task;
use App\Services\Tasks\TaskQueryService;
use App\Services\Time\TimerService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TimerController extends Controller
{
    public function __construct(
        private TimerService $timerService,
        private TaskQueryService $taskQueryService,
    ) {}

    public function start(TimerStartRequest $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id, $request);
        $employee = $request->filled('employee_id')
            ? Employee::query()->findOrFail($request->integer('employee_id'))
            : null;

        $entry = $this->timerService->start($task, $request->user(), $employee);

        return response()->json([
            'data' => TimeEntryResource::make($entry->load(['employee', 'task'])),
        ], 201);
    }

    public function stop(Request $request, int $id): JsonResponse
    {
        $task = $this->findTaskOrFail($id, $request);
        $entry = $this->timerService->stop($task, $request->user());

        return response()->json([
            'data' => $entry !== null ? TimeEntryResource::make($entry->load('employee')) : null,
        ]);
    }

    public function active(Request $request): JsonResponse
    {
        $entry = $this->timerService->activeForUser($request->user());

        return response()->json([
            'data' => $entry !== null ? TimeEntryResource::make($entry) : null,
        ]);
    }

    private function findTaskOrFail(int $id, Request $request): Task
    {
        try {
            return $this->taskQueryService->findForUser($id, $request->user());
        } catch (ModelNotFoundException) {
            throw new ModelNotFoundException(__('Task not found'));
        }
    }
}
