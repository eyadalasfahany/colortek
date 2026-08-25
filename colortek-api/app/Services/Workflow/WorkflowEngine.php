<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Enums\TaskStatus;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Sample;
use App\Models\Task;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use App\Models\WorkflowTransitionLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use RuntimeException;

final class WorkflowEngine
{
    public function __construct(
        private ConditionEvaluator $evaluator,
        private TaskFactory $taskFactory,
    ) {}

    public function start(WorkflowTemplate $template, Model $subject): WorkflowInstance
    {
        if ($template->published_at === null) {
            throw new RuntimeException('Cannot instantiate a draft workflow template.');
        }

        $project = $this->resolveProject($subject);

        $instance = WorkflowInstance::create([
            'template_id' => $template->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'project_id' => $project?->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $entryPoints = $template->definitions()->where('is_entry_point', true)->get();
        foreach ($entryPoints as $definition) {
            $this->taskFactory->createForDefinition($instance, $definition, TaskStatus::Ready);
        }

        return $instance->load('tasks');
    }

    public function startAtDefinition(WorkflowTemplate $template, Model $subject, string $definitionCode): WorkflowInstance
    {
        if ($template->published_at === null) {
            throw new RuntimeException('Cannot instantiate a draft workflow template.');
        }

        $definition = $template->definitions()->where('code', $definitionCode)->first();
        if ($definition === null) {
            throw new RuntimeException(sprintf('Workflow definition [%s] not found.', $definitionCode));
        }

        $project = $this->resolveProject($subject);

        $instance = WorkflowInstance::create([
            'template_id' => $template->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'project_id' => $project?->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        $this->taskFactory->createForDefinition($instance, $definition, TaskStatus::Ready);

        return $instance->load('tasks');
    }

    /** @return Collection<int, Task> */
    public function advance(Task $completedTask): Collection
    {
        $completedTask->loadMissing(['definition', 'instance.project', 'instance.template']);

        $instance = $completedTask->instance;
        if ($instance === null || $completedTask->task_definition_id === null) {
            return collect();
        }

        $transitions = WorkflowTransition::query()
            ->where('template_id', $instance->template_id)
            ->where('from_task_definition_id', $completedTask->task_definition_id)
            ->orderBy('sort_order')
            ->get();

        $created = collect();

        foreach ($transitions as $transition) {
            $passes = $this->evaluator->passes($transition->condition, $completedTask);
            $this->logEvaluation(
                $instance,
                $transition,
                $completedTask,
                $passes,
                $passes ? null : 'condition not met',
            );

            if (! $passes) {
                continue;
            }

            $target = $transition->toDefinition;
            if ($target === null) {
                continue;
            }

            if (in_array($target->code, [
                'reception_daily_journal',
                'accounting_process_journal',
                'reception_fix_journal',
            ], true)) {
                continue;
            }

            $task = $this->createOrPromoteTarget($instance, $transition, $target, $completedTask);
            if ($task !== null) {
                $created->push($task);
            }
        }

        $this->maybeCompleteInstance($instance);

        return $created;
    }

    public function releaseSiteHeldTasks(Project $project): void
    {
        if (! $project->site_ready) {
            return;
        }

        Task::query()
            ->where('project_id', $project->id)
            ->where('status', TaskStatus::Pending)
            ->update([
                'status' => TaskStatus::Ready,
                'ready_at' => now(),
            ]);
    }

    private function createOrPromoteTarget(
        WorkflowInstance $instance,
        WorkflowTransition $transition,
        WorkflowTaskDefinition $target,
        Task $completedTask,
    ): ?Task {
        $existing = Task::query()
            ->where('instance_id', $instance->id)
            ->where('task_definition_id', $target->id)
            ->whereIn('status', [
                TaskStatus::Waiting,
                TaskStatus::Ready,
                TaskStatus::Pending,
                TaskStatus::Claimed,
                TaskStatus::InProgress,
                TaskStatus::Paused,
                TaskStatus::Blocked,
            ])
            ->first();

        if ($transition->join_mode === 'any') {
            if ($existing !== null) {
                return null;
            }

            return $this->taskFactory->createForDefinition($instance, $target, TaskStatus::Ready);
        }

        if (! $this->allPredecessorsCompleted($instance, $target)) {
            if ($existing !== null) {
                return null;
            }

            return $this->taskFactory->createForDefinition($instance, $target, TaskStatus::Waiting);
        }

        if ($existing !== null && $existing->status === TaskStatus::Waiting) {
            $existing->update([
                'status' => TaskStatus::Ready,
                'ready_at' => now(),
            ]);

            return $existing->fresh();
        }

        if ($existing !== null) {
            return null;
        }

        return $this->taskFactory->createForDefinition($instance, $target, TaskStatus::Ready);
    }

    private function allPredecessorsCompleted(WorkflowInstance $instance, WorkflowTaskDefinition $target): bool
    {
        $predecessorDefinitionIds = WorkflowTransition::query()
            ->where('template_id', $instance->template_id)
            ->where('to_task_definition_id', $target->id)
            ->pluck('from_task_definition_id')
            ->filter()
            ->unique()
            ->values();

        if ($predecessorDefinitionIds->isEmpty()) {
            return true;
        }

        foreach ($predecessorDefinitionIds as $definitionId) {
            $completed = Task::query()
                ->where('instance_id', $instance->id)
                ->where('task_definition_id', $definitionId)
                ->where('status', TaskStatus::Completed)
                ->exists();

            if (! $completed) {
                return false;
            }
        }

        return true;
    }

    private function maybeCompleteInstance(WorkflowInstance $instance): void
    {
        $hasOpenTasks = Task::query()
            ->where('instance_id', $instance->id)
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            ->exists();

        if (! $hasOpenTasks) {
            $instance->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }
    }

    private function resolveProject(Model $subject): ?Project
    {
        if ($subject instanceof Project) {
            return $subject;
        }

        if ($subject instanceof Payment) {
            $subject->loadMissing('project');

            return $subject->project;
        }

        if ($subject instanceof Sample) {
            $subject->loadMissing('project');

            return $subject->project;
        }

        if ($subject->relationLoaded('project')) {
            $relatedProject = $subject->getRelation('project');
            if ($relatedProject instanceof Project) {
                return $relatedProject;
            }
        }

        return null;
    }

    private function logEvaluation(
        WorkflowInstance $instance,
        WorkflowTransition $transition,
        Task $sourceTask,
        bool $taken,
        ?string $reason,
    ): void {
        WorkflowTransitionLog::create([
            'instance_id' => $instance->id,
            'transition_id' => $transition->id,
            'source_task_id' => $sourceTask->id,
            'taken' => $taken,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
