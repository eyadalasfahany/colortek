<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Exceptions\InvalidTaskTransition;
use App\Models\BlockerCategory;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskService;

it('moves a ready task to claimed and records who took it', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->create();

    $claimed = app(TaskService::class)->claim($task, $user);

    expect($claimed->status)->toBe(TaskStatus::Claimed)
        ->and($claimed->claimed_by_user_id)->toBe($user->id)
        ->and($claimed->claimed_at)->not->toBeNull();
});

it('writes a status event for every change', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->create();

    app(TaskService::class)->claim($task, $user);

    expect($task->statusEvents()->count())->toBe(1)
        ->and($task->statusEvents()->first()->to_status)->toBe('claimed');
});

it('refuses to start a task the user has not claimed', function (): void {
    $task = Task::factory()->claimed()->create();
    $other = User::factory()->create();

    expect(fn () => app(TaskService::class)->start($task, $other))
        ->toThrow(InvalidTaskTransition::class);
});

it('returns a released task to its department queue and clears the claim', function (): void {
    $task = Task::factory()->claimed()->create();

    $released = app(TaskService::class)->release($task, $task->claimant);

    expect($released->status)->toBe(TaskStatus::Ready)
        ->and($released->claimed_by_user_id)->toBeNull();
});

it('requires a category and a reason to block', function (): void {
    $task = Task::factory()->inProgress()->create();
    $category = BlockerCategory::factory()->create();

    expect(fn () => app(TaskService::class)->block($task, $task->claimant, $category, '', null))
        ->toThrow(InvalidArgumentException::class);
});
