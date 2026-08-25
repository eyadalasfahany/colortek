<?php

declare(strict_types=1);

namespace App\Http\Filters;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class TaskFilter
{
    /** @param Builder<Task> $query */
    public function apply(Request $request, Builder $query, User $user): Builder
    {
        $this->applyVisibility($query, $user);

        if ($scope = $request->string('scope')->toString()) {
            $this->applyScope($query, $user, $scope);
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->has('status')) {
            $statuses = (array) $request->input('status');
            $query->whereIn('status', $statuses);
        }

        if ($request->boolean('overdue')) {
            $query->where('is_overdue', true);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->string('priority')->toString());
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('reference', 'like', "%{$search}%");
            });
        }

        if ($request->filled('due_before')) {
            $query->where('due_at', '<=', $request->date('due_before'));
        }

        $sort = $request->string('sort', 'due_at')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (in_array($column, ['due_at', 'priority', 'created_at', 'reference'], true)) {
            $query->orderBy($column, $direction);
        }

        return $query;
    }

    /** @param Builder<Task> $query */
    private function applyScope(Builder $query, User $user, string $scope): void
    {
        match ($scope) {
            'my' => $query->where('claimed_by_user_id', $user->id),
            'queue' => $query
                ->where('status', TaskStatus::Ready->value)
                ->whereIn('department_id', $user->departments()->pluck('departments.id')),
            'all' => $user->can('task.view_all') ? null : $query->whereRaw('1 = 0'),
            default => null,
        };
    }

    /** @param Builder<Task> $query */
    private function applyVisibility(Builder $query, User $user): void
    {
        if ($user->can('project.view_all') || $user->can('task.view_all')) {
            return;
        }

        $departmentIds = $user->departments()->pluck('departments.id');

        $query->where(function (Builder $builder) use ($user, $departmentIds): void {
            $builder->whereNull('project_id')
                ->orWhereHas('project', function (Builder $projectQuery) use ($user, $departmentIds): void {
                    $projectQuery
                        ->where('sales_user_id', $user->id)
                        ->orWhereHas('tasks', function (Builder $taskQuery) use ($user): void {
                            $taskQuery->where('claimed_by_user_id', $user->id);
                        })
                        ->orWhereHas('tasks', function (Builder $taskQuery) use ($departmentIds): void {
                            $taskQuery
                                ->whereIn('department_id', $departmentIds)
                                ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value]);
                        });
                });
        });
    }
}
