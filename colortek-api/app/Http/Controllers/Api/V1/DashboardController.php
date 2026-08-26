<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\ControlRoomService;
use App\Services\Dashboard\SamplesDashboardService;
use App\Services\Dashboard\SiteDashboardService;
use App\Services\Dashboard\WorkshopDashboardService;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __construct(private ControlRoomService $c, private WorkshopDashboardService $w, private SiteDashboardService $s, private SamplesDashboardService $sa) {}

    public function controlRoom(Request $request)
    {
        abort_unless($request->user()->can('project.view_all'), 403);

        return response()->json(['data' => $this->c->build($request->user())]);
    }

    public function workshop(Request $request)
    {
        return response()->json(['data' => $this->w->build($request->user())]);
    }

    public function site(Request $request)
    {
        abort_unless($request->user()->can('site.view'), 403);

        return response()->json(['data' => $this->s->build($request->user())]);
    }

    public function samples(Request $request)
    {
        abort_unless($request->user()->can('sample.view'), 403);

        return response()->json(['data' => $this->sa->build($request->user())]);
    }
}
