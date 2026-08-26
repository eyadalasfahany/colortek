<?php

declare(strict_types=1);

use App\Enums\FormulaStatus;
use App\Enums\SampleStatus;
use App\Enums\TaskStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Attachment;
use App\Models\Client;
use App\Models\Employee;
use App\Models\Formula;
use App\Models\Project;
use App\Models\Sample;
use App\Models\SampleApproval;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Samples\SampleService;
use App\Services\Tasks\TaskService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
});

it('seeds the sample_request workflow template', function (): void {
    expect(WorkflowTemplate::query()->where('code', 'sample_request')->where('is_active', true)->exists())->toBeTrue();
});

it('scenario 1: a sample with no client cannot be created', function (): void {
    $sales = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($sales);

    $this->postJson('/api/v1/samples', ['color' => 'red'])->assertUnprocessable();
});

it('scenario 2: a sample with no project requires sample.create_presale', function (): void {
    $client = Client::factory()->create();
    $sales = salesUserWithoutPresale();
    Sanctum::actingAs($sales);

    $this->postJson('/api/v1/samples', [
        'client_id' => $client->id,
        'color' => 'red',
    ])->assertForbidden();
});

it('scenario 3: manager approval is always required', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $result = app(SampleService::class)->start(sampleSalesFields($project), $sales);

    completeSampleTask($result['task'], $sales, sampleSalesFields($project));

    $receptionTask = sampleTaskFor($result['sample'], 'reception_review_sample_request');
    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask($receptionTask, $reception, ['review_result' => 'forward']);

    expect(sampleTaskFor($result['sample']->fresh(), 'manager_approve_sample'))->not->toBeNull()
        ->and(Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'workshop_make_sample'))->exists())->toBeFalse();
});

it('scenario 4: manager rejection stops the flow', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $result = app(SampleService::class)->start(sampleSalesFields($project), $sales);
    completeSampleTask($result['task'], $sales, sampleSalesFields($project));

    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask(sampleTaskFor($result['sample'], 'reception_review_sample_request'), $reception, ['review_result' => 'forward']);

    $manager = User::factory()->inDepartment('management')->create();
    completeSampleTask(sampleTaskFor($result['sample'], 'manager_approve_sample'), $manager, [
        'decision' => 'rejected',
        'comments' => 'Not viable',
    ]);

    expect($result['sample']->fresh()->status)->toBe(SampleStatus::RejectedByManager)
        ->and(Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'workshop_make_sample'))->exists())->toBeFalse();
});

it('scenario 5: workshop cannot complete before formula authored', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $result = app(SampleService::class)->start(sampleSalesFields($project), $sales);
    advanceSampleToWorkshop($result['sample'], $sales);

    $workshop = User::factory()->inDepartment('workshop')->create();
    $workshopTask = sampleTaskFor($result['sample'], 'workshop_make_sample');
    $photoId = Attachment::factory()->samplePhoto()->create(['uploaded_by_user_id' => $workshop->id])->id;

    app(TaskService::class)->claim($workshopTask, $workshop);
    app(TaskService::class)->start($workshopTask->fresh(), $workshop);

    expect(fn () => app(TaskService::class)->complete($workshopTask->fresh(), $workshop, [
        'ready_for_registration' => true,
    ], ['sample_photo' => [$photoId]]))->toThrow(TaskNotReadyToComplete::class);
});

it('scenario 6: workshop timer stops on completion', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $result = app(SampleService::class)->start(sampleSalesFields($project), $sales);
    advanceSampleToWorkshop($result['sample'], $sales);

    $employee = Employee::factory()->inDepartment('tinting')->create();
    $tinting = User::factory()->inDepartment('tinting')->create();
    completeSampleTask(sampleTaskFor($result['sample'], 'tinting_author_formula'), $tinting, [
        'body' => 'Formula text',
        'author_employee_id' => $employee->id,
        'authored_at' => '2026-08-20',
    ]);

    $workshop = User::factory()->inDepartment('workshop')->create();
    $workshopTask = sampleTaskFor($result['sample'], 'workshop_make_sample');
    TimeEntry::query()->create([
        'task_id' => $workshopTask->id,
        'user_id' => $workshop->id,
        'employee_id' => $employee->id,
        'started_at' => now()->subHour(),
        'source' => 'timer',
    ]);

    $photoId = Attachment::factory()->samplePhoto()->create(['uploaded_by_user_id' => $workshop->id])->id;
    completeSampleTask($workshopTask, $workshop, ['ready_for_registration' => true], ['sample_photo' => [$photoId]]);

    expect(TimeEntry::query()->where('task_id', $workshopTask->id)->whereNotNull('ended_at')->exists())->toBeTrue();
});

