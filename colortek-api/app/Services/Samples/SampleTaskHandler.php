<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Enums\ActivitySeverity;
use App\Enums\FormulaStatus;
use App\Enums\SampleApprovalDecision;
use App\Enums\SampleApprovalType;
use App\Enums\SampleStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Attachment;
use App\Models\Formula;
use App\Models\Sample;
use App\Models\SampleApproval;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Activity\ActivityRecorder;
use App\Services\Workflow\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SampleTaskHandler
{
    public function __construct(
        private FormulaService $formulaService,
        private SampleReferenceGenerator $referenceGenerator,
        private TimerService $timerService,
        private WorkflowEngine $workflowEngine,
        private ActivityRecorder $activityRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    public function handleBeforeComplete(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        $task->loadMissing(['definition', 'subject', 'project']);

        $code = $task->definition?->code;
        if ($code === null) {
            return;
        }

        match ($code) {
            'sales_create_sample_request' => $this->handleSalesCreate($task, $user, $fields),
            'reception_review_sample_request' => $this->handleReceptionReview($task, $fields),
            'manager_approve_sample' => $this->handleManagerApprove($task, $user, $fields),
            'workshop_make_sample' => $this->handleWorkshopMake($task, $user, $fields, $attachmentIds),
            'tinting_author_formula' => $this->handleTintingAuthor($task, $user, $fields, $attachmentIds),
            'reception_register_formula' => $this->handleReceptionRegister($task, $user, $fields),
            'sales_get_client_decision' => $this->handleClientDecision($task, $user, $fields, $attachmentIds),
            'sales_create_modification_request' => $this->handleModificationRequest($task, $user, $fields, $attachmentIds),
            default => null,
        };
    }

    /** @param array<string, mixed> $fields */
    public function handleAfterComplete(Task $task, User $user, array $fields): void
    {
        $task->loadMissing(['definition', 'subject']);

        match ($task->definition?->code) {
            'reception_review_sample_request' => $this->maybeRaiseManagerPriority($task),
            default => null,
        };
    }

    /** @param array<string, mixed> $data */
    public function createModificationChild(Sample $parent, User $user, array $data): Sample
    {
        if ($parent->status !== SampleStatus::RejectedByClient) {
            throw new TaskNotReadyToComplete(
                __('Modifications are only allowed after client rejection.'),
                'sample.modification_not_allowed',
            );
        }

        return DB::transaction(function () use ($parent, $user, $data): Sample {
            $this->formulaService->supersedeForSample($parent);
            $parent->update(['status' => SampleStatus::Superseded]);

            $child = Sample::query()->create([
                'reference' => $this->referenceGenerator->nextSampleReference($parent),
                'client_id' => $parent->client_id,
                'project_id' => $parent->project_id,
                'parent_sample_id' => $parent->id,
                'root_sample_id' => $parent->root_sample_id ?? $parent->id,
                'attempt_number' => $parent->attempt_number + 1,
                'requested_by_user_id' => $user->id,
                'requested_at' => now(),
                'needed_by' => $data['needed_by'] ?? $parent->needed_by,
                'color' => $data['color'] ?? $parent->color,
                'texture' => $data['texture'] ?? $parent->texture,
                'client_reference' => $data['client_reference'] ?? $parent->client_reference,
                'size' => $data['size'] ?? $parent->size,
                'finish_requirement' => $data['finish_requirement'] ?? $parent->finish_requirement,
                'modification_reason' => $data['modification_reason'],
                'status' => SampleStatus::PendingManagerApproval,
                'is_presale' => $parent->is_presale,
            ]);

            $template = WorkflowTemplate::query()
                ->where('code', 'sample_request')
                ->where('is_active', true)
                ->whereNotNull('published_at')
                ->firstOrFail();

            $this->workflowEngine->startAtDefinition(
                $template,
                $child->fresh(['project', 'client', 'parentSample']),
                'reception_review_sample_request',
            );

            $this->activityRecorder->record(
                type: 'sample.modification_requested',
                severity: ActivitySeverity::Warning,
                messageEn: sprintf('Modification requested for sample %s (attempt %d).', $parent->reference, $child->attempt_number),
                messageAr: sprintf('طلب تعديل للعينة %s (المحاولة %d).', $parent->reference, $child->attempt_number),
                actor: $user,
                project: $parent->project,
                subject: $child,
            );

            return $child->fresh(['client', 'project', 'parentSample']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attachmentIds
     */
    public function recordClientDecision(Sample $sample, array $data, User $user, array $attachmentIds): Sample
    {
        $formIds = $this->resolveAttachmentIdsForType($attachmentIds, 'client_approval_form');
        if ($formIds === []) {
            throw TaskNotReadyToComplete::missingAttachment('client_approval_form');
        }

        $decision = (string) ($data['decision'] ?? '');
        if ($decision === 'rejected' && empty($data['comments'])) {
            throw TaskNotReadyToComplete::missingField('comments');
        }

        return DB::transaction(function () use ($sample, $data, $user, $formIds, $decision): Sample {
            $this->linkAttachments($formIds, $sample);

            SampleApproval::query()->create([
                'sample_id' => $sample->id,
                'type' => SampleApprovalType::Client,
                'decision' => SampleApprovalDecision::from($decision),
                'decided_by_user_id' => $user->id,
                'client_signatory_name' => $data['client_signatory_name'] ?? null,
                'decided_at' => CarbonImmutable::parse((string) $data['decided_at']),
                'recorded_by_user_id' => $user->id,
                'comments' => $data['comments'] ?? null,
            ]);

            if ($decision === 'approved') {
                $sample->update(['status' => SampleStatus::Approved]);
                $this->formulaService->approveForSample($sample);
            } else {
                $sample->update(['status' => SampleStatus::RejectedByClient]);
            }

            return $sample->fresh(['client', 'project', 'approvals', 'formulas', 'approvedFormula']);
        });
    }

    /** @param array<string, mixed> $fields */
    private function handleSalesCreate(Task $task, User $user, array $fields): void
    {
        $sample = $this->sampleFromTask($task);
        $sample->update([
            'color' => $fields['color'] ?? $sample->color,
            'texture' => $fields['texture'] ?? $sample->texture,
            'client_reference' => $fields['client_reference'] ?? $sample->client_reference,
            'size' => $fields['size'] ?? $sample->size,
            'finish_requirement' => $fields['finish_requirement'] ?? $sample->finish_requirement,
            'needed_by' => $fields['needed_by'] ?? $sample->needed_by,
            'notes' => $fields['notes'] ?? $sample->notes,
            'status' => SampleStatus::PendingManagerApproval,
        ]);
    }

    /** @param array<string, mixed> $fields */
    private function handleReceptionReview(Task $task, array $fields): void
    {
        if (($fields['review_result'] ?? '') === 'return_to_sales' && empty($fields['note'])) {
            throw TaskNotReadyToComplete::missingField('note');
        }
    }

    /** @param array<string, mixed> $fields */
    private function handleManagerApprove(Task $task, User $user, array $fields): void
    {
        $sample = $this->sampleFromTask($task);
        $decision = (string) ($fields['decision'] ?? '');

        if ($decision === 'rejected' && empty($fields['comments'])) {
            throw TaskNotReadyToComplete::missingField('comments');
        }

        DB::transaction(function () use ($sample, $user, $fields, $decision): void {
            SampleApproval::query()->create([
                'sample_id' => $sample->id,
                'type' => SampleApprovalType::Manager,
                'decision' => SampleApprovalDecision::from($decision),
                'decided_by_user_id' => $user->id,
                'recorded_by_user_id' => $user->id,
                'comments' => $fields['comments'] ?? null,
                'decided_at' => now(),
            ]);

            $sample->update([
                'status' => $decision === 'approved'
                    ? SampleStatus::InWorkshop
                    : SampleStatus::RejectedByManager,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    private function handleWorkshopMake(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        $sample = $this->sampleFromTask($task);

        $hasFormula = Formula::query()
            ->where('sample_id', $sample->id)
            ->whereIn('status', [FormulaStatus::Draft, FormulaStatus::Registered, FormulaStatus::Approved])
            ->exists();

        if (! $hasFormula) {
            throw new TaskNotReadyToComplete(
                __('The formula must be authored before the workshop can complete this task.'),
                'sample.formula_missing',
            );
        }

        $photoIds = $this->resolveAttachmentIdsForType($attachmentIds, 'sample_photo');
        if ($photoIds === []) {
            throw TaskNotReadyToComplete::missingAttachment('sample_photo');
        }

        $this->timerService->stopForTask($task);
        $this->linkAttachments($photoIds, $sample);

        $sample->update(['status' => SampleStatus::AwaitingFormulaRegistration]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    private function handleTintingAuthor(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        $sample = $this->sampleFromTask($task);
        $this->formulaService->author($sample, $fields, $attachmentIds, $user);
    }

    /** @param array<string, mixed> $fields */
    private function handleReceptionRegister(Task $task, User $user, array $fields): void
    {
        $sample = $this->sampleFromTask($task);

        $formula = Formula::query()
            ->where('sample_id', $sample->id)
            ->where('status', FormulaStatus::Draft)
            ->orderByDesc('version')
            ->first();

        if ($formula === null) {
            throw new TaskNotReadyToComplete(
                __('No draft formula found to register.'),
                'formula.not_found',
            );
        }

        $this->formulaService->register($formula, $fields, $user);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    private function handleClientDecision(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        $sample = $this->sampleFromTask($task);
        $formIds = $this->resolveAttachmentIdsForType($attachmentIds, 'client_approval_form');

        if ($formIds === []) {
            throw TaskNotReadyToComplete::missingAttachment('client_approval_form');
        }

        if (($fields['decision'] ?? '') === 'rejected' && empty($fields['comments'])) {
            throw TaskNotReadyToComplete::missingField('comments');
        }

        DB::transaction(function () use ($sample, $user, $fields, $formIds): void {
            $this->linkAttachments($formIds, $sample);

            SampleApproval::query()->create([
                'sample_id' => $sample->id,
                'type' => SampleApprovalType::Client,
                'decision' => SampleApprovalDecision::from((string) $fields['decision']),
                'decided_by_user_id' => $user->id,
                'client_signatory_name' => $fields['client_signatory_name'] ?? null,
                'decided_at' => CarbonImmutable::parse((string) $fields['decided_at']),
                'recorded_by_user_id' => $user->id,
                'comments' => $fields['comments'] ?? null,
            ]);

            if (($fields['decision'] ?? '') === 'approved') {
                $sample->update(['status' => SampleStatus::Approved]);
                $this->formulaService->approveForSample($sample);
            } else {
                $sample->update(['status' => SampleStatus::RejectedByClient]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    private function handleModificationRequest(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        $parent = $this->sampleFromTask($task);

        if (empty($fields['modification_reason'])) {
            throw TaskNotReadyToComplete::missingField('modification_reason');
        }

        $this->createModificationChild($parent, $user, $fields);

        $photoIds = $this->resolveAttachmentIdsForType($attachmentIds, 'sample_photo');
        if ($photoIds !== []) {
            $child = Sample::query()->where('parent_sample_id', $parent->id)->orderByDesc('id')->first();
            if ($child !== null) {
                $this->linkAttachments($photoIds, $child);
            }
        }
    }

    private function maybeRaiseManagerPriority(Task $task): void
    {
        $sample = $this->sampleFromTask($task);
        $threshold = (int) (Setting::query()->where('key', 'sample_repeat_attempt_threshold')->value('value') ?? 4);

        if ($sample->attempt_number < $threshold) {
            return;
        }

        Task::query()
            ->where('subject_type', $sample->getMorphClass())
            ->where('subject_id', $sample->id)
            ->whereHas('definition', fn ($q) => $q->where('code', 'manager_approve_sample'))
            ->whereIn('status', [TaskStatus::Ready, TaskStatus::Waiting, TaskStatus::Claimed, TaskStatus::InProgress])
            ->update(['priority' => TaskPriority::High]);
    }

    private function sampleFromTask(Task $task): Sample
    {
        $subject = $task->subject;
        if ($subject instanceof Sample) {
            return $subject->loadMissing(['project', 'client', 'parentSample']);
        }

        throw new TaskNotReadyToComplete(
            __('Sample record not found for this task.'),
            'sample.not_found',
        );
    }

    /** @param list<int> $attachmentIds */
    private function linkAttachments(array $attachmentIds, Sample $sample): void
    {
        if ($attachmentIds === []) {
            return;
        }

        Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->update([
                'attachable_type' => $sample->getMorphClass(),
                'attachable_id' => $sample->id,
            ]);
    }

    /**
     * @param  array<int|string, mixed>  $attachmentIds
     * @return list<int>
     */
    private function resolveAttachmentIdsForType(array $attachmentIds, string $type): array
    {
        if (isset($attachmentIds[$type]) && is_array($attachmentIds[$type])) {
            return array_map(intval(...), $attachmentIds[$type]);
        }

        if ($this->isFlatAttachmentList($attachmentIds)) {
            return array_map(intval(...), $attachmentIds);
        }

        return [];
    }

    /** @param array<int|string, mixed> $attachmentIds */
    private function isFlatAttachmentList(array $attachmentIds): bool
    {
        if ($attachmentIds === []) {
            return true;
        }

        return array_keys($attachmentIds) === range(0, count($attachmentIds) - 1);
    }
}
