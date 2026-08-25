<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Enums\TaskStatus;
use App\Events\TaskBlocked;
use App\Events\TaskClaimed;
use App\Events\TaskCompleted;
use App\Exceptions\InvalidTaskTransition;
use App\Exceptions\TaskAlreadyClaimed;
use App\Models\BlockerCategory;
use App\Models\Task;
use App\Models\TaskFieldValue;
use App\Models\TaskStatusEvent;
use App\Models\User;
use App\Repositories\TaskRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Workflow\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class TaskService
{
    public function __construct(
        private TaskRepository $repository,
        private TaskValidator $validator,
        private WorkflowEngine $workflowEngine,
        private AuditLogger $auditLogger,
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
            return $this->transitionTo($task, TaskStatus::InProgress, $user, afterUpdate: [
                'started_at' => $task->started_at ?? now(),
            ]);
        });
    }

    public function pause(Task $task, User $user): Task
    {
        $this->assertClaimant($task, $user);

        return DB::transaction(fn (): Task => $this->transitionTo($task, TaskStatus::Paused, $user));
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
            $this->persistFieldValues($task, $fields);

            $completed = $this->transitionTo($task, TaskStatus::Completed, $user, afterUpdate: [
                'completed_at' => now(),
                'completed_by_user_id' => $user->id,
            ]);

            $createdTasks = $this->workflowEngine->advance($completed);
        });

        DB::afterCommit(fn () => event(new TaskCompleted($completed->fresh(), $user, $createdTasks)));

        return [
            'task' => $completed->fresh(['department', 'project', 'claimant']),
            'created' => $createdTasks->each->load('department'),
        ];
    }

    public function overrideSiteBlock(Task $task, User $user, string $reason): Task
    {
        if ($task->status !== TaskStatus::Pending) {
            throw InvalidTaskTransition::between($task->status, TaskStatus::Ready);
        }

        return DB::transaction(fn (): Task => $this->transitionTo($task, TaskStatus::Ready, $user, note: $reason, afterUpdate: [
            'ready_at' => now(),
        ]));
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