it('scenario 7: client decision blocked without signed form', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToClientDecision($project, $sales);
    $task = sampleTaskFor($sample, 'sales_get_client_decision');

    app(TaskService::class)->claim($task, $sales);
    app(TaskService::class)->start($task->fresh(), $sales);

    expect(fn () => app(TaskService::class)->complete($task->fresh(), $sales, [
        'decision' => 'approved',
        'client_signatory_name' => 'Client Name',
        'decided_at' => '2026-08-20',
    ], []))->toThrow(TaskNotReadyToComplete::class);
});

it('scenario 8: decided_at stored separately from upload time', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToClientDecision($project, $sales);
    $formId = Attachment::factory()->clientApprovalForm()->create(['uploaded_by_user_id' => $sales->id])->id;

    completeSampleTask(sampleTaskFor($sample, 'sales_get_client_decision'), $sales, [
        'decision' => 'approved',
        'client_signatory_name' => 'Client Name',
        'decided_at' => '2026-08-18',
    ], ['client_approval_form' => [$formId]]);

    $approval = SampleApproval::query()->where('sample_id', $sample->id)->where('type', 'client')->first();
    expect($approval?->decided_at?->toDateString())->toBe('2026-08-18');
});

it('scenario 9: approval marks exactly one formula approved', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToClientDecision($project, $sales);
    $formId = Attachment::factory()->clientApprovalForm()->create(['uploaded_by_user_id' => $sales->id])->id;

    completeSampleTask(sampleTaskFor($sample, 'sales_get_client_decision'), $sales, [
        'decision' => 'approved',
        'client_signatory_name' => 'Client Name',
        'decided_at' => '2026-08-18',
    ], ['client_approval_form' => [$formId]]);

    $sample = $sample->fresh();
    expect(Formula::query()->where('sample_id', $sample->id)->where('status', FormulaStatus::Approved)->count())->toBe(1)
        ->and($sample->approved_formula_id)->not->toBeNull();
});

it('scenario 10: rejection does not auto-create modification', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToClientDecision($project, $sales);
    $formId = Attachment::factory()->clientApprovalForm()->create(['uploaded_by_user_id' => $sales->id])->id;

    completeSampleTask(sampleTaskFor($sample, 'sales_get_client_decision'), $sales, [
        'decision' => 'rejected',
        'comments' => 'Too dark',
        'client_signatory_name' => 'Client Name',
        'decided_at' => '2026-08-18',
    ], ['client_approval_form' => [$formId]]);

    expect(Sample::query()->count())->toBe(1)
        ->and(Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'sales_create_modification_request'))->exists())->toBeFalse();
});

it('scenario 11: workshop task claimable while site not ready', function (): void {
    $project = Project::factory()->siteNotReady()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $result = app(SampleService::class)->start(sampleSalesFields($project), $sales);
    advanceSampleToWorkshop($result['sample'], $sales);

    $workshopTask = sampleTaskFor($result['sample'], 'workshop_make_sample');
    expect($workshopTask->status)->toBe(TaskStatus::Ready);
});

it('starts a sample via HTTP and exposes form_schema', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($sales);

    $response = $this->postJson('/api/v1/samples', sampleSalesFields($project))->assertCreated();
    expect($response->json('meta.task.form_schema'))->not->toBeNull();
});

it('approval form returns PDF and sets form_generated_at', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToClientDecision($project, $sales);
    Sanctum::actingAs($sales);

    $this->postJson("/api/v1/samples/{$sample->id}/approval-form")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');

    expect(SampleApproval::query()->where('sample_id', $sample->id)->whereNotNull('form_generated_at')->exists())->toBeTrue();
});
