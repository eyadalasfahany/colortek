<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class ProjectVisibility
{
    public function canView(User $u, Project $p): bool
    {
        if ($u->can('project.view_all') || $p->sales_user_id === $u->id) {
            return true;
        }
        $d = $u->departments()->pluck('departments.id');

        return $p->tasks()->where(fn ($q) => $q->where('claimed_by_user_id', $u->id)->orWhere(fn ($dq) => $dq->whereIn('department_id', $d)->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])))->exists();
    }

    public function applyToProjects(Builder $q, User $u): Builder
    {
        if ($u->can('project.view_all')) {
            return $q;
        }
        $d = $u->departments()->pluck('departments.id');

        return $q->where(fn ($b) => $b->where('sales_user_id', $u->id)->orWhereHas('tasks', fn ($t) => $t->where('claimed_by_user_id', $u->id))->orWhereHas('tasks', fn ($t) => $t->whereIn('department_id', $d)->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])));
    }

    public function applyToActivity(Builder $q, User $u): Builder
    {
        $q->where(fn ($b) => $b->whereNull('project_id')->orWhereHas('project', fn ($p) => $this->applyToProjects($p, $u)));
        $perms = $u->getAllPermissions()->pluck('name');

        return $q->where(fn ($b) => $b->whereNull('visible_to_permission')->orWhereIn('visible_to_permission', $perms));
    }
}
