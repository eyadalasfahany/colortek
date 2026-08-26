<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Http\Resources\Admin\SettingResource;
use App\Models\Setting;
use App\Services\Admin\SettingService;
use Illuminate\Http\JsonResponse;

final class AdminSettingController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private SettingService $service) {}

    public function index(): JsonResponse
    {
        $this->authorizeAdmin('settings.manage');
        $this->authorize('viewAny', Setting::class);

        return SettingResource::collection($this->service->all())->response();
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->authorizeAdmin('settings.manage');
        $this->authorize('update', Setting::class);
        $r = $this->service->update($request->validated(), $request->user(), (bool) $request->boolean('confirm'));

        return response()->json(['data' => SettingResource::collection($r['settings']), 'meta' => ['affected_task_count' => $r['affected_task_count']]]);
    }
}
