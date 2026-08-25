<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SiteChecklistItemResource;
use App\Models\SiteChecklistItem;
use Illuminate\Http\JsonResponse;

final class SiteChecklistItemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => SiteChecklistItemResource::collection(SiteChecklistItem::query()->where('active', true)->orderBy('sort_order')->get())]);
    }

    public function options(): JsonResponse
    {
        return $this->index();
    }
}
