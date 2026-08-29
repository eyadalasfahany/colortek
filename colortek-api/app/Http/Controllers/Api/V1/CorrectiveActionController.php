<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CorrectiveActionStoreRequest;
use App\Http\Resources\CorrectiveActionResource;
use App\Models\CorrectiveAction;
use App\Models\SiteVisit;
use App\Services\Site\CorrectiveActionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CorrectiveActionController extends Controller
{
    public function __construct(private CorrectiveActionService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CorrectiveAction::class);

        $query = CorrectiveAction::query()->with(['checklistItem', 'siteVisit']);

        if ($request->filled('site_visit_id')) {
            $query->where('site_visit_id', $request->integer('site_visit_id'));
        }

        return response()->json([
            'data' => CorrectiveActionResource::collection($query->latest('id')->get()),
        ]);
    }

    public function store(CorrectiveActionStoreRequest $request): JsonResponse
    {
        $this->authorize('create', CorrectiveAction::class);

        $visit = SiteVisit::query()->findOrFail($request->integer('site_visit_id'));

        return response()->json([
            'data' => CorrectiveActionResource::make(
                $this->service->createForVisit($visit, $request->validated()),
            ),
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        $action = CorrectiveAction::query()->with(['checklistItem', 'siteVisit', 'task'])->findOrFail($id);
        $this->authorize('view', $action);

        return response()->json(['data' => CorrectiveActionResource::make($action)]);
    }
}
