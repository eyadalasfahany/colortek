<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\StalledInstanceResource;
use App\Services\Admin\StalledInstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminStalledInstanceController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private StalledInstanceService $service) {}

    public function index(Request $r): JsonResponse
    {
        $this->authorizeAdmin('settings.manage');
        $p = $this->service->paginate((int) $r->integer('per_page', 15));

        return response()->json(['data' => StalledInstanceResource::collection($p->items()), 'meta' => ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'coverage_warnings' => $this->service->coverageWarnings()]]);
    }
}
