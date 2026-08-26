<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HolidayRequest;
use App\Http\Resources\Admin\HolidayResource;
use App\Models\Holiday;
use App\Services\Admin\HolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminHolidayController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private HolidayService $service) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizeAdmin('holiday.manage');
        $this->authorize('viewAny', Holiday::class);

        return HolidayResource::collection($this->service->paginate((int) $request->integer('per_page', 15)))->response();
    }

    public function store(HolidayRequest $request): JsonResponse
    {
        $this->authorizeAdmin('holiday.manage');
        $this->authorize('create', Holiday::class);

        return response()->json(['data' => HolidayResource::make($this->service->store($request->validated(), $request->user(), (bool) $request->boolean('confirm')))], 201);
    }

    public function update(HolidayRequest $request, int $id): JsonResponse
    {
        $h = $this->service->findOrFail($id);
        $this->authorizeAdmin('holiday.manage');
        $this->authorize('update', $h);

        return response()->json(['data' => HolidayResource::make($this->service->update($h, $request->validated(), $request->user(), (bool) $request->boolean('confirm')))]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $h = $this->service->findOrFail($id);
        $this->authorizeAdmin('holiday.manage');
        $this->authorize('delete', $h);
        $this->service->delete($h, $request->user(), (bool) $request->boolean('confirm'));

        return response()->json(['data' => null]);
    }
}
