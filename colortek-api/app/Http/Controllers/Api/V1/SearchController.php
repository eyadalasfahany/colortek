<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Project;
use App\Models\Task;
use App\Services\Projects\ProjectVisibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SearchController extends Controller
{
    public function __construct(private ProjectVisibility $visibility) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->string('q')->toString());

        if ($query === '') {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $projectQuery = Project::query()->where(
            fn ($builder) => $builder
                ->where('reference', 'like', "%{$query}%")
                ->orWhere('name', 'like', "%{$query}%"),
        );
        $this->visibility->applyToProjects($projectQuery, $user);
        $projectIds = (clone $projectQuery)->pluck('id');

        return response()->json([
            'data' => [
                'projects' => $projectQuery->limit(10)->get()->map(fn (Project $project) => [
                    'type' => 'project',
                    'id' => $project->id,
                    'label' => $project->reference.' — '.$project->name,
                    'reference' => $project->reference,
                ]),
                'tasks' => Task::query()
                    ->whereIn('project_id', $projectIds)
                    ->where('reference', 'like', "%{$query}%")
                    ->with('project')
                    ->limit(10)
                    ->get()
                    ->map(fn (Task $task) => [
                        'type' => 'task',
                        'id' => $task->id,
                        'label' => $task->reference.' — '.$task->localizedTitle(),
                        'project_reference' => $task->project?->reference,
                    ]),
                'clients' => Client::query()
                    ->where('name', 'like', "%{$query}%")
                    ->limit(5)
                    ->get()
                    ->map(fn (Client $client) => [
                        'type' => 'client',
                        'id' => $client->id,
                        'label' => $client->name,
                    ]),
                'samples' => [],
                'site_visits' => [],
            ],
        ]);
    }
}
