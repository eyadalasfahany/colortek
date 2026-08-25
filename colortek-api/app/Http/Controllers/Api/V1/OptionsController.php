<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeOptionResource;
use App\Models\BlockerCategory;
use App\Models\Client;
use App\Models\Department;
use App\Models\Project;
use App\Models\User;
use App\Services\Admin\SiteChecklistItemService;
use App\Services\Employees\EmployeeQueryService;
use App\Services\Projects\ProjectVisibility;
use Illuminate\Http\JsonResponse;

final class OptionsController extends Controller
{
    public function __construct(
        private SiteChecklistItemService $checklist,
        private EmployeeQueryService $employees,
        private ProjectVisibility $projectVisibility,
    ) {}

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
        return response()->json([
            'data' => Department::query()->where('active', true)->orderBy('code')->get(['id', 'code', 'name'])
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'code' => $department->code,
                    'name' => $department->getTranslation('name', 'en'),
                ]),
        ]);
    }

    public function users(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }

    public function employees(): JsonResponse
    {
        return response()->json([
            'data' => EmployeeOptionResource::collection($this->employees->optionsForUser(request()->user())),
        ]);
    }

    public function clients(): JsonResponse
    {
        return response()->json([
            'data' => Client::query()->orderBy('name')->get(['id', 'name'])
                ->map(fn (Client $client) => ['id' => $client->id, 'name' => $client->name]),
        ]);
    }

    public function projects(): JsonResponse
    {
        $user = request()->user();
        $query = Project::query()->orderBy('reference');
        if ($user !== null) {
            $this->projectVisibility->applyToProjects($query, $user);
        }

        return response()->json([
            'data' => $query->get(['id', 'reference', 'name'])
                ->map(fn (Project $project) => ['id' => $project->id, 'reference' => $project->reference, 'name' => $project->name]),
        ]);
    }

    public function blockerCategories(): JsonResponse
    {
        return response()->json([
            'data' => BlockerCategory::query()->where('active', true)->orderBy('code')->get()
                ->map(fn (BlockerCategory $category) => [
                    'id' => $category->id,
                    'code' => $category->code,
                    'label' => $category->getTranslation('name', app()->getLocale()),
                ]),
        ]);
    }
}
