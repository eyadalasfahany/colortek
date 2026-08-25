<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Filters\ActivityFilter;
use App\Http\Resources\ActivityEventResource;
use App\Services\Activity\ActivityQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ActivityController extends Controller
{
    public function __construct(
        private ActivityQuery $activityQuery,
        private ActivityFilter $activityFilter,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->activityFilter->apply(
            $request,
            $this->activityQuery->forUser($request->user()),
            $request->user(),
        );

        return ActivityEventResource::collection(
            $query->paginate($request->integer('per_page', 15)),
        )->response();
    }
}
