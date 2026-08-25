<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\ActivitySeverity;
use App\Events\TaskBlocked;
use App\Events\TaskClaimed;
use App\Events\TaskCompleted;
use App\Events\TaskCreated;
use App\Services\Activity\ActivityRecorder;

final class RecordTaskActivity
{
    public function __construct(private ActivityRecorder $recorder) {}

    public function handleTaskCreated(TaskCreated $event): void
    {
        $this->safely(fn () => $this->recordCreated($event));
    }

    public function handleTaskClaimed(TaskClaimed $event): void
    {
        $this->safely(fn () => $this->recordClaimed($event));
    }

    public function handleTaskCompleted(TaskCompleted $event): void
    {
        $this->safely(fn () => $this->recordCompleted($event));
    }

    public function handleTaskBlocked(TaskBlocked $event): void
    {
        $this->safely(fn () => $this->recordBlocked($event));
    }

    private function safely(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable) {
            // Activity feed failures must not roll back committed work.
        }
    }

    private function recordCreated(TaskCreated $event): void
    {
        $task = $event->task->loadMissing(['department', 'project']);

        $this->recorder->record(
            type: 'task.created',
            severity: ActivitySeverity::Info,
            messageEn: __(':title was created in :department.', [
                'title' => $task->localizedTitle('en'),
                'department' => $task->department->getTranslation('name', 'en'),
            ], 'en'),
            messageAr: __(':title was created in :department.', [
                'title' => $task->localizedTitle('ar'),
                'department' => $task->department->getTranslation('name', 'ar'),
            ], 'ar'),
            project: $task->project,
            subject: $task,
            department: $task->department,
        );
    }

    private function recordClaimed(TaskClaimed $event): void
    {
        $task = $event->task->loadMissing(['department', 'project']);

        $this->recorder->record(
            type: 'task.claimed',
            severity: ActivitySeverity::Info,
            messageEn: __(':user claimed :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('en'),
            ], 'en'),
            messageAr: __(':user claimed :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('ar'),
            ], 'ar'),
            actor: $event->user,
            project: $task->project,
            subject: $task,
            department: $task->department,
        );
    }

    private function recordCompleted(TaskCompleted $event): void
    {
        $task = $event->task->loadMissing(['department', 'project']);

        $this->recorder->record(
            type: 'task.completed',
            severity: ActivitySeverity::Success,
            messageEn: __(':user completed :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('en'),
            ], 'en'),
            messageAr: __(':user completed :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('ar'),
            ], 'ar'),
            actor: $event->user,
            project: $task->project,
            subject: $task,
            department: $task->department,
        );
    }

    private function recordBlocked(TaskBlocked $event): void
    {
        $task = $event->task->loadMissing(['department', 'project']);

        $this->recorder->record(
            type: 'task.blocked',
            severity: ActivitySeverity::Blocker,
            messageEn: __(':user blocked :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('en'),
            ], 'en'),
            messageAr: __(':user blocked :title.', [
                'user' => $event->user->name,
                'title' => $task->localizedTitle('ar'),
            ], 'ar'),
            actor: $event->user,
            project: $task->project,
            subject: $task,
            department: $task->department,
        );
    }
}
