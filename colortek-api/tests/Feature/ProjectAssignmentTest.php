<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * `specs/03-data-model.md` "Projects": `sales_user_id` (the salesperson who
 * owns the client relationship) and `responsible_user_id` (project manager
 * once execution starts) are both real columns, but `responsible_user_id` had
 * no model relation, was never returned by ProjectResource, and every fresh()
 * call after an update dropped it — so it could be set and would vanish.
 * `sales_user_id` could not be changed after creation at all.
 */
beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

function projectManager(): User
{
    $user = User::factory()->create();
    $user->assignRole('management');
    Sanctum::actingAs($user);

    return $user;
}

it('sets the responsible user when creating a project', function (): void {
    $sales = projectManager();
    $client = Client::factory()->create();
    $engineer = User::factory()->inDepartment('site')->create();

    $response = $this->postJson('/api/v1/projects', [
        'name' => 'Omega — Lobby',
        'client_id' => $client->id,
        'responsible_user_id' => $engineer->id,
    ])->assertCreated();

    expect($response->json('data.responsible_user.id'))->toBe($engineer->id)
        ->and($response->json('data.responsible_user.name'))->toBe($engineer->name)
        ->and($response->json('data.sales_user.id'))->toBe($sales->id);
});

it('assigns a responsible user to an existing project and keeps it on read', function (): void {
    projectManager();
    $project = Project::factory()->create();
    $engineer = User::factory()->inDepartment('site')->create();

    $this->patchJson("/api/v1/projects/{$project->id}", ['responsible_user_id' => $engineer->id])
        ->assertOk()
        ->assertJsonPath('data.responsible_user.id', $engineer->id);

    // Regression: fresh(['client', 'salesUser']) dropped responsibleUser, so a
    // second read after the same request could show it missing again.
    $this->getJson("/api/v1/projects/{$project->id}?relations=responsibleUser")
        ->assertOk()
        ->assertJsonPath('data.responsible_user.id', $engineer->id);
});

it('reassigns the salesperson on an existing project', function (): void {
    projectManager();
    $project = Project::factory()->create();
    $newSales = User::factory()->inDepartment('sales')->create();

    $this->patchJson("/api/v1/projects/{$project->id}", ['sales_user_id' => $newSales->id])
        ->assertOk()
        ->assertJsonPath('data.sales_user.id', $newSales->id);

    expect($project->fresh()->sales_user_id)->toBe($newSales->id);
});

it('clears the responsible user by sending null', function (): void {
    projectManager();
    $engineer = User::factory()->inDepartment('site')->create();
    $project = Project::factory()->create(['responsible_user_id' => $engineer->id]);

    $this->patchJson("/api/v1/projects/{$project->id}", ['responsible_user_id' => null])
        ->assertOk()
        ->assertJsonPath('data.responsible_user', null);
});

it('forbids assigning a project without project.update', function (): void {
    $user = User::factory()->inDepartment('workshop')->create();
    Sanctum::actingAs($user);
    $project = Project::factory()->create();
    $engineer = User::factory()->create();

    $this->patchJson("/api/v1/projects/{$project->id}", ['responsible_user_id' => $engineer->id])
        ->assertForbidden();
});
