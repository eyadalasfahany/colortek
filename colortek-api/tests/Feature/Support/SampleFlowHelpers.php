<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Attachment;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Sample;
use App\Models\Task;
use App\Models\User;
use App\Services\Samples\SampleService;
use App\Services\Tasks\TaskService;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;

/** @return array<string, mixed> */
function sampleSalesFields(Project $project): array
{
    return [
        'client_id' => $project->client_id,
        'project_id' => $project->id,
        'color' => 'warm sand',
        'texture' => 'fine',
        'size' => 'A4',
    ];
}

/**
 * @param  array<string, mixed>  $fields
 * @param  array<string, mixed>  $attachmentIds
 */
function completeSampleTask(Task $task, User $user, array $fields = [], array $attachmentIds = []): void
{
    app(TaskService::class)->claim($task, $user);
    app(TaskService::class)->start($task->fresh(), $user);
    app(TaskService::class)->complete($task->fresh(), $user, $fields, $attachmentIds);
}

function sampleTaskFor(Sample $sample, string $code, TaskStatus $status = TaskStatus::Ready): Task
{
    return Task::query()
        ->where('subject_id', $sample->id)
        ->whereHas('definition', fn ($q) => $q->where('code', $code))
        ->where('status', $status)
        ->firstOrFail();
}

function uploadAttachment(User $user, string $type): int
{
    Sanctum::actingAs($user);
    $response = test()->postJson('/api/v1/attachments', [
        'file' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'),
        'type' => $type,
    ])->assertCreated();

    return (int) $response->json('data.id');
}

function advanceSampleToWorkshop(Sample $sample, User $sales): Sample
{
    $sample = $sample->fresh();
    $salesTask = Task::query()
        ->where('subject_id', $sample->id)
        ->whereHas('definition', fn ($q) => $q->where('code', 'sales_create_sample_request'))
        ->first();

    if ($salesTask !== null && $salesTask->status !== TaskStatus::Completed) {
        completeSampleTask($salesTask, $sales, sampleSalesFields($sample->project));
    }

    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask(sampleTaskFor($sample->fresh(), 'reception_review_sample_request'), $reception, ['review_result' => 'forward']);

    $manager = User::factory()->inDepartment('management')->create();
    completeSampleTask(sampleTaskFor($sample->fresh(), 'manager_approve_sample'), $manager, ['decision' => 'approved']);

    return $sample->fresh();
}

function advanceSampleToClientDecision(Project $project, User $sales): Sample
{
    $result = app(SampleService::class)->start(sampleSalesFields($project), $sales);
    $sample = advanceSampleToWorkshop($result['sample'], $sales);

    $employee = Employee::factory()->inDepartment('tinting')->create();
    $tinting = User::factory()->inDepartment('tinting')->create();
    completeSampleTask(sampleTaskFor($sample, 'tinting_author_formula'), $tinting, [
        'body' => 'Tint mix 1:2',
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
    ]);

    return $sample->fresh();
}

function rejectSample(Sample $sample, User $sales, string $reason = 'Too dark'): Sample
{
    $formId = Attachment::factory()->clientApprovalForm()->create(['uploaded_by_user_id' => $sales->id])->id;
    completeSampleTask(sampleTaskFor($sample->fresh(), 'sales_get_client_decision'), $sales, [
        'decision' => 'rejected',
        'comments' => $reason,
        'client_signatory_name' => 'Client Name',
        'decided_at' => '2026-08-18',
    ], ['client_approval_form' => [$formId]]);

    return $sample->fresh();
}

function rejectSampleViaChild(Sample $parent, User $sales, string $reason = 'Too grey'): Sample
{
    $child = rejectSample($parent, $sales, $reason);

    return Sample::query()->where('parent_sample_id', $parent->id)->orderByDesc('id')->first() ?? $child;
}

function salesUserWithoutPresale(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(['sample.view', 'sample.create']);
    $department = Department::query()->where('code', 'sales')->firstOrFail();
    $user->departments()->syncWithoutDetaching([$department->id]);

    return $user->fresh();
}
