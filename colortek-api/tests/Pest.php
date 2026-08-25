<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Models\WorkflowTransition;
use App\Services\Tasks\TaskService;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

uses(TestCase::class, RefreshDatabase::class)->in('Feature');

uses(TestCase::class, RefreshDatabase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/**
 * @param  list<string>  $requiredFields
 * @param  list<string>  $requiredAttachments
 * @return array{0: WorkflowInstance, 1: Task, 2: WorkflowTemplate, 3: WorkflowTaskDefinition}
 */
function seedTwoStepWorkflow(array $requiredFields = [], array $requiredAttachments = []): array
{
    $template = WorkflowTemplate::factory()->twoStep()->create();
    $secondDefinition = $template->definitions()->where('code', 'step_two')->firstOrFail();

    if ($requiredFields !== [] || $requiredAttachments !== []) {
        $firstDefinition = $template->definitions()->where('code', 'step_one')->firstOrFail();
        $firstDefinition->update([
            'required_fields' => $requiredFields,
            'required_attachment_types' => $requiredAttachments,
        ]);
    }

    $project = Project::factory()->create();
    $instance = app(WorkflowEngine::class)->start($template, $project);
    $first = $instance->tasks()->sole();
    $user = User::factory()->create();

    app(TaskService::class)->claim($first, $user);
    app(TaskService::class)->start($first->fresh(), $user);

    return [$instance, $first->fresh(['claimant']), $template, $secondDefinition];
}

/** @return array{0: WorkflowInstance, 1: Task, 2: WorkflowTemplate} */
function seedConditionalWorkflow(): array
{
    $sales = Department::query()->where('code', 'sales')->firstOrFail();
    $reception = Department::query()->where('code', 'reception')->firstOrFail();
    $accounting = Department::query()->where('code', 'accounting')->firstOrFail();

    $template = WorkflowTemplate::factory()->create(['code' => 'conditional_test']);

    $first = WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'decide',
        'title_en' => 'Decide',
        'title_ar' => 'قرار',
        'department_id' => $sales->id,
        'is_entry_point' => true,
        'required_fields' => ['decision'],
        'required_attachment_types' => [],
    ]);

    $approved = WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'approved_path',
        'title_en' => 'Approved',
        'title_ar' => 'موافق',
        'department_id' => $reception->id,
        'required_fields' => [],
        'required_attachment_types' => [],
    ]);

    $rejected = WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'rejected_path',
        'title_en' => 'Rejected',
        'title_ar' => 'مرفوض',
        'department_id' => $accounting->id,
        'required_fields' => [],
        'required_attachment_types' => [],
    ]);

    WorkflowTransition::create([
        'template_id' => $template->id,
        'from_task_definition_id' => $first->id,
        'to_task_definition_id' => $approved->id,
        'condition' => ['field' => 'decision', 'operator' => 'equals', 'value' => 'approved'],
        'join_mode' => 'any',
        'sort_order' => 1,
    ]);

    WorkflowTransition::create([
        'template_id' => $template->id,
        'from_task_definition_id' => $first->id,
        'to_task_definition_id' => $rejected->id,
        'condition' => ['field' => 'decision', 'operator' => 'equals', 'value' => 'rejected'],
        'join_mode' => 'any',
        'sort_order' => 2,
    ]);

    $instance = app(WorkflowEngine::class)->start($template, Project::factory()->create());
    $task = $instance->tasks()->sole();
    $user = User::factory()->create();
    app(TaskService::class)->claim($task, $user);
    app(TaskService::class)->start($task->fresh(), $user);

    return [$instance, $task->fresh(['claimant']), $template];
}

/**
 * @return array{0: WorkflowInstance, 1: Task, 2: Task, 3: WorkflowTaskDefinition}
 */
function seedParallelJoinWorkflow(string $joinMode): array
{
    $sales = Department::query()->where('code', 'sales')->firstOrFail();
    $workshop = Department::query()->where('code', 'workshop')->firstOrFail();
    $reception = Department::query()->where('code', 'reception')->firstOrFail();

    $template = WorkflowTemplate::factory()->create(['code' => 'parallel_'.$joinMode]);

    $left = WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'left',
        'title_en' => 'Left',
        'title_ar' => 'Left',
        'department_id' => $sales->id,
        'is_entry_point' => true,
        'required_fields' => [],
        'required_attachment_types' => [],
    ]);

    $right = WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'right',
        'title_en' => 'Right',
        'title_ar' => 'Right',
        'department_id' => $workshop->id,
        'is_entry_point' => true,
        'required_fields' => [],
        'required_attachment_types' => [],
    ]);

    $target = WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'target',
        'title_en' => 'Target',
        'title_ar' => 'Target',
        'department_id' => $reception->id,
        'required_fields' => [],
        'required_attachment_types' => [],
    ]);

    foreach ([$left, $right] as $source) {
        WorkflowTransition::create([
            'template_id' => $template->id,
            'from_task_definition_id' => $source->id,
            'to_task_definition_id' => $target->id,
            'join_mode' => $joinMode,
            'sort_order' => 1,
        ]);
    }

    $project = Project::factory()->create();
    $instance = app(WorkflowEngine::class)->start($template, $project);

    $leftTask = $instance->tasks()->where('task_definition_id', $left->id)->sole();
    $rightTask = $instance->tasks()->where('task_definition_id', $right->id)->sole();

    $leftUser = User::factory()->create();
    $rightUser = User::factory()->create();

    app(TaskService::class)->claim($leftTask, $leftUser);
    app(TaskService::class)->start($leftTask->fresh(), $leftUser);
    app(TaskService::class)->claim($rightTask, $rightUser);
    app(TaskService::class)->start($rightTask->fresh(), $rightUser);

    return [$instance, $leftTask->fresh(['claimant']), $rightTask->fresh(['claimant']), $target];
}

/**
 * @return array{0: WorkflowInstance, 1: WorkflowTaskDefinition, 2: WorkflowTaskDefinition, 3: Project}
 */
function seedSiteHoldWorkflow(bool $siteReady, bool $blockAll): array
{
    $site = Department::query()->where('code', 'site')->firstOrFail();
    $workshop = Department::query()->where('code', 'workshop')->firstOrFail();

    $template = WorkflowTemplate::factory()->create(['code' => 'site_hold_test']);

    $siteDefinition = WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'site_task',
        'title_en' => 'Site Task',
        'title_ar' => 'Site',
        'department_id' => $site->id,
        'is_entry_point' => true,
        'blocks_when_site_not_ready' => true,
        'required_fields' => [],
        'required_attachment_types' => [],
    ]);

    $workshopDefinition = WorkflowTaskDefinition::create([
        'template_id' => $template->id,
        'code' => 'workshop_task',
        'title_en' => 'Workshop Task',
        'title_ar' => 'Workshop',
        'department_id' => $workshop->id,
        'is_entry_point' => true,
        'blocks_when_site_not_ready' => false,
        'required_fields' => [],
        'required_attachment_types' => [],
    ]);

    $project = Project::factory()->create([
        'site_ready' => $siteReady,
        'block_all_when_site_not_ready' => $blockAll,
    ]);

    $instance = app(WorkflowEngine::class)->start($template, $project);

    return [$instance, $siteDefinition, $workshopDefinition, $project];
}
