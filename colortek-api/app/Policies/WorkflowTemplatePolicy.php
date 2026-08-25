<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkflowTemplate;

final class WorkflowTemplatePolicy
{
    public function viewAny(User $u): bool
    {
        return $u->can('workflow.view');
    }

    public function view(User $u, WorkflowTemplate $t): bool
    {
        return $u->can('workflow.view');
    }

    public function update(User $u, WorkflowTemplate $t): bool
    {
        return $u->can('workflow.manage') && $t->published_at === null;
    }

    public function publish(User $u, WorkflowTemplate $t): bool
    {
        return $u->can('workflow.manage') && $t->published_at === null;
    }

    public function createDraft(User $u, WorkflowTemplate $t): bool
    {
        return $u->can('workflow.manage') && $t->published_at !== null;
    }
}
