<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Events\TaskBlocked;
use App\Events\TaskClaimed;
use App\Events\TaskCompleted;
use App\Events\TaskReassigned;
use App\Events\TaskStarted;
use App\Events\TaskUnblocked;
use App\Exceptions\InvalidTaskTransition;
use App\Exceptions\TaskAlreadyClaimed;
use App\Models\BlockerCategory;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskFieldValue;
use App\Models\TaskStatusEvent;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Payments\PaymentTaskHandler;
use App\Services\Samples\SampleTaskHandler;
use App\Services\Site\SiteBlockService;
use App\Services\Site\SiteVisitTaskHandler;
use App\Services\Time\TimerService;
use App\Services\Workflow\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class TaskService
{
    public function __construct(
        private TaskRepository $repository,
        private TaskValidator $validator,
        private WorkflowEngine $workflowEngine,
        private AuditLogger $auditLogger,
        private PaymentTaskHandler $paymentTaskHandler,
        private SampleTaskHandler $sampleTaskHandler,
        private SiteVisitTaskHandler $siteVisitTaskHandler,
        private SiteBlockService $siteBlockService,
        private TimerService $timerService,
    ) {}

    public function claim(Task $task, User $user): Task
    {
        if ($task->status === TaskStatus::Claimed) {
            throw TaskAlreadyClaimed::forTask($task->loadMissing('claimant'));
        }

        if (! $task->status->canTransitionTo(TaskStatus::Claimed)) {
            throw InvalidTaskTransition::between($task->status, TaskStatus::Claimed);
        }

        return DB::transaction(function () use ($task, $user): Task {
            if (! $this->repository->claimAtomically($task->id, $user->id)) {
                throw TaskAlreadyClaimed::forTask($task->fresh(['claimant']));
            }

            $task = $task->fresh();
            $this->recordStatusEvent($task, TaskStatus::Ready, TaskStatus::Claimed, $user);

            DB::afterCommit(fn () => event(new TaskClaimed($task->fresh(), $user)));

            return $task->fresh();
        });
    }

    public function release(Task $task, User $user): Task
    {
        $this->assertClaimant($task, $user);

        return DB::transaction(function () use ($task, $user): Task {
            return $this->transitionTo($task, TaskStatus::Ready, $user, afterUpdate: [
                'claimed_by_user_id' => null,
                'claimed_at' => null,
            ]);
        });
    }

    public function start(Task $task, User $user): Task
    {
        $this->assertClaimant($task, $user);

        return DB::transaction(function () use ($task, $user): Task {
            $started = $this->transitionTo($task, TaskStatus::InProgress, $user, afterUpdate: [
                'started_at' => $task->started_at ?? now(),
            ]);

            DB::afterCommit(fn () => event(new TaskStarted($started, $user)));

            return $started;
        });
    }

    public function pause(Task $task, User $user): Task
    {
        $this->assertClaimant($task, $user);

        return DB::transaction(function () use ($task, $user): Task {
            $paused = $this->transitionTo($task, TaskStatus::Paused, $user);
            $this->timerService->stopForTask($task);

            return $paused;
        });
    }

    public function resume(Task $task, User $user): Task
    {
        $this->assertClaimant($task, $user);

        return DB::transaction(fn (): Task => $this->transitionTo($task, TaskStatus::InProgress, $user));
    }

    public function unblock(Task $task, User $user, string $resolutionNote): Task
    {
        if ($task->status !== TaskStatus::Blocked) {
            throw InvalidTaskTransition::between($task->status, TaskStatus::InProgress);
        }

        if ($resolutionNote === '') {
            throw new InvalidArgumentException('A resolution note is required to unblock this task.');
        }

        return DB::transaction(function () use ($task, $user, $resolutionNote): Task {
            $updated = $this->transitionTo($task, TaskStatus::InProgress, $user, $resolutionNote, [
                'blocker_category_id' => null,
                'blocker_reason' => null,
                'blocker_expected_resolution' => null,
                'blocked_by_user_id' => null,
                'blocked_at' => null,
            ]);

            DB::afterCommit(fn () => event(new TaskUnblocked($updated, $user)));

            return $updated;
        });
    }

    public function comment(Task $task, User $user, string $body): TaskComment
    {
        if ($body === '') {
            throw new InvalidArgumentException('Comment body is required.');
        }

        return TaskComment::query()->create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'body' => $body,
            'created_at' => now(),
        ]);
    }

    public function reassign(Task $task, User $actor, User $assignee): Task
    {
        $previous = $task->claimant;

        return DB::transaction(function () use ($task, $actor, $assignee, $previous): Task {
            $task->update([
                'claimed_by_user_id' => $assignee->id,
                'claimed_at' => now(),
                'status' => in_array($task->status, [TaskStatus::Ready, TaskStatus::Claimed], true)
                    ? TaskStatus::Claimed
                    : $task->status,
            ]);

            $fresh = $task->fresh(['department', 'claimant', 'project']);

            $this->auditLogger->log(
                auditable: $fresh,
                event: 'reassigned',
                user: $actor,
                oldValues: ['claimed_by_user_id' => $previous?->id],
                newValues: ['claimed_by_user_id' => $assignee->id],
            );

            DB::afterCommit(fn () => event(new TaskReassigned($fresh, $actor, $previous, $assignee)));

            return $fresh;
        });
    }

    public function updateDeadline(Task $task, User $user, CarbonImmutable $dueAt): Task
    {
        $old = $task->due_at?->toIso8601String();
        $task->update(['due_at' => $dueAt, 'is_overdue' => false]);

        $this->auditLogger->log(
            auditable: $task,
            event: 'deadline_overridden',
            user: $user,
            oldValues: ['due_at' => $old],
            newValues: ['due_at' => $dueAt->toIso8601String()],
        );

        return $task->fresh();
    }

    /** @param array<string, mixed> $data */
    public function createAdhoc(array $data, User $user): Task
    {
        $projectId = $data['project_id'] ?? null;
        $sequence = Task::query()
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->count() + 1;

        $reference = $projectId !== null
            ? sprintf('PRJ%d-ADH-%03d', $projectId, $sequence)
            : sprintf('ADH-%s-%03d', Str::upper(Str::random(6)), $sequence);

        $task = Task::query()->create([
            'reference' => $reference,
            'project_id' => $projectId,
            'title' => $data['title'],
            'instructions' => $data['instructions'] ?? null,
            'department_id' => $data['department_id'],
            'status' => TaskStatus::Ready,
            'priority' => $data['priority'] ?? TaskPriority::Normal,
            'due_at' => isset($data['due_at']) ? CarbonImmutable::parse($data['due_at']) : null,
            'ready_at' => now(),
        ]);

        $this->auditLogger->log($task, 'created', $user, newValues: ['reference' => $task->reference, 'adhoc' => true]);

        return $task->fresh(['department', 'project']);
    }

    public function block(
        Task $task,
        User $user,
        BlockerCategory $category,
        string $reason,
        ?CarbonImmutable $expectedResolution,
    ): Task {
        $this->assertClaimant($task, $user);

        if ($reason === '') {
            throw new InvalidArgumentException('A blocker reason is required.');
        }

        if ($category->requires_expected_date && $expectedResolution === null) {
            throw new InvalidArgumentException('This blocker category requires an expected resolution date.');
        }

        return DB::transaction(function () use ($task, $user, $category, $reason, $expectedResolution): Task {
            $this->timerService->stopForTask($task);

            $updated = $this->transitionTo($task, TaskStatus::Blocked, $user, afterUpdate: [
                'blocker_category_id' => $category->id,
                'blocker_reason' => $reason,
                'blocker_expected_resolution' => $expectedResolution,
                'blocked_by_user_id' => $user->id,
                'blocked_at' => now(),
            ]);

            DB::afterCommit(fn () => event(new TaskBlocked($updated, $user, $category, $reason)));

            return $updated;
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     * @return array{task: Task, created: Collection<int, Task>}
     */
    public function complete(Task $task, User $user, array $fields, array $attachmentIds): array
    {
        $this->assertClaimant($task, $user);

        /** @var Collection<int, Task> $createdTasks */
        $createdTasks = collect();
        $completed = $task;

        DB::transaction(function () use ($task, $user, $fields, $attachmentIds, &$createdTasks, &$completed): void {
            $this->validator->assertReadyToComplete($task, $fields, $attachmentIds);
            $this->timerService->stopForTask($task);
            $this->paymentTaskHandler->handleBeforeComplete($task, $user, $fields, $attachmentIds);
            $this->sampleTaskHandler->handleBeforeComplete($task, $user, $fields, $attachmentIds);
            $this->siteVisitTaskHandler->handleBeforeComplete($task, $user, $fields, $attachmentIds);
            $this->persistFieldValues($task, $fields);

            $completed = $this->transitionTo($task, TaskStatus::Completed, $user, afterUpdate: [
                'completed_at' => now(),
                'completed_by_user_id' => $user->id,
            ]);

            $createdTasks = $this->workflowEngine->advance($completed);
            $this->paymentTaskHandler->handleAfterComplete($completed, $user, $fields);
            $this->sampleTaskHandler->handleAfterComplete($completed, $user, $fields);
            $this->siteVisitTaskHandler->handleAfterComplete($completed, $user, $fields);
        });

        DB::afterCommit(fn () => event(new TaskCompleted($completed->fresh(), $user, $createdTasks)));

        return [
            'task' => $completed->fresh(['department', 'project', 'claimant']),
            'created' => $createdTasks->each->load('department'),
        ];
    }

    public function overrideSiteBlock(Task $task, User $user, string $reason): Task
    {
        return $this->siteBlockService->override($task, $user, $reason);
    }

    /** @param array<string, mixed> $fields */
    private function persistFieldValues(Task $task, array $fields): void
    {
        foreach ($fields as $key => $value) {
            TaskFieldValue::updateOrCreate(
                ['task_id' => $task->id, 'key' => (string) $key],
                ['value' => $value],
            );
        }
    }

    /** @param array<string, mixed> $afterUpdate */
    private function transitionTo(
        Task $task,
        TaskStatus $to,
        User $user,
        ?string $note = null,
        array $afterUpdate = [],
    ): Task {
        if (! $task->status->canTransitionTo($to)) {
            throw InvalidTaskTransition::between($task->status, $to);
        }

        $from = $task->status;

        $task->update(array_merge(['status' => $to], $afterUpdate));
        $this->recordStatusEvent($task->fresh(), $from, $to, $user, $note);

        return $task->fresh();
    }

    private function recordStatusEvent(
        Task $task,
        TaskStatus $from,
        TaskStatus $to,
        User $user,
        ?string $note = null,
    ): void {
        TaskStatusEvent::create([
            'task_id' => $task->id,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'user_id' => $user->id,
            'note' => $note,
            'created_at' => now(),
        ]);

        $this->auditLogger->log(
            auditable: $task,
            event: 'updated',
            user: $user,
            oldValues: ['status' => $from->value],
            newValues: ['status' => $to->value],
            reason: $note,
        );
    }

    private function assertClaimant(Task $task, User $user): void
    {
        if ($task->claimed_by_user_id !== $user->id) {
            throw InvalidTaskTransition::notClaimant();
        }
    }
}
