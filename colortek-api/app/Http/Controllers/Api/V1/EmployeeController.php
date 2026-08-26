<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeOptionResource;
use App\Models\Employee;
use App\Services\Employees\EmployeeQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmployeeController extends Controller
{
    public function __construct(private EmployeeQueryService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        return response()->json([
            'data' => EmployeeOptionResource::collection(
                $this->service->optionsForUser($request->user()),
            ),
        ]);
    }
}
