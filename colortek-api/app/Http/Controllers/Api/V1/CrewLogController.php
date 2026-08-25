<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CrewLogRequest;
use App\Http\Resources\CrewLogResource;
use App\Models\CrewLog;
use App\Models\Project;
use App\Services\Time\CrewLogService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CrewLogController extends Controller
{
    public function __construct(private CrewLogService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CrewLog::class);

        return CrewLogResource::collection(
            $this->service->paginate($request->user(), (int) $request->integer('per_page', 15)),
        )->response();
    }

    public function store(CrewLogRequest $request, int $projectId): JsonResponse
    {
        $project = Project::query()->find($projectId);
        if ($project === null) {
            throw new ModelNotFoundException(__('Project not found'));
        }

        $this->authorize('view', $project);
        $this->authorize('create', CrewLog::class);

        $log = $this->service->createForProject($project, $request->user(), $request->validated());

        return response()->json(['data' => CrewLogResource::make($log)], 201);
    }

    public function update(CrewLogRequest $request, int $id): JsonResponse
    {
        $log = $this->service->findOrFail($id);
        $this->authorize('update', $log);

        return response()->json([
            'data' => CrewLogResource::make($this->service->update($log, $request->user(), $request->validated())),
        ]);
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $log = $this->service->findOrFail($id);
        $this->authorize('submit', $log);

        return response()->json([
            'data' => CrewLogResource::make($this->service->submit($log, $request->user())),
        ]);
    }
}
