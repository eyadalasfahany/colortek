<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

it('lists every active user by default', function (): void {
    Sanctum::actingAs(User::factory()->inDepartment('management')->create());
    User::factory()->count(3)->create();

    $response = $this->getJson('/api/v1/options/users')->assertOk();

    expect($response->json('data'))->toHaveCount(4);
});

it('narrows user options to one department', function (): void {
    // Reassigning a workshop task should only offer workshop people.
    Sanctum::actingAs(User::factory()->inDepartment('management')->create());
    $workshop = User::factory()->inDepartment('workshop')->create(['name' => 'Waleed Workshop']);
    User::factory()->inDepartment('sales')->create();

    $departmentId = Department::query()->where('code', 'workshop')->value('id');

    $response = $this->getJson("/api/v1/options/users?department_id={$departmentId}")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.name'))->toBe($workshop->name);
});

it('excludes inactive users from the options list', function (): void {
    Sanctum::actingAs(User::factory()->inDepartment('management')->create());
    User::factory()->create(['active' => false]);

    $response = $this->getJson('/api/v1/options/users')->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});

it('reassigns a task to another user', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('management');
    Sanctum::actingAs($manager);

    $assignee = User::factory()->inDepartment('workshop')->create();
    $task = Task::factory()->claimed()->create();

    $this->postJson("/api/v1/tasks/{$task->id}/reassign", [
        'assignee_user_id' => $assignee->id,
    ])->assertOk();

    expect($task->fresh()->claimed_by_user_id)->toBe($assignee->id);
});

it('rejects a reassignment to a user that does not exist', function (): void {
    $manager = User::factory()->create();
    $manager->assignRole('management');
    Sanctum::actingAs($manager);
    $task = Task::factory()->claimed()->create();

    $this->postJson("/api/v1/tasks/{$task->id}/reassign", ['assignee_user_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('assignee_user_id');
});

it('forbids reassignment without task.reassign', function (): void {
    Sanctum::actingAs(User::factory()->inDepartment('workshop')->create());
    $assignee = User::factory()->create();
    $task = Task::factory()->claimed()->create();

    $this->postJson("/api/v1/tasks/{$task->id}/reassign", ['assignee_user_id' => $assignee->id])
        ->assertForbidden();
});
