<?php

declare(strict_types=1);

use App\Enums\SampleStatus;
use App\Enums\TaskPriority;
use App\Exceptions\TaskNotReadyToComplete;
use App\Http\Resources\TaskResource;
use App\Models\Project;
use App\Models\Sample;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Samples\SampleService;
use App\Services\Workflow\WorkflowEngine;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
});

it('scenario 1: a modification can only be created from a rejected sample', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $sample = advanceSampleToClientDecision($project, $sales);

    expect(fn () => app(SampleService::class)->requestModification($sample->fresh(), [
        'modification_reason' => 'Too dark',
        'color' => 'warm sand',
    ], $sales))->toThrow(TaskNotReadyToComplete::class);
});

it('scenario 2: the new sample has the correct parent, root and attempt number', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $parent = rejectSample(advanceSampleToClientDecision($project, $sales), $sales, 'Too dark');

    $child = app(SampleService::class)->requestModification($parent, [
        'modification_reason' => 'Needs lighter tone',
        'color' => 'light sand',
    ], $sales);

    expect($child->parent_sample_id)->toBe($parent->id)
        ->and($child->root_sample_id)->toBe($parent->root_sample_id)
        ->and($child->attempt_number)->toBe(2);
});

it('scenario 3: a third-generation sample keeps root_sample_id pointing at the first', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $first = rejectSample(advanceSampleToClientDecision($project, $sales), $sales, 'Too dark');
    $second = app(SampleService::class)->requestModification($first, [
        'modification_reason' => 'Still too dark',
        'color' => 'lighter sand',
    ], $sales);
    $second->update(['status' => SampleStatus::RejectedByClient]);

    $third = app(SampleService::class)->requestModification($second, [
        'modification_reason' => 'Grey in daylight',
        'color' => 'warm sand',
    ], $sales);

    expect($third->root_sample_id)->toBe($first->root_sample_id)
        ->and($third->attempt_number)->toBe(3);
});

it('scenario 4: the parent is marked superseded and nothing else about it changes', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $parent = rejectSample(advanceSampleToClientDecision($project, $sales), $sales);
    $originalColor = $parent->color;
    $originalReference = $parent->reference;

    app(SampleService::class)->requestModification($parent, [
        'modification_reason' => 'Adjust tone',
        'color' => 'warm sand',
    ], $sales);

    $parent = $parent->fresh();
    expect($parent->status)->toBe(SampleStatus::Superseded)
        ->and($parent->color)->toBe($originalColor)
        ->and($parent->reference)->toBe($originalReference);
});

it('scenario 5: requirement fields are prefilled from the parent', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $parent = rejectSample(advanceSampleToClientDecision($project, $sales), $sales);
    $parent->update(['texture' => 'fine', 'size' => 'A4']);

    $child = app(SampleService::class)->requestModification($parent->fresh(), [
        'modification_reason' => 'Adjust tone',
        'color' => $parent->color,
    ], $sales);

    expect($child->texture)->toBe('fine')
        ->and($child->size)->toBe('A4');
});

it('scenario 6: the new instance enters at the reception step, not at the sales step', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $parent = rejectSample(advanceSampleToClientDecision($project, $sales), $sales);

    $child = app(SampleService::class)->requestModification($parent, [
        'modification_reason' => 'Adjust tone',
        'color' => 'warm sand',
    ], $sales);

    expect(sampleTaskFor($child, 'reception_review_sample_request'))->not->toBeNull()
        ->and(Task::query()->where('subject_id', $child->id)->whereHas('definition', fn ($q) => $q->where('code', 'sales_create_sample_request'))->exists())->toBeFalse();
});

it('scenario 7: manager approval is still created for modifications', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $parent = rejectSample(advanceSampleToClientDecision($project, $sales), $sales);

    $child = app(SampleService::class)->requestModification($parent, [
        'modification_reason' => 'Adjust tone',
        'color' => 'warm sand',
    ], $sales);

    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask(sampleTaskFor($child, 'reception_review_sample_request'), $reception, ['review_result' => 'forward']);

    expect(sampleTaskFor($child->fresh(), 'manager_approve_sample'))->not->toBeNull();
});

it('scenario 8: the workshop task shows the parent reference and rejection reason', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $parent = rejectSample(advanceSampleToClientDecision($project, $sales), $sales, 'Too dark in daylight');

    $child = app(SampleService::class)->requestModification($parent, [
        'modification_reason' => 'Adjust tone',
        'color' => 'warm sand',
    ], $sales);

    advanceSampleToWorkshop($child, $sales);
    $workshopTask = sampleTaskFor($child->fresh(), 'workshop_make_sample');
    $workshopTask->load(['subject.parentSample.approvals']);

    $context = TaskResource::make($workshopTask)->resolve()['subject'];
    expect($context['parent_reference'])->toBe($parent->reference)
        ->and($context['parent_rejection_reason'])->toBe('Too dark in daylight');
});

it('scenario 9: counting attempts by root_sample_id returns the right number', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $first = rejectSample(advanceSampleToClientDecision($project, $sales), $sales);
    $second = app(SampleService::class)->requestModification($first, [
        'modification_reason' => 'Try again',
        'color' => 'warm sand',
    ], $sales);
    $second->update(['status' => SampleStatus::RejectedByClient]);
    app(SampleService::class)->requestModification($second, [
        'modification_reason' => 'Third try',
        'color' => 'warm sand',
    ], $sales);

    expect(Sample::query()->where('root_sample_id', $first->root_sample_id)->count())->toBe(3);
});

it('scenario 10: reaching attempt 4 raises the manager approval priority', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();

    $child = Sample::factory()->create([
        'project_id' => $project->id,
        'client_id' => $project->client_id,
        'attempt_number' => 4,
        'status' => SampleStatus::PendingManagerApproval,
        'color' => 'warm sand',
    ]);

    $template = WorkflowTemplate::query()->where('code', 'sample_request')->firstOrFail();
    app(WorkflowEngine::class)->startAtDefinition(
        $template,
        $child->fresh(['project', 'client']),
        'reception_review_sample_request',
    );

    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask(sampleTaskFor($child, 'reception_review_sample_request'), $reception, ['review_result' => 'forward']);

    $managerTask = sampleTaskFor($child->fresh(), 'manager_approve_sample');
    expect($managerTask->priority)->toBe(TaskPriority::High);
});
