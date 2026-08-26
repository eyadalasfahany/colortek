<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Exceptions\InvalidTaskTransition;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Department;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Services\Tasks\TaskService;
use App\Services\Time\WorkingCalendar;
use App\Services\Workflow\ConditionEvaluator;
use App\Services\Workflow\WorkflowEngine;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceSeeder;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

it('rejects completion when a required field is missing and creates no successor', function (): void {
    [$instance, $first] = seedTwoStepWorkflow(requiredFields: ['amount']);

    expect(fn () => app(TaskService::class)->complete($first, $first->claimant, [], []))
        ->toThrow(TaskNotReadyToComplete::class);

    expect($instance->tasks()->count())->toBe(1)
        ->and($first->fresh()->status)->toBe(TaskStatus::InProgress);
});

it('rejects completion when a required attachment is missing', function (): void {
    [$instance, $first] = seedTwoStepWorkflow(requiredAttachments: ['payment_proof']);

    expect(fn () => app(TaskService::class)->complete($first, $first->claimant, [], []))
        ->toThrow(TaskNotReadyToComplete::class);

    expect($instance->tasks()->count())->toBe(1);
});

it('creates the expected successor in the expected department with ready status', function (): void {
    [$instance, $first, , $secondDefinition] = seedTwoStepWorkflow();

    app(TaskService::class)->complete($first, $first->claimant, [], []);

    $successor = $instance->tasks()->where('task_definition_id', $secondDefinition->id)->sole();

    expect($successor->department->code)->toBe('reception')
        ->and($successor->status)->toBe(TaskStatus::Ready);
});

it('creates exactly one successor when Complete is pressed twice', function (): void {
    [$instance, $first, , $secondDefinition] = seedTwoStepWorkflow();

    app(TaskService::class)->complete($first, $first->claimant, [], []);
    try {
        app(TaskService::class)->complete($first->fresh(), $first->claimant, [], []);
    } catch (InvalidTaskTransition) {
    }

    expect($instance->tasks()->where('task_definition_id', $secondDefinition->id)->count())->toBe(1);
});

it('fires conditional transitions for approved and rejected decisions', function (): void {
    [$instance, $first, $template] = seedConditionalWorkflow();

    app(TaskService::class)->complete($first, $first->claimant, ['decision' => 'approved'], []);

    expect($instance->tasks()->whereHas('definition', fn ($q) => $q->where('code', 'approved_path'))->exists())->toBeTrue()
        ->and($instance->tasks()->whereHas('definition', fn ($q) => $q->where('code', 'rejected_path'))->exists())->toBeFalse();

    $rejectedInstance = app(WorkflowEngine::class)->start($template, Project::factory()->create());
    $rejectedFirst = $rejectedInstance->tasks()->sole();
    $rejectUser = User::factory()->create();
    app(TaskService::class)->claim($rejectedFirst, $rejectUser);
    app(TaskService::class)->start($rejectedFirst->fresh(), $rejectUser);

    app(TaskService::class)->complete($rejectedFirst->fresh(), $rejectUser, ['decision' => 'rejected'], []);

    expect($rejectedInstance->tasks()->whereHas('definition', fn ($q) => $q->where('code', 'rejected_path'))->exists())->toBeTrue();
});

it('holds a join_mode=all target until every predecessor completes', function (): void {
    [$instance, $left, $right, $targetDefinition] = seedParallelJoinWorkflow('all');

    app(TaskService::class)->complete($left, $left->claimant, [], []);

    $target = $instance->tasks()->where('task_definition_id', $targetDefinition->id)->sole();
    expect($target->status)->toBe(TaskStatus::Waiting);

    app(TaskService::class)->complete($right, $right->claimant, [], []);

    expect($target->fresh()->status)->toBe(TaskStatus::Ready);
});

it('creates a join_mode=any target only once', function (): void {
    [$instance, $left, $right, $targetDefinition] = seedParallelJoinWorkflow('any');

    app(TaskService::class)->complete($left, $left->claimant, [], []);
    app(TaskService::class)->complete($right, $right->claimant, [], []);

    expect($instance->tasks()->where('task_definition_id', $targetDefinition->id)->count())->toBe(1);
});

it('leaves running instances on their original template version when republished', function (): void {
    $template = WorkflowTemplate::factory()->twoStep()->create(['code' => 'test', 'version' => 1]);
    $project = Project::factory()->create();
    $instance = app(WorkflowEngine::class)->start($template, $project);

    WorkflowTemplate::factory()->create([
        'code' => 'test',
        'version' => 2,
        'is_active' => true,
        'published_at' => now(),
    ]);
    $template->update(['is_active' => false]);

    expect($instance->fresh()->template->version)->toBe(1)
        ->and(WorkflowTemplate::where('code', 'test')->max('version'))->toBe(2);
});

