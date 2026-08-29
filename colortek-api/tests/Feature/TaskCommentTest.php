<?php

declare(strict_types=1);

use App\Models\Task;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

function commentableTask(): array
{
    $user = User::factory()->inDepartment('sales')->create();
    $task = Task::factory()->create(['department_id' => $user->departments->first()->id]);
    Sanctum::actingAs($user);

    return [$task, $user];
}

it('stores a comment', function (): void {
    [$task] = commentableTask();

    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Waiting on the client.'])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Waiting on the client.');

    $this->assertDatabaseHas('task_comments', [
        'task_id' => $task->id,
        'body' => 'Waiting on the client.',
    ]);
});

it('rejects an empty comment', function (): void {
    [$task] = commentableTask();

    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('body');
});

it('omits comments when the relation is not requested', function (): void {
    // Documents the contract the frontend has to satisfy: relations are opt-in,
    // so a screen that does not ask for them sees nothing.
    [$task] = commentableTask();
    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Hidden.'])->assertCreated();

    $response = $this->getJson("/api/v1/tasks/{$task->id}")->assertOk();

    expect($response->json('data'))->not->toHaveKey('comments');
});

it('returns comments with their author when the relation is requested', function (): void {
    // Regression: comments saved fine but the task screen never asked for them,
    // so they vanished on reload and looked like failed writes.
    [$task, $user] = commentableTask();
    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'First.'])->assertCreated();
    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Second.'])->assertCreated();

    $this->getJson("/api/v1/tasks/{$task->id}?relations=comments,comments.user")
        ->assertOk()
        ->assertJsonCount(2, 'data.comments')
        ->assertJsonPath('data.comments.0.body', 'First.')
        ->assertJsonPath('data.comments.0.user.name', $user->name);
});

it('returns every relation the task screen asks for', function (): void {
    [$task] = commentableTask();
    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Note.'])->assertCreated();

    // Mirrors TASK_DETAIL_RELATIONS in the frontend taskService.
    $relations = 'department,claimant,project,definition,subject,comments,comments.user,blockerCategory';

    $this->getJson("/api/v1/tasks/{$task->id}?relations={$relations}")
        ->assertOk()
        ->assertJsonCount(1, 'data.comments');
});

it('refuses a comment from a user who cannot comment', function (): void {
    $task = Task::factory()->create();
    $user = User::factory()->create();
    $user->assignRole('viewer');
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/tasks/{$task->id}/comments", ['body' => 'Nope.'])
        ->assertForbidden();
});
