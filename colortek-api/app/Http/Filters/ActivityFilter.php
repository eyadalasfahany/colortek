<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Models\ActivityEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class ActivityFilter
{
    /**
     * @param  Builder<ActivityEvent>  $query
     * @return Builder<ActivityEvent>
     */
    public function apply(Request $request, Builder $query, User $user): Builder
    {
        unset($user);

        if ($request->filled('since')) {
            $query->where('id', '>', $request->integer('since'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->string('severity')->toString());
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        return $query->orderByDesc('id');
    }
}
