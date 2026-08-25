<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Events\TaskCreated;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTaskDefinition;
use App\Services\Tasks\DeadlineCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class TaskFactory
{
    public function __construct(private DeadlineCalculator $deadlineCalculator) {}

    public function createForDefinition(
        WorkflowInstance $instance,
        WorkflowTaskDefinition $definition,
        TaskStatus $status,
    ): Task {
        $instance->loadMissing('project');

        $status = $this->applySiteHold($definition, $instance->project, $status);

        $task = Task::create([
            'reference' => $this->generateReference($instance, $definition),
            'instance_id' => $instance->id,
            'task_definition_id' => $definition->id,
            'project_id' => $instance->project_id,
            'subject_type' => $instance->subject_type,
            'subject_id' => $instance->subject_id,
            'title' => $definition->title_en,
            'instructions' => $definition->instructions_en,
            'department_id' => $definition->department_id,
            'status' => $status,
            'priority' => $definition->priority ?? TaskPriority::Normal,
            'due_at' => $this->deadlineCalculator->for(
                $definition,
                $instance->project,
                CarbonImmutable::now(),
            ),
            'ready_at' => $status === TaskStatus::Ready ? now() : null,
        ]);

        event(new TaskCreated($task));

        return $task;
    }

    public function shouldHoldForSite(WorkflowTaskDefinition $definition, ?Project $project): bool
    {
        if ($project === null || $project->site_ready) {
            return false;
        }

        if ($this->deadlineCalculator->companyBlocksAllWhenSiteNotReady()
            || $project->block_all_when_site_not_ready) {
            return true;
        }

        return $definition->blocks_when_site_not_ready;
    }

    private function applySiteHold(
        WorkflowTaskDefinition $definition,
        ?Project $project,
        TaskStatus $status,
    ): TaskStatus {
        if ($status !== TaskStatus::Ready || ! $this->shouldHoldForSite($definition, $project)) {
            return $status;
        }

        return TaskStatus::Pending;
    }

    private function generateReference(WorkflowInstance $instance, WorkflowTaskDefinition $definition): string
    {
        $count = Task::query()
            ->where('instance_id', $instance->id)
            ->count() + 1;

        return sprintf('WF%d-%s-%03d', $instance->id, Str::upper($definition->code), $count);
    }
}
