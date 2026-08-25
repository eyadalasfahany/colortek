<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\Admin\SiteChecklistItemService;
use Illuminate\Http\JsonResponse;

final class OptionsController extends Controller
{
    public function __construct(private SiteChecklistItemService $checklist) {}

    public function checklistItems(): JsonResponse
    {
        $items = $this->checklist->activeOptions()->map(fn ($item) => [
            'id' => $item->id,
            'code' => $item->code,
            'label' => $item->localizedLabel(),
        ]);

        return response()->json(['data' => $items]);
    }

    public function departments(): JsonResponse
    {
        $departments = Department::query()
            ->where('active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->getTranslation('name', 'en'),
            ]);

        return response()->json(['data' => $departments]);
    }

    public function users(): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->where('active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }
}
