<?php

declare(strict_types=1);

use App\Enums\TaskStatus;
use App\Models\Department;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use App\Services\Workflow\WorkflowEngine;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);

    $this->salesUser = User::factory()->inDepartment('sales')->create();
    $this->otherUser = User::factory()->inDepartment('sales')->create();
});

function seedEndpointTwoStepWorkflow(): array
{
    $user = auth()->user();
    $template = WorkflowTemplate::factory()->twoStep()->create();
    $project = Project::factory()->create(['sales_user_id' => $user?->id]);
    $instance = app(WorkflowEngine::class)->start($template, $project);
    $first = $instance->tasks()->sole();

    Task::query()->whereKey($first->id)->update([
        'status' => TaskStatus::InProgress,
        'claimed_by_user_id' => $user?->id,
        'claimed_at' => now(),
        'started_at' => now(),
    ]);

    return [$instance, $first->fresh(['claimant', 'definition'])];
}

function taskRequiringPaymentProof(): Task
{
    $sales = Department::query()->where('code', 'sales')->first();
    $user = User::factory()->inDepartment('sales')->create();
    $definition = WorkflowTaskDefinition::factory()->create([
        'required_attachment_types' => ['payment_proof'],
        'department_id' => $sales->id,
    ]);

    return Task::factory()->inProgress($user)->create([
        'task_definition_id' => $definition->id,
        'department_id' => $sales->id,
    ]);
}

it('returns a paginator with per_page 15 by default', function (): void {
    $department = Department::query()->where('code', 'sales')->first();
    Task::factory()->ready()->count(20)->create(['department_id' => $department->id]);

    Sanctum::actingAs($this->salesUser);

    $this->getJson('/api/v1/tasks?scope=queue')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonCount(15, 'data');
});

it('returns 409 when the task was already claimed', function (): void {
    $task = Task::factory()->claimed()->create();

    Sanctum::actingAs($this->otherUser);

    $this->postJson("/api/v1/tasks/{$task->id}/claim")
        ->assertStatus(409)
        ->assertJsonPath('code', 'task.already_claimed');
});

it('names the missing attachment instead of a generic error', function (): void {
    $task = taskRequiringPaymentProof();

    Sanctum::actingAs($task->claimant);

    $response = $this->postJson("/api/v1/tasks/{$task->id}/complete", ['fields' => []]);

    $response->assertStatus(422)
        ->assertJsonPath('code', 'task.missing_required_attachment');

    expect(strtolower($response->json('errors')['attachments.payment_proof'][0]))
        ->toContain('payment proof');
});

it('names the created successor in the completion response', function (): void {
    Sanctum::actingAs($this->salesUser);
    [$instance, $first] = seedEndpointTwoStepWorkflow();

    Sanctum::actingAs($first->claimant);

    $this->postJson("/api/v1/tasks/{$first->id}/complete", ['fields' => []])
        ->assertOk()
        ->assertJsonPath('meta.created_tasks.0.department', 'Reception');
});

it('hides tasks on projects the user cannot see', function (): void {
    $salesDept = Department::query()->where('code', 'sales')->first();
    $accountingDept = Department::query()->where('code', 'accounting')->first();
    $visibleProject = Project::factory()->create(['sales_user_id' => $this->salesUser->id]);
    $hiddenProject = Project::factory()->create();

    Task::factory()->ready()->create([
        'project_id' => $visibleProject->id,
        'department_id' => $salesDept->id,
    ]);
    Task::factory()->ready()->create([
        'project_id' => $hiddenProject->id,
        'department_id' => $accountingDept->id,
    ]);

    Sanctum::actingAs($this->salesUser);

    $response = $this->getJson('/api/v1/tasks');

    expect($response->json('meta.total'))->toBe(1);
});
