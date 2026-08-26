<?php

declare(strict_types=1);

use App\Exceptions\TaskAlreadyClaimed;
use App\Models\Task;
use App\Models\User;
use App\Services\Tasks\TaskService;

it('lets only one user win a contested claim', function (): void {
    $task = Task::factory()->ready()->create();
    $first = User::factory()->create();
    $second = User::factory()->create();

    app(TaskService::class)->claim($task, $first);

    expect(fn () => app(TaskService::class)->claim($task->fresh(), $second))
        ->toThrow(TaskAlreadyClaimed::class);
});
