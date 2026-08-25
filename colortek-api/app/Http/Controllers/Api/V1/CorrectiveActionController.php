<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CorrectiveActionResource;
use App\Models\CorrectiveAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CorrectiveActionController extends Controller
{
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

    public function show(int $id): JsonResponse
    {
        $action = CorrectiveAction::query()->with(['checklistItem', 'siteVisit', 'task'])->findOrFail($id);
        $this->authorize('view', $action);

        return response()->json(['data' => CorrectiveActionResource::make($action)]);
    }
}
