<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\ActivityEvent;
use App\Models\Task;
use App\Models\User;
use App\Services\Activity\ActivityRecorder;
use App\Services\Tasks\TaskService;
use Database\Seeders\ReferenceSeeder;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

it('writes both language messages at the moment of the event', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->create();

    app(TaskService::class)->claim($task, $user);

    $event = ActivityEvent::latest('id')->first();

    expect($event->message_en)->not->toBeEmpty()
        ->and($event->message_ar)->not->toBeEmpty();
});

it('keeps the original message after the actor is renamed', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->create(['name' => 'Ahmed Hassan']);

    app(TaskService::class)->claim($task, $user);
    $original = ActivityEvent::latest('id')->first()->message_en;

    $user->update(['name' => 'Someone Else']);

    expect(ActivityEvent::latest('id')->first()->message_en)->toBe($original);
});

it('does not roll back the task when writing the activity row fails', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->create();

    $this->mock(ActivityRecorder::class, function ($mock): void {
        $mock->shouldReceive('record')->andThrow(new RuntimeException('feed write failed'));
    });

    app(TaskService::class)->claim($task, $user);

    expect($task->fresh()->status)->toBe(TaskStatus::Claimed);
});
