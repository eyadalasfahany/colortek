<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SiteChecklistItemResource;
use App\Services\Admin\SiteChecklistItemService;
use Illuminate\Http\JsonResponse;

final class SiteChecklistItemController extends Controller
{
    public function __construct(private SiteChecklistItemService $service) {}

    public function index(): JsonResponse
    {
        return SiteChecklistItemResource::collection($this->service->activeOptions())->response();
    }
}
