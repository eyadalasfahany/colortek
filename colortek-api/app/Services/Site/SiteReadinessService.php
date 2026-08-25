<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Enums\ActivitySeverity;
use App\Enums\SiteReadiness;
use App\Events\ActivityRecorded;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use Illuminate\Support\Facades\DB;

final class SiteReadinessService
{
    public function __construct(
        private SiteVisitService $siteVisitService,
        private SiteBlockService $siteBlockService,
        private CorrectiveActionService $correctiveActionService,
        private ActivityRecorder $activityRecorder,
    ) {}

    /** @param array{readiness: string, summary?: string} $data */
    public function apply(SiteVisit $visit, User $user, array $data, ?Task $readinessTask = null): SiteVisit
    {
        $requested = SiteReadiness::from($data['readiness']);
        $readiness = $requested;
        $forcedNotReady = $readiness === SiteReadiness::Ready && $this->siteVisitService->hasCriticalFailures($visit);
        if ($forcedNotReady) {
            $readiness = SiteReadiness::NotReady;
        }

        if ($requested === SiteReadiness::NotReady && ! $forcedNotReady && empty($data['summary'])) {
            throw new TaskNotReadyToComplete(
                __('A summary is required when the site is not ready.'),
                'site.readiness_summary_required',
                ['summary' => [__('A summary is required when the site is not ready.')]],
            );
        }

        DB::transaction(function () use ($visit, $readiness, $data, $readinessTask): void {
            $visit->update([
                'readiness' => $readiness,
                'general_notes' => $data['summary'] ?? $visit->general_notes,
            ]);

            $project = $visit->project()->firstOrFail();
            if ($readiness === SiteReadiness::Ready) {
                $this->siteBlockService->releaseBlock($project);
            } else {
                $this->siteBlockService->applyBlock($project);
                if ($readinessTask !== null) {
                    $this->correctiveActionService->spawnForFailedItems($visit->fresh(['answers.checklistItem']), $readinessTask);
                }
            }
        });

        $type = $readiness === SiteReadiness::Ready ? 'site.ready' : 'site.not_ready';
        $severity = $readiness === SiteReadiness::Ready ? ActivitySeverity::Success : ActivitySeverity::Blocker;

        $visit->loadMissing('project');
        $projectReference = $visit->project->reference;

        $event = $this->activityRecorder->record(
            type: $type,
            severity: $severity,
            messageEn: $readiness === SiteReadiness::Ready
                ? __('Site declared ready for :project.', ['project' => $projectReference], 'en')
                : __('Site declared not ready for :project.', ['project' => $projectReference], 'en'),
            messageAr: $readiness === SiteReadiness::Ready
                ? __('Site declared ready for :project.', ['project' => $projectReference], 'ar')
                : __('Site declared not ready for :project.', ['project' => $projectReference], 'ar'),
            actor: $user,
            project: $visit->project,
            subject: $visit,
        );

        event(new ActivityRecorded($event));

        $visit->loadMissing('project');

        return $visit->fresh(['project', 'answers.checklistItem']) ?? $visit;
    }
}
