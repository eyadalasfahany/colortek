<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Enums\CorrectiveActionStatus;
use App\Enums\ResponsibleParty;
use App\Enums\TaskStatus;
use App\Models\CorrectiveAction;
use App\Models\Department;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\WorkflowTaskDefinition;
use Illuminate\Support\Facades\DB;

final class CorrectiveActionService
{
    public function spawnForFailedItems(SiteVisit $visit, Task $readinessTask): void
    {
        $visit->loadMissing(['answers.checklistItem']);
        $definition = WorkflowTaskDefinition::query()
            ->where('code', 'corrective_action_task')
            ->whereHas('template', fn ($q) => $q->where('code', 'site_visit'))
            ->firstOrFail();

        foreach ($visit->answers as $answer) {
            $item = $answer->checklistItem;
            if ($item === null || ! $item->is_readiness_critical || $answer->passed !== false) {
                continue;
            }

            $action = CorrectiveAction::create([
                'site_visit_id' => $visit->id,
                'checklist_item_id' => $item->id,
                'description' => $item->localizedLabel(),
                'responsible_party' => ResponsibleParty::Client,
                'status' => CorrectiveActionStatus::Open,
            ]);

            $department = Department::query()->where('code', 'sales')->firstOrFail();
            $task = Task::create([
                'reference' => sprintf('%s-CA%d', $visit->reference, $action->id),
                'instance_id' => null,
                'task_definition_id' => $definition->id,
                'project_id' => $visit->project_id,
                'subject_type' => $action->getMorphClass(),
                'subject_id' => $action->id,
                'title' => $definition->title_en,
                'instructions' => $definition->instructions_en,
                'department_id' => $department->id,
                'status' => TaskStatus::Ready,
                'priority' => $definition->priority,
                'due_at' => $readinessTask->due_at,
                'ready_at' => now(),
            ]);

            $action->update(['task_id' => $task->id]);
        }
    }

    /**
     * Raises a corrective action by hand, for something the checklist did not
     * cover. `responsible_party` decides the queue —
     * `07-workflows/05-site-visit-and-readiness.md` §: client, contractor and
     * other_trade all go to Sales, because Sales is who talks to the client;
     * only `colortek` routes to an internal department.
     *
     * @param  array<string, mixed>  $data
     */
    public function createForVisit(SiteVisit $visit, array $data): CorrectiveAction
    {
        $party = $data['responsible_party'] instanceof ResponsibleParty
            ? $data['responsible_party']
            : ResponsibleParty::from((string) $data['responsible_party']);

        return DB::transaction(function () use ($visit, $data, $party): CorrectiveAction {
            $action = CorrectiveAction::create([
                'site_visit_id' => $visit->id,
                'checklist_item_id' => $data['checklist_item_id'] ?? null,
                'description' => $data['description'],
                'responsible_party' => $party,
                'status' => CorrectiveActionStatus::Open,
            ]);

            $definition = WorkflowTaskDefinition::query()
                ->where('code', 'corrective_action_task')
                ->whereHas('template', fn ($q) => $q->where('code', 'site_visit'))
                ->firstOrFail();

            $department = $this->departmentFor($party, $data['department_id'] ?? null);

            $task = Task::create([
                'reference' => sprintf('%s-CA%d', $visit->reference, $action->id),
                'task_definition_id' => $definition->id,
                'project_id' => $visit->project_id,
                'subject_type' => $action->getMorphClass(),
                'subject_id' => $action->id,
                'title' => $definition->title_en,
                'instructions' => $data['description'],
                'department_id' => $department->id,
                'status' => TaskStatus::Ready,
                'priority' => $definition->priority,
                'ready_at' => now(),
            ]);

            $action->update(['task_id' => $task->id]);

            return $action->fresh(['checklistItem', 'siteVisit', 'task']);
        });
    }

    private function departmentFor(ResponsibleParty $party, ?int $departmentId): Department
    {
        if ($party === ResponsibleParty::Colortek && $departmentId !== null) {
            return Department::query()->findOrFail($departmentId);
        }

        // Everything the client or another trade owns is chased by Sales, which
        // is what keeps the delay attributed to the right party in reports.
        return Department::query()->where('code', 'sales')->firstOrFail();
    }

    public function resolveFromTask(Task $task, string $resolutionNote): CorrectiveAction
    {
        $action = $this->actionFromTask($task);
        $action->update([
            'status' => CorrectiveActionStatus::Resolved,
            'resolution_note' => $resolutionNote,
            'resolved_at' => now(),
        ]);

        return $action->fresh(['siteVisit']);
    }

    public function allResolved(SiteVisit $visit): bool
    {
        return ! CorrectiveAction::query()
            ->where('site_visit_id', $visit->id)
            ->where('status', CorrectiveActionStatus::Open)
            ->exists();
    }

    public function spawnReinspectionTask(SiteVisit $visit, Task $sourceTask): Task
    {
        $definition = WorkflowTaskDefinition::query()
            ->where('code', 'site_reinspection')
            ->whereHas('template', fn ($q) => $q->where('code', 'site_visit'))
            ->firstOrFail();

        return Task::create([
            'reference' => sprintf('%s-RI%d', $visit->reference, $sourceTask->id),
            'instance_id' => $sourceTask->instance_id,
            'task_definition_id' => $definition->id,
            'project_id' => $visit->project_id,
            'subject_type' => $visit->getMorphClass(),
            'subject_id' => $visit->id,
            'title' => $definition->title_en,
            'instructions' => $definition->instructions_en,
            'department_id' => $definition->department_id,
            'status' => TaskStatus::Ready,
            'priority' => $definition->priority,
            'due_at' => $sourceTask->due_at,
            'ready_at' => now(),
        ]);
    }

    public function actionFromTask(Task $task): CorrectiveAction
    {
        $subject = $task->subject;
        if ($subject instanceof CorrectiveAction) {
            return $subject;
        }

        throw new \RuntimeException(__('Corrective action not found for this task.'));
    }
}
