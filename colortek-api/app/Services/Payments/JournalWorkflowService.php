<?php

declare(strict_types=1);

namespace App\Services\Payments;

use App\Enums\TaskStatus;
use App\Models\Journal;
use App\Models\Task;
use App\Models\WorkflowTaskDefinition;
use App\Services\Audit\AuditLogger;
use App\Services\Tasks\DeadlineCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class JournalWorkflowService
{
    public function __construct(
        private JournalService $journalService,
        private DeadlineCalculator $deadlineCalculator,
        private AuditLogger $auditLogger,
    ) {}

    public function ensureDailyJournalTask(CarbonImmutable $date): Task
    {
        $journal = $this->journalService->openJournalForDate($date);
        $definition = $this->definitionFor('reception_daily_journal');

        $existing = $this->findOpenTaskForJournal($definition, $journal);
        if ($existing !== null) {
            return $existing;
        }

        return $this->createJournalTask($definition, $journal, TaskStatus::Ready);
    }

    public function ensureAccountingTask(Journal $journal): Task
    {
        $definition = $this->definitionFor('accounting_process_journal');

        $existing = $this->findOpenTaskForJournal($definition, $journal);
        if ($existing !== null) {
            return $existing;
        }

        return $this->createJournalTask($definition, $journal, TaskStatus::Ready);
    }

    public function createFixJournalTask(Journal $journal, string $note): Task
    {
        $definition = $this->definitionFor('reception_fix_journal');

        return $this->createJournalTask(
            $definition,
            $journal,
            TaskStatus::Ready,
            sprintf("%s\n\n%s", $definition->instructions_en, $note),
        );
    }

    public function autoCloseEmptyJournalForDate(CarbonImmutable $date): void
    {
        $journal = Journal::query()
            ->whereDate('journal_date', $date->toDateString())
            ->first();

        if ($journal === null || $journal->payments()->exists()) {
            return;
        }

        $this->journalService->submitEmptyJournal($journal);

        $definition = $this->definitionFor('reception_daily_journal');
        $task = $this->findOpenTaskForJournal($definition, $journal);

        if ($task !== null) {
            $task->update([
                'status' => TaskStatus::Completed,
                'completed_at' => now(),
            ]);
        }
    }

    private function definitionFor(string $code): WorkflowTaskDefinition
    {
        return WorkflowTaskDefinition::query()
            ->where('code', $code)
            ->whereHas('template', fn ($query) => $query
                ->where('code', 'payment_cycle')
                ->where('is_active', true))
            ->firstOrFail();
    }

    private function findOpenTaskForJournal(WorkflowTaskDefinition $definition, Journal $journal): ?Task
    {
        return Task::query()
            ->where('task_definition_id', $definition->id)
            ->where('subject_type', $journal->getMorphClass())
            ->where('subject_id', $journal->id)
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])
            ->first();
    }

    private function createJournalTask(
        WorkflowTaskDefinition $definition,
        Journal $journal,
        TaskStatus $status,
        ?string $instructions = null,
    ): Task {
        $reference = sprintf(
            'JNL%s-%s',
            $journal->journal_date->format('Ymd'),
            Str::upper(Str::replace('_', '-', $definition->code)),
        );

        $task = Task::query()->create([
            'reference' => $reference,
            'instance_id' => null,
            'task_definition_id' => $definition->id,
            'project_id' => null,
            'subject_type' => $journal->getMorphClass(),
            'subject_id' => $journal->id,
            'title' => $definition->title_en,
            'instructions' => $instructions ?? $definition->instructions_en,
            'department_id' => $definition->department_id,
            'status' => $status,
            'priority' => $definition->priority,
            'due_at' => $this->deadlineCalculator->for(
                $definition,
                null,
                CarbonImmutable::now(),
            ),
            'ready_at' => $status === TaskStatus::Ready ? now() : null,
        ]);

        $this->auditLogger->log(
            auditable: $task,
            event: 'created',
            user: null,
            newValues: [
                'reference' => $task->reference,
                'status' => $task->status->value,
                'journal_id' => $journal->id,
            ],
        );

        return $task;
    }
}
