<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Enums\ProjectStage;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;

final class ProjectWorkflowService
{
    public function workflow(Project $p): array
    {
        $p->loadMissing(['tasks.department', 'tasks.claimant', 'tasks.definition']);
        $order = [ProjectStage::Lead, ProjectStage::Quotation, ProjectStage::Payment, ProjectStage::Sample, ProjectStage::Site, ProjectStage::Production, ProjectStage::Execution, ProjectStage::Delivery, ProjectStage::Completed];
        $fi = array_search($p->stage, $order, true);
        $open = $p->tasks->filter(fn (Task $t) => $t->status->isOpen());
        $stages = [];
        foreach ($order as $stage) {
            $i = array_search($stage, $order, true);
            $stages[] = ['key' => $stage->value, 'label' => $stage->label(), 'completed' => $i !== false && $fi !== false && $i < $fi, 'active' => $stage === $p->stage, 'blocked' => $stage === ProjectStage::Site && ! $p->site_ready, 'configured' => $stage !== ProjectStage::Delivery];
        }
        $task = $open->sortBy([fn (Task $t) => $t->status === TaskStatus::Blocked ? 0 : 1, 'due_at'])->first();
        $next = $task ? ['task_id' => $task->id, 'title' => $task->localizedTitle(), 'department' => $task->department->getTranslation('name', app()->getLocale()), 'holder' => $task->claimant?->name ?? __('Unclaimed'), 'status' => $task->status->value, 'is_overdue' => $task->is_overdue] : null;

        return ['stages' => $stages, 'next_action' => $next, 'current_stage' => $p->stage->value];
    }
}
