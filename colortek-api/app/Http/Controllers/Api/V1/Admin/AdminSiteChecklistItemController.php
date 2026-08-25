<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdminAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SiteChecklistItemRequest;
use App\Http\Resources\Admin\SiteChecklistItemResource;
use App\Models\SiteChecklistItem;
use App\Services\Admin\SiteChecklistItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminSiteChecklistItemController extends Controller
{
    use AuthorizesAdminAccess;

    public function __construct(private SiteChecklistItemService $service) {}

    public function index(Request $r): JsonResponse
    {
        $this->authorizeAdmin('settings.manage');
        $this->authorize('viewAny', SiteChecklistItem::class);

        return SiteChecklistItemResource::collection($this->service->paginate((int) $r->integer('per_page', 15)))->response();
    }

    public function store(SiteChecklistItemRequest $r): JsonResponse
    {
        $this->authorizeAdmin('settings.manage');
        $this->authorize('create', SiteChecklistItem::class);

        return response()->json(['data' => SiteChecklistItemResource::make($this->service->store($r->validated(), $r->user()))], 201);
    }

    public function update(SiteChecklistItemRequest $r, int $id): JsonResponse
    {
        $i = $this->service->findOrFail($id);
        $this->authorizeAdmin('settings.manage');
        $this->authorize('update', $i);

        return response()->json(['data' => SiteChecklistItemResource::make($this->service->update($i, $r->validated(), $r->user()))]);
    }
}
