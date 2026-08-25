<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\ControlRoomService;
use App\Services\Dashboard\SamplesDashboardService;
use App\Services\Dashboard\SiteDashboardService;
use App\Services\Dashboard\WorkshopDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(
        private ControlRoomService $controlRoom,
        private WorkshopDashboardService $workshop,
        private SiteDashboardService $site,
        private SamplesDashboardService $samples,
    ) {}

    public function controlRoom(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('project.view_all'), 403);

        return response()->json(['data' => $this->controlRoom->build($request->user())]);
    }

    public function workshop(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->workshop->build($request->user())]);
    }

    public function site(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('site.view'), 403);

        return response()->json(['data' => $this->site->build($request->user())]);
    }

    public function samples(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('sample.view'), 403);

        return response()->json(['data' => $this->samples->build($request->user())]);
    }
}
