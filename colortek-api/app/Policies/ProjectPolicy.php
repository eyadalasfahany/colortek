<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Services\Projects\ProjectVisibility;

final class ProjectPolicy
{
    public function __construct(private ProjectVisibility $v) {}

    public function viewAny(User $u): bool
    {
        return $u->can('project.view');
    }

    public function view(User $u, Project $p): bool
    {
        return $this->v->canView($u, $p);
    }

    public function create(User $u): bool
    {
        return $u->can('project.create');
    }

    public function update(User $u, Project $p): bool
    {
        return $u->can('project.update') && $this->v->canView($u, $p);
    }

    public function complete(User $u, Project $p): bool
    {
        return $u->can('project.complete') && $this->v->canView($u, $p);
    }

    public function cancel(User $u, Project $p): bool
    {
        return $u->can('project.cancel') && $this->v->canView($u, $p);
    }
}
