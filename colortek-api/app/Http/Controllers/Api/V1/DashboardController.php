<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Dashboard\ControlRoomService;
use App\Services\Dashboard\SamplesDashboardService;
use App\Services\Dashboard\SiteDashboardService;
use App\Services\Dashboard\WorkshopDashboardService;

final class DashboardController extends Controller
{
    public function __construct(private ControlRoomService $c, private WorkshopDashboardService $w, private SiteDashboardService $s, private SamplesDashboardService $sa) {}

    public function controlRoom($r)
    {
        abort_unless($r->user()->can('project.view_all'), 403);

        return response()->json(['data' => $this->c->build($r->user())]);
    }

    public function workshop($r)
    {
        return response()->json(['data' => $this->w->build($r->user())]);
    }

    public function site($r)
    {
        abort_unless($r->user()->can('site.view'), 403);

        return response()->json(['data' => $this->s->build($r->user())]);
    }

    public function samples($r)
    {
        abort_unless($r->user()->can('sample.view'), 403);

        return response()->json(['data' => $this->sa->build($r->user())]);
    }
}
