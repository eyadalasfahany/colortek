<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\FailedJobResource;
use App\Services\Admin\FailedJobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminFailedJobController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private FailedJobService $service) {}

    public function index(Request $r): JsonResponse
    {
        $this->authorizeAdmin('settings.manage');

        return FailedJobResource::collection($this->service->paginate((int) $r->integer('per_page', 15)))->response();
    }

    public function retry(string $uuid): JsonResponse
    {
        $this->authorizeAdmin('settings.manage');
        $this->service->retry($uuid);

        return response()->json(['data' => ['retried' => true]]);
    }
}
