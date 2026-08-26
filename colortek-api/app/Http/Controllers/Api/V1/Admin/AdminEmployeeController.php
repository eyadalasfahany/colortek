<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EmployeeRequest;
use App\Http\Resources\Admin\EmployeeResource;
use App\Models\Employee;
use App\Services\Admin\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminEmployeeController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private EmployeeService $service) {}

    public function index(Request $r): JsonResponse
    {
        $this->authorizeAdmin('employee.manage');
        $this->authorize('viewAny', Employee::class);

        return EmployeeResource::collection($this->service->paginate($r->only(['q', 'active', 'department_id']), (int) $r->integer('per_page', 15)))->response();
    }

    public function store(EmployeeRequest $r): JsonResponse
    {
        $this->authorizeAdmin('employee.manage');
        $this->authorize('create', Employee::class);

        return response()->json(['data' => EmployeeResource::make($this->service->store($r->validated())->load(['department', 'user']))], 201);
    }

    public function update(EmployeeRequest $r, int $id): JsonResponse
    {
        $e = $this->service->findOrFail($id);
        $this->authorizeAdmin('employee.manage');
        $this->authorize('update', $e);

        return response()->json(['data' => EmployeeResource::make($this->service->update($e, $r->validated())->load(['department', 'user']))]);
    }
}