it('holds only site tasks when the site is not ready', function (): void {
    [$instance, $siteTaskDefinition, $workshopTaskDefinition] = seedSiteHoldWorkflow(siteReady: false, blockAll: false);

    $siteTask = $instance->tasks()->where('task_definition_id', $siteTaskDefinition->id)->sole();
    $workshopTask = $instance->tasks()->where('task_definition_id', $workshopTaskDefinition->id)->sole();

    expect($siteTask->status)->toBe(TaskStatus::Pending)
        ->and($workshopTask->status)->toBe(TaskStatus::Ready);
});

it('holds every task when block_all_when_site_not_ready is enabled on the project', function (): void {
    [$instance, $siteTaskDefinition, $workshopTaskDefinition] = seedSiteHoldWorkflow(
        siteReady: false,
        blockAll: true,
    );

    expect($instance->tasks()->where('task_definition_id', $siteTaskDefinition->id)->sole()->status)
        ->toBe(TaskStatus::Pending)
        ->and($instance->tasks()->where('task_definition_id', $workshopTaskDefinition->id)->sole()->status)
        ->toBe(TaskStatus::Pending);
});

it('releases one held task through a site override', function (): void {
    [$instance, $siteTaskDefinition] = seedSiteHoldWorkflow(siteReady: false, blockAll: false);
    $siteTask = $instance->tasks()->where('task_definition_id', $siteTaskDefinition->id)->sole();
    $user = User::factory()->create();

    $released = app(TaskService::class)->overrideSiteBlock($siteTask, $user, 'Emergency override');

    expect($released->status)->toBe(TaskStatus::Ready);
});

it('releases every held task when the site becomes ready again', function (): void {
    [$instance, $siteTaskDefinition, , $project] = seedSiteHoldWorkflow(siteReady: false, blockAll: false);

    $project->update(['site_ready' => true]);
    app(WorkflowEngine::class)->releaseSiteHeldTasks($project->fresh());

    expect($instance->tasks()->where('task_definition_id', $siteTaskDefinition->id)->sole()->status)
        ->toBe(TaskStatus::Ready);
});

it('computes deadlines across a Friday weekend and a holiday', function (): void {
    Setting::updateOrCreate(['key' => 'work_start'], ['value' => '09:00', 'group' => 'general']);
    Setting::updateOrCreate(['key' => 'work_end'], ['value' => '17:00', 'group' => 'general']);
    Setting::updateOrCreate(['key' => 'weekend_days'], ['value' => ['friday'], 'group' => 'general']);

    Holiday::create([
        'date' => '2026-09-05',
        'name' => ['en' => 'Test holiday', 'ar' => 'إجازة'],
        'type' => 'public',
        'is_recurring' => false,
    ]);

    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-03 15:00', 'Africa/Cairo'));

    $template = WorkflowTemplate::factory()->create();
    $department = Department::query()->where('code', 'sales')->first();
    WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'deadline_step',
        'title_en' => 'Deadline Step',
        'title_ar' => 'Deadline',
        'department_id' => $department->id,
        'is_entry_point' => true,
        'sla_minutes' => 240,
        'required_fields' => [],
        'required_attachment_types' => [],
    ]);

    $instance = app(WorkflowEngine::class)->start($template, Project::factory()->create());
    $task = $instance->tasks()->sole();

    expect($task->due_at->format('Y-m-d H:i'))->toBe('2026-09-06 11:00')
        ->and(app(WorkingCalendar::class)->isWorkingTime($task->due_at))->toBeTrue();

    CarbonImmutable::setTestNow();
});

it('rolls the whole completion back when transition evaluation fails', function (): void {
    [$instance, $first] = seedTwoStepWorkflow();

    $evaluator = Mockery::mock(ConditionEvaluator::class);
    $evaluator->shouldReceive('passes')->andThrow(new RuntimeException('forced failure'));
    app()->instance(ConditionEvaluator::class, $evaluator);
    app()->forgetInstance(WorkflowEngine::class);
    app()->forgetInstance(TaskService::class);

    expect(fn () => app(TaskService::class)->complete($first, $first->claimant, [], []))
        ->toThrow(RuntimeException::class);

    expect($first->fresh()->status)->toBe(TaskStatus::InProgress)
        ->and(Task::count())->toBe(1)
        ->and($instance->tasks()->count())->toBe(1);
});
