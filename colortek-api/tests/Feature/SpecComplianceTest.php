<?php

declare(strict_types=1);

use App\Enums\JournalStatus;
use App\Enums\TaskStatus;
use App\Models\ActivityEvent;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\BlockerCategory;
use App\Models\Employee;
use App\Models\IdempotencyKey;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TimeEntry;
use App\Models\User;
use App\Services\Tasks\TaskService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
});

it('resumes a paused task', function (): void {
    $task = Task::factory()->create(['status' => TaskStatus::Paused]);
    $user = User::factory()->create();
    $task->update(['claimed_by_user_id' => $user->id]);

    $resumed = app(TaskService::class)->resume($task, $user);

    expect($resumed->status)->toBe(TaskStatus::InProgress);
});

it('unblocks a task with a resolution note', function (): void {
    $task = Task::factory()->create(['status' => TaskStatus::Blocked]);
    $user = User::factory()->create();
    $task->update(['claimed_by_user_id' => $user->id]);

    $unblocked = app(TaskService::class)->unblock($task, $user, 'Issue resolved');

    expect($unblocked->status)->toBe(TaskStatus::InProgress)
        ->and($unblocked->blocker_reason)->toBeNull();
});

it('creates ad-hoc tasks via API', function (): void {
    $supervisor = User::factory()->inDepartment('management')->create();
    Sanctum::actingAs($supervisor);

    $departmentId = $supervisor->departments()->first()->id;

    $response = $this->postJson('/api/v1/tasks', [
        'title' => 'Follow up with client',
        'department_id' => $departmentId,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.title', 'Follow up with client');
});

it('comments on a task via API', function (): void {
    $task = Task::factory()->inProgress()->create();
    $user = User::factory()->inDepartment('sales')->create();
    $task->update(['claimed_by_user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Need more info'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Need more info');

    expect(TaskComment::query()->where('task_id', $task->id)->count())->toBe(1);
});

it('reassigns a task and records activity', function (): void {
    $task = Task::factory()->claimed()->create();
    $manager = User::factory()->inDepartment('management')->create();
    $assignee = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/tasks/{$task->id}/reassign", ['assignee_user_id' => $assignee->id])
        ->assertOk()
        ->assertJsonPath('data.claimant.id', $assignee->id);

    expect(ActivityEvent::query()->where('type', 'task.reassigned')->exists())->toBeTrue();
});

it('returns the same claim response for repeated idempotency keys', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($user);

    $headers = ['Idempotency-Key' => 'claim-test-key'];

    $first = $this->postJson("/api/v1/tasks/{$task->id}/claim", [], $headers)->assertOk();
    $second = $this->postJson("/api/v1/tasks/{$task->id}/claim", [], $headers)->assertOk();

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(IdempotencyKey::query()->count())->toBe(1);
});

it('deletes attachments while the owning task is unlocked', function (): void {
    $task = Task::factory()->inProgress()->create();
    $user = User::factory()->inDepartment('sales')->create();
    $attachment = Attachment::factory()->paymentProof()->create([
        'attachable_type' => $task->getMorphClass(),
        'attachable_id' => $task->id,
        'uploaded_by_user_id' => $user->id,
    ]);
    Sanctum::actingAs($user);

    $this->deleteJson("/api/v1/attachments/{$attachment->id}")->assertNoContent();
    expect(Attachment::query()->find($attachment->id))->toBeNull();
});

it('submits and reopens journals via API', function (): void {
    $reception = User::factory()->inDepartment('reception')->create();
    $journal = Journal::factory()->create(['status' => JournalStatus::Open, 'journal_date' => '2026-08-20']);
    $journal->payments()->attach(
        Payment::factory()->create()->id,
        ['amount_snapshot' => 1000],
    );

    Sanctum::actingAs($reception);

    $this->postJson('/api/v1/journals/2026-08-20/submit')->assertOk();
    expect($journal->fresh()->status)->toBe(JournalStatus::Submitted);

    $accounting = User::factory()->inDepartment('management')->create();
    Sanctum::actingAs($accounting);

    $this->postJson('/api/v1/journals/2026-08-20/reopen', ['reason' => 'Missing payment'])
        ->assertOk();

    expect($journal->fresh()->status)->toBe(JournalStatus::Open)
        ->and(ActivityEvent::query()->where('type', 'journal.reopened')->exists())->toBeTrue();
});

it('starts and stops timers and lists the active timer', function (): void {
    $task = Task::factory()->inProgress()->create();
    $supervisor = User::factory()->inDepartment('workshop')->create();
    $employee = Employee::factory()->inDepartment('workshop')->create();
    $task->update(['claimed_by_user_id' => $supervisor->id]);
    Sanctum::actingAs($supervisor);

    $this->postJson("/api/v1/tasks/{$task->id}/timer/start", ['employee_id' => $employee->id])
        ->assertCreated();

    $this->getJson('/api/v1/timers/active')
        ->assertOk()
        ->assertJsonPath('data.employee.id', $employee->id);

    $this->postJson("/api/v1/tasks/{$task->id}/timer/stop")->assertOk();

    expect(TimeEntry::query()->where('task_id', $task->id)->whereNotNull('ended_at')->exists())->toBeTrue();
});

it('corrects a time entry with audit trail', function (): void {
    $entry = TimeEntry::factory()->create(['seconds' => 3600, 'ended_at' => now()]);
    $admin = User::factory()->inDepartment('management')->create();
    Sanctum::actingAs($admin);

    $this->patchJson("/api/v1/time-entries/{$entry->id}", [
        'seconds' => 7200,
        'note' => 'Forgot to stop timer',
    ])->assertOk()
        ->assertJsonPath('data.seconds', 7200);

    expect(AuditLog::query()->where('event', 'corrected')->exists())->toBeTrue();
});

it('creates and submits crew logs', function (): void {
    $supervisor = User::factory()->inDepartment('site')->create();
    $project = Project::factory()->create(['sales_user_id' => $supervisor->id]);
    $employee = Employee::factory()->inDepartment('site')->create();
    Sanctum::actingAs($supervisor);

    $create = $this->postJson("/api/v1/projects/{$project->id}/crew-logs", [
        'work_done' => 'Painted walls',
        'members' => [['employee_id' => $employee->id, 'hours' => 8]],
    ])->assertCreated();

    $logId = $create->json('data.id');

    $this->postJson("/api/v1/crew-logs/{$logId}/submit")->assertOk()
        ->assertJsonPath('data.status', 'submitted');
});

it('exposes visibility-filtered options and missing enums', function (): void {
    $user = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/options/employees')->assertOk();
    $this->getJson('/api/v1/options/projects')->assertOk();
    $this->getJson('/api/v1/enums/sample_status')->assertOk()
        ->assertJsonStructure(['data' => [['value', 'label']]]);
    $this->getJson('/api/v1/enums/project_status')->assertOk();
    $this->getJson('/api/v1/enums/attachment_type')->assertOk();
});

it('creates updates and completes projects with real hours data', function (): void {
    $sales = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($sales);

    $clientId = Project::factory()->create()->client_id;

    $created = $this->postJson('/api/v1/projects', [
        'name' => 'New tower',
        'client_id' => $clientId,
    ])->assertCreated();

    $projectId = $created->json('data.id');

    $patch = $this->patchJson("/api/v1/projects/{$projectId}", ['name' => 'Renamed tower']);
    $patch->assertOk();

    $this->getJson("/api/v1/projects/{$projectId}/hours")
        ->assertOk()
        ->assertJsonMissing(['data' => ['stub' => true]]);

    $manager = User::factory()->inDepartment('management')->create();
    Sanctum::actingAs($manager);

    $this->postJson("/api/v1/projects/{$projectId}/complete", ['completion_note' => 'Done'])
        ->assertOk()
        ->assertJsonPath('data.status', 'completed');
});

it('lists audit logs for authorized users', function (): void {
    AuditLog::query()->create([
        'auditable_type' => Project::class,
        'auditable_id' => 1,
        'event' => 'created',
        'created_at' => now(),
    ]);

    $admin = User::factory()->inDepartment('management')->create();
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/audit-logs')->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

it('loads requested relations on task detail', function (): void {
    $task = Task::factory()->ready()->create();
    $user = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($user);

    $this->getJson("/api/v1/tasks/{$task->id}?relations=department,comments")
        ->assertOk()
        ->assertJsonStructure(['data' => ['department', 'comments']]);
});

it('stops timers when blocking a task', function (): void {
    $task = Task::factory()->inProgress()->create();
    $user = User::factory()->create();
    $task->update(['claimed_by_user_id' => $user->id]);
    $category = BlockerCategory::factory()->create();

    TimeEntry::query()->create([
        'task_id' => $task->id,
        'user_id' => $user->id,
        'started_at' => now()->subHour(),
        'source' => 'timer',
    ]);

    app(TaskService::class)->block($task, $user, $category, 'Waiting on parts', null);

    expect(TimeEntry::query()->where('task_id', $task->id)->whereNotNull('ended_at')->exists())->toBeTrue();
});
