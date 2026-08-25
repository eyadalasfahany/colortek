<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ProjectFilter
{
    public function __construct(private ProjectVisibility $visibility) {}

    /** @param Builder<Project> $query @return Builder<Project> */
    public function apply(Request $request, Builder $query, User $user): Builder
    {
        $this->visibility->applyToProjects($query, $user);
        if ($request->filled('stage')) {
            $query->where('stage', $request->string('stage')->toString());
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->boolean('blocked')) {
            $query->whereHas('tasks', fn ($taskQuery) => $taskQuery->where('status', 'blocked'));
        }
        if ($request->boolean('overdue')) {
            $query->whereHas('tasks', fn ($taskQuery) => $taskQuery->where('is_overdue', true));
        }
        $search = $request->string('q')->toString();
        if ($search !== '') {
            $query->where(fn ($builder) => $builder->where('reference', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }

        return $query->orderByDesc('updated_at');
    }
}
