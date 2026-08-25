<?php

declare(strict_types=1);

use App\Enums\FormulaStatus;
use App\Enums\TaskStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Formula;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\Samples\SampleService;
use App\Services\Tasks\TaskService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
});

it('scenario 1: approval creates workshop and tinting tasks in different queues', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $result = app(SampleService::class)->start(sampleSalesFields($project), $sales);
    completeSampleTask($result['task'], $sales, sampleSalesFields($project));

    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask(sampleTaskFor($result['sample'], 'reception_review_sample_request'), $reception, ['review_result' => 'forward']);

    $manager = User::factory()->inDepartment('management')->create();
    completeSampleTask(sampleTaskFor($result['sample']->fresh(), 'manager_approve_sample'), $manager, ['decision' => 'approved']);

    $workshopTask = sampleTaskFor($result['sample']->fresh(), 'workshop_make_sample');
    $tintingTask = sampleTaskFor($result['sample']->fresh(), 'tinting_author_formula');

    expect($workshopTask->department->code)->toBe('workshop')
        ->and($tintingTask->department->code)->toBe('tinting');
});

it('scenario 2: the registration task stays waiting until both have completed', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToWorkshop(app(SampleService::class)->start(sampleSalesFields($project), $sales)['sample'], $sales);

    $employee = Employee::factory()->inDepartment('tinting')->create();
    $tinting = User::factory()->inDepartment('tinting')->create();
    completeSampleTask(sampleTaskFor($sample, 'tinting_author_formula'), $tinting, [
        'body' => 'Formula text',
        'author_employee_id' => $employee->id,
        'authored_at' => '2026-08-20',
    ]);

    $registration = Task::query()
        ->where('subject_id', $sample->id)
        ->whereHas('definition', fn ($q) => $q->where('code', 'reception_register_formula'))
        ->firstOrFail();

    expect($registration->status)->toBe(TaskStatus::Waiting);
});

it('scenario 3: completing only the tinting task does not promote registration', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToWorkshop(app(SampleService::class)->start(sampleSalesFields($project), $sales)['sample'], $sales);

    $employee = Employee::factory()->inDepartment('tinting')->create();
    $tinting = User::factory()->inDepartment('tinting')->create();
    completeSampleTask(sampleTaskFor($sample, 'tinting_author_formula'), $tinting, [
        'body' => 'Formula text',
        'author_employee_id' => $employee->id,
        'authored_at' => '2026-08-20',
    ]);

    $registration = Task::query()
        ->where('subject_id', $sample->id)
        ->whereHas('definition', fn ($q) => $q->where('code', 'reception_register_formula'))
        ->firstOrFail();

    expect($registration->status)->toBe(TaskStatus::Waiting);
});

it('scenario 4: a formula with neither text nor a scanned sheet cannot be saved', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToWorkshop(app(SampleService::class)->start(sampleSalesFields($project), $sales)['sample'], $sales);
    $tinting = User::factory()->inDepartment('tinting')->create();
    $employee = Employee::factory()->inDepartment('tinting')->create();
    $task = sampleTaskFor($sample, 'tinting_author_formula');

    app(TaskService::class)->claim($task, $tinting);
    app(TaskService::class)->start($task->fresh(), $tinting);

    expect(fn () => app(TaskService::class)->complete($task->fresh(), $tinting, [
        'author_employee_id' => $employee->id,
        'authored_at' => '2026-08-20',
    ], []))->toThrow(TaskNotReadyToComplete::class);
});

it('scenario 5: the author is stored as an employee with no user account', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToWorkshop(app(SampleService::class)->start(sampleSalesFields($project), $sales)['sample'], $sales);
    $employee = Employee::factory()->inDepartment('tinting')->create(['user_id' => null]);
    $tinting = User::factory()->inDepartment('tinting')->create();

    completeSampleTask(sampleTaskFor($sample, 'tinting_author_formula'), $tinting, [
        'body' => 'Formula text',
        'author_employee_id' => $employee->id,
        'authored_at' => '2026-08-20',
    ]);

    $formula = Formula::query()->where('sample_id', $sample->id)->firstOrFail();
    expect($formula->author_employee_id)->toBe($employee->id)
        ->and($formula->authorEmployee?->name)->not->toBeEmpty();
});

