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
use App\Models\Client;
use App\Models\Formula;
use App\Models\Project;
use App\Models\Sample;
use App\Models\SampleApproval;
use App\Models\Setting;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Activity\ActivityRecorder;
use App\Services\Workflow\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SampleTaskHandler
{
    public function __construct(
        private SampleReferenceGenerator $referenceGenerator,
        private ActivityRecorder $activityRecorder,
        private WorkflowEngine $workflowEngine,
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
            'sales_create_sample_request' => $this->handleSalesCreate($task, $fields),
            'reception_review_sample_request' => $this->handleReceptionReview($fields),
            'manager_approve_sample' => $this->handleManagerApprove($task, $user, $fields),
            'workshop_make_sample' => $this->handleWorkshopComplete($task, $user, $attachmentIds),
            'tinting_author_formula' => $this->handleTintingAuthor($task, $user, $fields, $attachmentIds),
            'reception_register_formula' => $this->handleReceptionRegister($task, $user),
            'sales_get_client_decision' => $this->handleClientDecision($task, $user, $fields, $attachmentIds),
            'sales_create_modification_request' => $this->handleModificationRequest($task, $user, $fields),
            default => null,
        };
    }

    /** @param array<string, mixed> $fields */
    public function handleAfterComplete(Task $task, User $user, array $fields): void
    {
        $task->loadMissing(['definition', 'subject']);
        if ($task->definition?->code === 'sales_create_modification_request') {
            $this->startChildSampleRequest($this->sampleFromTask($task), $user);
        }
    }

    /** @param array<string, mixed> $data */
    public function createModificationChild(Sample $parent, User $user, array $data): Sample
    {
        return DB::transaction(function () use ($parent, $user, $data): Sample {
            $parent->update(['status' => SampleStatus::Superseded]);
            $client = $parent->client;
            $project = $parent->project;
            $attempt = $parent->attempt_number + 1;
            $child = Sample::query()->create([
                'reference' => $this->referenceGenerator->forSample($project, $client),
                'client_id' => $parent->client_id,
                'project_id' => $parent->project_id,
                'parent_sample_id' => $parent->id,
                'root_sample_id' => $parent->root_sample_id ?: $parent->id,
                'attempt_number' => $attempt,
                'requested_by_user_id' => $user->id,
                'requested_at' => now(),
                'color' => $data['color'] ?? $parent->color,
                'texture' => $data['texture'] ?? $parent->texture,
                'client_reference' => $data['client_reference'] ?? $parent->client_reference,
                'size' => $data['size'] ?? $parent->size,
                'finish_requirement' => $data['finish_requirement'] ?? $parent->finish_requirement,
                'modification_reason' => $data['modification_reason'] ?? null,
                'status' => SampleStatus::PendingManagerApproval,
                'is_presale' => $parent->is_presale,
            ]);

            return $child;
        });
    }

    /** @param array<string, mixed> $fields */
    private function handleSalesCreate(Task $task, array $fields): void
    {
        $sample = $this->sampleFromTask($task);
        $sample->update([
            'color' => $fields['color'] ?? $sample->color,
            'texture' => $fields['texture'] ?? $sample->texture,
            'size' => $fields['size'] ?? $sample->size,
            'status' => SampleStatus::PendingManagerApproval,
        ]);
    }

    /** @param array<string, mixed> $fields */
    private function handleReceptionReview(array $fields): void
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

        SampleApproval::query()->create([
            'sample_id' => $sample->id,
            'type' => SampleApprovalType::Manager,
            'decision' => $decision === 'approved' ? SampleApprovalDecision::Approved : SampleApprovalDecision::Rejected,
            'decided_by_user_id' => $user->id,
            'recorded_by_user_id' => $user->id,
            'comments' => $fields['comments'] ?? null,
            'decided_at' => now(),
        ]);

        $sample->update([
            'status' => $decision === 'approved' ? SampleStatus::InWorkshop : SampleStatus::RejectedByManager,
        ]);

        if ($decision === 'approved' && $sample->attempt_number >= (int) (Setting::get('sample_repeat_attempt_threshold') ?? 4)) {
            Task::query()
                ->where('subject_id', $sample->id)
                ->where('subject_type', $sample->getMorphClass())
                ->whereHas('definition', fn ($q) => $q->where('code', 'manager_approve_sample'))
                ->update(['priority' => TaskPriority::High]);
        }
    }

    /** @param array<string, mixed> $attachmentIds */
    private function handleWorkshopComplete(Task $task, User $user, array $attachmentIds): void
    {
        $sample = $this->sampleFromTask($task);
        $hasFormula = Formula::query()->where('sample_id', $sample->id)->where('status', FormulaStatus::Draft)->exists();
        if (! $hasFormula) {
            throw new TaskNotReadyToComplete(
                __('Formula must be authored before workshop completion.'),
                'sample.formula_missing',
            );
        }

        $photoIds = $this->attachmentIdsForType($attachmentIds, 'sample_photo');
        if ($photoIds === []) {
            throw TaskNotReadyToComplete::missingAttachment('sample_photo');
        }

        TimeEntry::query()
            ->where('task_id', $task->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'seconds' => 3600]);

        $sample->update(['status' => SampleStatus::AwaitingFormulaRegistration]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    private function handleTintingAuthor(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        $sample = $this->sampleFromTask($task);
        $body = trim((string) ($fields['body'] ?? ''));
        $sheetIds = $this->attachmentIdsForType($attachmentIds, 'formula_sheet');
        if ($body === '' && $sheetIds === []) {
            throw TaskNotReadyToComplete::missingField('body');
        }

        $version = (int) Formula::query()->where('sample_id', $sample->id)->max('version') + 1;
        $formula = Formula::query()->create([
            'reference' => $this->referenceGenerator->forFormula($sample, $version),
            'sample_id' => $sample->id,
            'version' => $version,
            'body' => $body !== '' ? $body : null,
            'author_employee_id' => (int) $fields['author_employee_id'],
            'author_user_id' => $user->id,
            'authored_at' => isset($fields['authored_at'])
                ? CarbonImmutable::parse((string) $fields['authored_at'])
                : now(),
            'status' => FormulaStatus::Draft,
        ]);

        if ($sheetIds !== []) {
            Attachment::query()->whereIn('id', $sheetIds)->update([
                'attachable_type' => $formula->getMorphClass(),
                'attachable_id' => $formula->id,
            ]);
        }
    }

    private function handleReceptionRegister(Task $task, User $user): void
    {
        $sample = $this->sampleFromTask($task);
        $formula = Formula::query()->where('sample_id', $sample->id)->where('status', FormulaStatus::Draft)->latest('version')->first();
        if ($formula === null) {
            throw new TaskNotReadyToComplete(__('No draft formula to register.'), 'formula.missing');
        }

        $formula->update([
            'status' => FormulaStatus::Registered,
            'registered_by_user_id' => $user->id,
            'registered_at' => now(),
        ]);
        $sample->update(['status' => SampleStatus::ReadyForClientApproval]);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    private function handleClientDecision(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        $sample = $this->sampleFromTask($task);
        $formIds = $this->attachmentIdsForType($attachmentIds, 'client_approval_form');
        if ($formIds === []) {
            throw TaskNotReadyToComplete::missingAttachment('client_approval_form');
        }

        $decision = (string) ($fields['decision'] ?? '');
        if ($decision === 'rejected' && empty($fields['comments'])) {
            throw TaskNotReadyToComplete::missingField('comments');
        }

        $formula = Formula::query()->where('sample_id', $sample->id)->where('status', FormulaStatus::Registered)->latest('version')->first();
        $decidedAt = CarbonImmutable::parse((string) $fields['decided_at']);

        SampleApproval::query()->updateOrCreate(
            ['sample_id' => $sample->id, 'type' => SampleApprovalType::Client],
            [
                'decision' => $decision === 'approved' ? SampleApprovalDecision::Approved : SampleApprovalDecision::Rejected,
                'decided_by_user_id' => $user->id,
                'recorded_by_user_id' => $user->id,
                'client_signatory_name' => $fields['client_signatory_name'] ?? null,
                'decided_at' => $decidedAt,
                'comments' => $fields['comments'] ?? null,
            ],
        );

        if ($decision === 'approved') {
            $formula?->update(['status' => FormulaStatus::Approved]);
            $sample->update([
                'status' => SampleStatus::Approved,
                'approved_formula_id' => $formula?->id,
            ]);
        } else {
            $sample->update(['status' => SampleStatus::RejectedByClient]);
        }
    }

    /** @param array<string, mixed> $fields */
    private function handleModificationRequest(Task $task, User $user, array $fields): void
    {
        $parent = $this->sampleFromTask($task);
        if ($parent->status !== SampleStatus::RejectedByClient) {
            throw new TaskNotReadyToComplete(__('Modifications are only allowed after client rejection.'), 'sample.modification_not_allowed');
        }

        $this->createModificationChild($parent, $user, $fields);

        $this->activityRecorder->record(
            type: 'sample.modification_requested',
            severity: ActivitySeverity::Warning,
            messageEn: sprintf('Modification requested for sample %s.', $parent->reference),
            messageAr: sprintf('تم طلب تعديل للعينة %s.', $parent->reference),
            actor: $user,
            project: $parent->project,
            subject: $parent,
        );
    }

    private function startChildSampleRequest(Sample $parent, User $user): void
    {
        $child = Sample::query()->where('parent_sample_id', $parent->id)->orderByDesc('id')->first();
        if ($child === null) {
            return;
        }

        $template = WorkflowTemplate::query()->where('code', 'sample_request')->where('is_active', true)->whereNotNull('published_at')->firstOrFail();
        $this->workflowEngine->startAtDefinition($template, $child, 'reception_review_sample_request');
    }

    private function sampleFromTask(Task $task): Sample
    {
        $subject = $task->subject;
        if ($subject instanceof Sample) {
            return $subject;
        }

        throw new TaskNotReadyToComplete(__('Sample not found for this task.'), 'sample.not_found');
    }

    /** @param array<string, mixed> $attachmentIds @return list<int> */
    private function attachmentIdsForType(array $attachmentIds, string $type): array
    {
        if (isset($attachmentIds[$type]) && is_array($attachmentIds[$type])) {
            return array_map(intval(...), $attachmentIds[$type]);
        }

        return [];
    }
}
