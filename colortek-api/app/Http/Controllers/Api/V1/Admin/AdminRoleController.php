<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

final class AdminRoleController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private RoleService $service) {}

    public function index(Request $r): JsonResponse
    {
        $this->authorizeAdmin('role.manage');
        $this->authorize('viewAny', Role::class);

        return RoleResource::collection($this->service->paginate((int) $r->integer('per_page', 15)))->response();
    }

    public function store(RoleRequest $r): JsonResponse
    {
        $this->authorizeAdmin('role.manage');
        $this->authorize('create', Role::class);

        return response()->json(['data' => RoleResource::make($this->service->store($r->validated(), $r->user()))], 201);
    }

    public function update(RoleRequest $r, int $id): JsonResponse
    {
        $role = $this->service->findOrFail($id);
        $this->authorizeAdmin('role.manage');
        $this->authorize('update', $role);

        return response()->json(['data' => RoleResource::make($this->service->update($role, $r->validated(), $r->user()))]);
    }

    public function destroy(int $id): JsonResponse
    {
        $role = $this->service->findOrFail($id);
        $this->authorizeAdmin('role.manage');
        $this->authorize('delete', $role);

        return response()->json(['data' => $this->service->delete($role)]);
    }
}