it('scenario 6: registered_by_user_id is the acting user', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToClientDecision($project, $sales);

    $formula = Formula::query()->where('sample_id', $sample->id)->firstOrFail();
    $registrar = User::query()->find($formula->registered_by_user_id);
    expect($formula->registered_by_user_id)->not->toBeNull()
        ->and($registrar?->hasRole('reception'))->toBeTrue();
});

it('scenario 7: a reception correction preserves the original text and writes an audit row', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToWorkshop(app(SampleService::class)->start(sampleSalesFields($project), $sales)['sample'], $sales);

    $employee = Employee::factory()->inDepartment('tinting')->create();
    $tinting = User::factory()->inDepartment('tinting')->create();
    completeSampleTask(sampleTaskFor($sample, 'tinting_author_formula'), $tinting, [
        'body' => 'Original formula',
        'author_employee_id' => $employee->id,
        'authored_at' => '2026-08-20',
    ]);

    $workshop = User::factory()->inDepartment('workshop')->create();
    $photoId = Attachment::factory()->samplePhoto()->create(['uploaded_by_user_id' => $workshop->id])->id;
    completeSampleTask(sampleTaskFor($sample->fresh(), 'workshop_make_sample'), $workshop, [
        'ready_for_registration' => true,
    ], ['sample_photo' => [$photoId]]);

    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask(sampleTaskFor($sample->fresh(), 'reception_register_formula'), $reception, [
        'confirm_matches_sheet' => true,
        'corrections' => 'Fixed ratio',
    ]);

    $formula = Formula::query()->where('sample_id', $sample->id)->firstOrFail();
    expect($formula->body)->toContain('Original formula')
        ->and($formula->body)->toContain('[Correction] Fixed ratio')
        ->and(AuditLog::query()->where('auditable_id', $formula->id)->where('event', 'corrected')->exists())->toBeTrue();
});

it('scenario 8: version numbers increment per sample in a chain', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $first = rejectSample(advanceSampleToClientDecision($project, $sales), $sales);
    expect(Formula::query()->where('sample_id', $first->id)->max('version'))->toBe(1);

    $second = app(SampleService::class)->requestModification($first, [
        'modification_reason' => 'Try again',
        'color' => 'warm sand',
    ], $sales);

    advanceSampleToWorkshop($second, $sales);
    $employee = Employee::factory()->inDepartment('tinting')->create();
    $tinting = User::factory()->inDepartment('tinting')->create();
    completeSampleTask(sampleTaskFor($second, 'tinting_author_formula'), $tinting, [
        'body' => 'Second formula',
        'author_employee_id' => $employee->id,
        'authored_at' => '2026-08-21',
    ]);

    expect(Formula::query()->where('sample_id', $second->id)->max('version'))->toBe(1);
});

it('scenario 9: client approval marks exactly one formula approved', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToClientDecision($project, $sales);
    $formId = Attachment::factory()->clientApprovalForm()->create(['uploaded_by_user_id' => $sales->id])->id;

    completeSampleTask(sampleTaskFor($sample, 'sales_get_client_decision'), $sales, [
        'decision' => 'approved',
        'client_signatory_name' => 'Client Name',
        'decided_at' => '2026-08-18',
    ], ['client_approval_form' => [$formId]]);

    expect(Formula::query()->where('sample_id', $sample->id)->where('status', FormulaStatus::Approved)->count())->toBe(1);
});

it('scenario 10: superseding a sample marks its formula superseded', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $parent = rejectSample(advanceSampleToClientDecision($project, $sales), $sales);
    $formula = Formula::query()->where('sample_id', $parent->id)->firstOrFail();
    $formula->update(['status' => FormulaStatus::Registered]);

    app(SampleService::class)->requestModification($parent, [
        'modification_reason' => 'Try again',
        'color' => 'warm sand',
    ], $sales);

    expect($formula->fresh()->status)->toBe(FormulaStatus::Superseded);
});
