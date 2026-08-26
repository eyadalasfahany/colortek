<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Services\Audit\AuditLogQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuditLogController extends Controller
{
    public function __construct(private AuditLogQueryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        return AuditLogResource::collection($this->service->paginate($request))->response();
    }
}
