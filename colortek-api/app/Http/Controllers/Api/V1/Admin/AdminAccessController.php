<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Services\Admin\AccessCoverageService;
use Illuminate\Http\JsonResponse;

final class AdminAccessController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private AccessCoverageService $service) {}

    public function coverage(): JsonResponse
    {
        $this->authorizeAdmin('role.manage');

        return response()->json(['data' => $this->service->gaps()]);
    }
}
