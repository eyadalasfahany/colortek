<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Enums\SiteReadiness;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SiteVisitTaskHandler
{
    public function __construct(
        private SiteVisitService $siteVisitService,
        private SiteBlockService $siteBlockService,
        private CorrectiveActionService $correctiveActionService,
    ) {}

    /** @param array<string, mixed> $fields @param array<string, mixed> $attachmentIds */
    public function handleBeforeComplete(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        $task->loadMissing(['definition', 'subject', 'project']);
        match ($task->definition?->code) {
            'site_conduct_visit' => $this->siteVisitService->assertSubmittedForTaskCompletion($this->visitFromTask($task)),
            'site_set_readiness' => $this->handleSetReadiness($task, $fields),
            'corrective_action_task' => empty($fields['resolution_note'])
                ? throw TaskNotReadyToComplete::missingField('resolution_note')
                : $this->correctiveActionService->resolveFromTask($task, (string) $fields['resolution_note']),
            default => null,
        };
    }

    /** @param array<string, mixed> $fields */
    public function handleAfterComplete(Task $task, User $user, array $fields): void
    {
        $task->loadMissing(['definition', 'subject']);
        match ($task->definition?->code) {
            'corrective_action_task' => $this->afterCorrectiveAction($task),
            'site_reinspection' => $this->siteVisitService->createReinspectionVisit($this->visitFromTask($task), $user),
            default => null,
        };
    }

    /** @param array<string, mixed> $fields */
    private function handleSetReadiness(Task $task, array $fields): void
    {
        $visit = $this->visitFromTask($task);
        $requested = SiteReadiness::from((string) ($fields['readiness'] ?? SiteReadiness::NotReady->value));
        $readiness = $requested;
        if ($readiness === SiteReadiness::Ready && $this->siteVisitService->hasCriticalFailures($visit)) {
            $readiness = SiteReadiness::NotReady;
        }
        if ($requested === SiteReadiness::NotReady && empty($fields['summary'])) {
            throw TaskNotReadyToComplete::missingField('summary');
        }

        DB::transaction(function () use ($visit, $task, $readiness, $fields): void {
            $visit->update(['readiness' => $readiness, 'general_notes' => $fields['summary'] ?? $visit->general_notes]);
            $project = $visit->project()->firstOrFail();
            if ($readiness === SiteReadiness::Ready) {
                $this->siteBlockService->releaseBlock($project);
            } else {
                $this->siteBlockService->applyBlock($project);
                $this->correctiveActionService->spawnForFailedItems($visit->fresh(['answers.checklistItem']), $task);
            }
        });
    }

    private function afterCorrectiveAction(Task $task): void
    {
        $visit = $this->correctiveActionService->actionFromTask($task)->siteVisit;
        if ($visit !== null && $this->correctiveActionService->allResolved($visit)) {
            $this->correctiveActionService->spawnReinspectionTask($visit, $task);
        }
    }

    private function visitFromTask(Task $task): SiteVisit
    {
        $subject = $task->subject;
        if ($subject instanceof SiteVisit) {
            return $subject->loadMissing(['answers.checklistItem', 'project']);
        }

        throw new TaskNotReadyToComplete(__('Site visit record not found for this task.'), 'site.visit_not_found');
    }
}
