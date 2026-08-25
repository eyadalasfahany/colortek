<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\ActivityEvent;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Tasks\TaskService;
use App\Services\Time\WorkingCalendar;
use App\Services\Workflow\WorkflowEngine;
use Database\Seeders\ReferenceSeeder;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

it('carries a three step workflow across three department queues', function (): void {
    ActivityEvent::query()->delete();

    $template = WorkflowTemplate::factory()->threeStep()->create();
    $project = Project::factory()->create();
    $instance = app(WorkflowEngine::class)->start($template, $project);

    $first = $instance->tasks()->sole();
    expect($first->department->code)->toBe('sales')
        ->and($first->status)->toBe(TaskStatus::Ready)
        ->and(app(WorkingCalendar::class)->isWorkingTime($first->due_at))->toBeTrue();

    $sales = User::factory()->inDepartment('sales')->create();
    app(TaskService::class)->claim($first, $sales);
    app(TaskService::class)->start($first->fresh(), $sales);
    app(TaskService::class)->complete($first->fresh(), $sales, ['amount' => 50000], []);

    $second = $instance->tasks()->where('id', '!=', $first->id)->sole();
    expect($second->department->code)->toBe('reception')
        ->and($second->status)->toBe(TaskStatus::Ready);

    $reception = User::factory()->inDepartment('reception')->create();
    app(TaskService::class)->claim($second, $reception);
    app(TaskService::class)->start($second->fresh(), $reception);
    app(TaskService::class)->complete($second->fresh(), $reception, [], []);

    expect($instance->fresh()->tasks()->where('status', TaskStatus::Ready)->sole()->department->code)
        ->toBe('accounting');

    expect(ActivityEvent::query()->where('type', 'task.created')->count())->toBe(3);
});
