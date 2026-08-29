<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OdooSyncLogResource;
use App\Services\Admin\OdooSyncLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminOdooSyncLogController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private OdooSyncLogService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin('settings.manage');

        return OdooSyncLogResource::collection($this->service->paginate(
            (int) $request->integer('per_page', 15),
            $request->only(['operation', 'status']),
        ))->response();
    }
}
