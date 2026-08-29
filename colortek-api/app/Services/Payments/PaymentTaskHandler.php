<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\ActivitySeverity;
use App\Enums\JournalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStage;
use App\Enums\QuotationStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Gateways\Odoo\Data\PaymentData;
use App\Gateways\Odoo\OdooGateway;
use App\Models\Attachment;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class PaymentTaskHandler
{
    public function __construct(
        private JournalService $journalService,
        private JournalWorkflowService $journalWorkflowService,
        private ActivityRecorder $activityRecorder,
        private OdooGateway $odoo,
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
            'sales_confirm_payment' => $this->handleSalesConfirm($task, $user, $fields, $attachmentIds),
            'reception_review_payment' => $this->handleReceptionReview($task, $user, $fields),
            'reception_daily_journal' => $this->handleJournalSubmit($task, $user, $fields),
            'accounting_process_journal' => $this->handleAccountingProcess($task, $user, $fields),
            'reception_fix_journal' => $this->handleJournalFix($task, $user, $fields),
            default => null,
        };
    }

    /** @param array<string, mixed> $fields */
    public function handleAfterComplete(Task $task, User $user, array $fields): void
    {
        $task->loadMissing(['definition', 'subject']);
        $code = $task->definition?->code;

        match ($code) {
            'reception_daily_journal' => $this->journalWorkflowService->ensureAccountingTask(
                $this->journalFromTask($task),
            ),
            'reception_fix_journal' => $this->journalWorkflowService->ensureAccountingTask(
                $this->journalFromTask($task),
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    private function handleSalesConfirm(Task $task, User $user, array $fields, array $attachmentIds): void
    {
        if (! filter_var($fields['quotation_locked'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            throw TaskNotReadyToComplete::missingField('quotation_locked');
        }

        $proofIds = $this->resolveAttachmentIdsForType($attachmentIds, 'payment_proof');
        if ($proofIds === [] && ! $user->can('payment.skip_proof')) {
            throw TaskNotReadyToComplete::missingAttachment('payment_proof');
        }

        /** @var Payment|null $payment */
        $payment = $task->subject instanceof Payment ? $task->subject : null;
        if ($payment === null) {
            throw new TaskNotReadyToComplete(
                __('Payment record not found for this task.'),
                'payment.not_found',
            );
        }

        $project = $payment->project ?? $task->project;
        if ($project === null) {
            throw new TaskNotReadyToComplete(
                __('Project not found for this payment.'),
                'payment.project_not_found',
            );
        }

        DB::transaction(function () use ($payment, $project, $user, $fields, $proofIds): void {
            $payment->update([
                'installment_number' => (int) $fields['installment_number'],
                'amount' => $fields['amount'],
                'method' => PaymentMethod::from((string) $fields['method']),
                'paid_at' => CarbonImmutable::parse((string) $fields['paid_at'])->toDateString(),
                'notes' => $fields['notes'] ?? null,
                'status' => PaymentStatus::Confirmed,
                'confirmed_by_user_id' => $user->id,
                'confirmed_at' => now(),
                'quotation_id' => $payment->quotation_id ?? $project->quotation_id,
            ]);

            $this->lockQuotation($project, $user);

            if ((int) $fields['installment_number'] === 1
                && in_array($project->stage, [ProjectStage::Lead, ProjectStage::Quotation], true)) {
                $project->update(['stage' => ProjectStage::Payment]);
            }

            $this->linkAttachments($proofIds, $payment);
        });

        // specs/13 §1 rule 3: pushed after commit so an ERP problem cannot roll
        // back a confirmed payment or block the queue.
        DB::afterCommit(fn () => $this->odoo->pushPaymentConfirmation(
            PaymentData::fromModel($payment->fresh()),
        ));

        $this->activityRecorder->record(
            type: 'payment.confirmed',
            severity: ActivitySeverity::Success,
            messageEn: sprintf('Payment of %s EGP confirmed for %s.', $fields['amount'], $project->reference),
            messageAr: sprintf('تم تأكيد دفعة %s جنيه للمشروع %s.', $fields['amount'], $project->reference),
            actor: $user,
            project: $project,
            subject: $payment->fresh(),
            department: $task->department,
        );
    }

    /** @param array<string, mixed> $fields */
    private function handleReceptionReview(Task $task, User $user, array $fields): void
    {
        if (($fields['review_result'] ?? '') === 'query' && empty($fields['review_note'])) {
            throw TaskNotReadyToComplete::missingField('review_note');
        }

        if (($fields['review_result'] ?? '') === 'query') {
            $payment = $task->subject instanceof Payment ? $task->subject : null;
            $project = $payment !== null ? $payment->project : $task->project;
            $projectReference = $project !== null ? $project->reference : 'project';

            $this->activityRecorder->record(
                type: 'payment.queried',
                severity: ActivitySeverity::Warning,
                messageEn: sprintf(
                    'Payment sent back to Sales for %s.',
                    $projectReference,
                ),
                messageAr: sprintf(
                    'تم إرجاع الدفعة إلى المبيعات للمشروع %s.',
                    $projectReference,
                ),
                actor: $user,
                project: $project,
                subject: $payment,
                department: $task->department,
                payload: ['review_note' => $fields['review_note'] ?? null],
            );

            return;
        }

        if (($fields['review_result'] ?? '') !== 'accepted') {
            return;
        }

        /** @var Payment|null $payment */
        $payment = $task->subject instanceof Payment ? $task->subject : null;
        if ($payment === null) {
            return;
        }

        DB::transaction(function () use ($payment, $user): void {
            $journal = $this->journalService->openJournalForDate(CarbonImmutable::today());

            $payment->update([
                'status' => PaymentStatus::Reviewed,
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
            ]);

            $this->journalService->attachPayment($journal, $payment->fresh());
        });

        $this->journalWorkflowService->ensureDailyJournalTask(CarbonImmutable::today());
    }

    /** @param array<string, mixed> $fields */
    private function handleJournalSubmit(Task $task, User $user, array $fields): void
    {
        $this->journalService->submit($this->journalFromTask($task), $user, $fields);
    }

    /** @param array<string, mixed> $fields */
    private function handleAccountingProcess(Task $task, User $user, array $fields): void
    {
        if (($fields['accounting_result'] ?? '') === 'query' && empty($fields['note'])) {
            throw TaskNotReadyToComplete::missingField('note');
        }

        if (($fields['accounting_result'] ?? '') !== 'processed') {
            $journal = $this->journalFromTask($task);
            $this->journalService->reopen($journal, $user, (string) ($fields['note'] ?? ''));
            $this->journalWorkflowService->createFixJournalTask($journal, (string) ($fields['note'] ?? ''));

            return;
        }

        $this->journalService->markAccounted($this->journalFromTask($task), $user, $fields);
    }

    /** @param array<string, mixed> $fields */
    private function handleJournalFix(Task $task, User $user, array $fields): void
    {
        if (empty($fields['fix_note'])) {
            throw TaskNotReadyToComplete::missingField('fix_note');
        }

        $journal = $this->journalFromTask($task);
        if ($journal->status !== JournalStatus::Open) {
            throw new TaskNotReadyToComplete(
                __('The journal must be open before it can be resubmitted.'),
                'journal.not_open',
            );
        }

        $this->journalService->submit($journal, $user, [
            'notes' => $fields['fix_note'],
        ]);
    }

    private function journalFromTask(Task $task): Journal
    {
        $subject = $task->subject;
        if ($subject instanceof Journal) {
            return $subject;
        }

        throw new TaskNotReadyToComplete(
            __('Journal record not found for this task.'),
            'journal.not_found',
        );
    }

    private function lockQuotation(Project $project, User $user): void
    {
        $quotation = $project->quotation;
        if ($quotation === null) {
            return;
        }

        if ($quotation->status === QuotationStatus::Locked) {
            return;
        }

        $quotation->update([
            'status' => QuotationStatus::Locked,
            'locked_at' => now(),
            'locked_by_user_id' => $user->id,
        ]);
    }

    /** @param list<int> $attachmentIds */
    private function linkAttachments(array $attachmentIds, Payment $payment): void
    {
        if ($attachmentIds === []) {
            return;
        }

        Attachment::query()
            ->whereIn('id', $attachmentIds)
            ->update([
                'attachable_type' => $payment->getMorphClass(),
                'attachable_id' => $payment->id,
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
