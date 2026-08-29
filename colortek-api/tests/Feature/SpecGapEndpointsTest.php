<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use Database\Seeders\ReferenceSeeder;
use Database\Seeders\SiteVisitWorkflowSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * Covers the two endpoints `specs/08-api-contract.md` declares that the code
 * did not have: creating a corrective action and creating a workflow template.
 */
beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

function siteEngineer(): User
{
    $user = User::factory()->create();
    $user->assignRole('site_engineer');
    Sanctum::actingAs($user);

    return $user;
}

it('raises a corrective action by hand', function (): void {
    $this->seed(SiteVisitWorkflowSeeder::class);
    siteEngineer();
    $visit = SiteVisit::factory()->create();

    $this->postJson('/api/v1/corrective-actions', [
        'site_visit_id' => $visit->id,
        'description' => 'Other trades still on site.',
        'responsible_party' => 'client',
    ])
        ->assertCreated()
        ->assertJsonPath('data.description', 'Other trades still on site.')
        ->assertJsonPath('data.status', 'open');

    $this->assertDatabaseHas('corrective_actions', ['site_visit_id' => $visit->id]);
});

it('routes a client corrective action to sales', function (): void {
    // 07-workflows/05: client, contractor and other_trade all go to Sales,
    // because Sales is who talks to the client.
    $this->seed(SiteVisitWorkflowSeeder::class);
    siteEngineer();
    $visit = SiteVisit::factory()->create();

    $response = $this->postJson('/api/v1/corrective-actions', [
        'site_visit_id' => $visit->id,
        'description' => 'Client to clear furniture.',
        'responsible_party' => 'client',
    ])->assertCreated();

    $task = Task::query()->where('subject_id', $response->json('data.id'))->firstOrFail();
    $sales = Department::query()->where('code', 'sales')->value('id');

    expect($task->department_id)->toBe($sales)
        ->and($task->status->value)->toBe('ready');
});

it('routes a colortek corrective action to the named department', function (): void {
    $this->seed(SiteVisitWorkflowSeeder::class);
    siteEngineer();
    $visit = SiteVisit::factory()->create();
    $workshop = Department::query()->where('code', 'workshop')->value('id');

    $response = $this->postJson('/api/v1/corrective-actions', [
        'site_visit_id' => $visit->id,
        'description' => 'Re-tint the batch.',
        'responsible_party' => 'colortek',
        'department_id' => $workshop,
    ])->assertCreated();

    $task = Task::query()->where('subject_id', $response->json('data.id'))->firstOrFail();

    expect($task->department_id)->toBe($workshop);
});

it('rejects an unknown responsible party', function (): void {
    siteEngineer();
    $visit = SiteVisit::factory()->create();

    $this->postJson('/api/v1/corrective-actions', [
        'site_visit_id' => $visit->id,
        'description' => 'Something.',
        'responsible_party' => 'nobody',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('responsible_party');
});

it('forbids raising a corrective action without the permission', function (): void {
    $user = User::factory()->create();
    $user->assignRole('viewer');
    Sanctum::actingAs($user);
    $visit = SiteVisit::factory()->create();

    $this->postJson('/api/v1/corrective-actions', [
        'site_visit_id' => $visit->id,
        'description' => 'Nope.',
        'responsible_party' => 'client',
    ])->assertForbidden();
});

it('creates a workflow template as an unpublished version 1', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/workflow-templates', [
        'code' => 'delivery_handover',
        'name_en' => 'Delivery handover',
        'name_ar' => 'تسليم',
        'scope' => 'project',
    ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'delivery_handover')
        ->assertJsonPath('data.version', 1);

    $template = WorkflowTemplate::query()->where('code', 'delivery_handover')->firstOrFail();

    // Publishing is a separate audited step, so a new template starts as a draft.
    expect($template->published_at)->toBeNull()
        ->and($template->is_active)->toBeFalse();
});

it('rejects a duplicate template code', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    Sanctum::actingAs($admin);
    WorkflowTemplate::factory()->create(['code' => 'taken']);

    $this->postJson('/api/v1/admin/workflow-templates', [
        'code' => 'taken',
        'name_en' => 'Dup',
        'name_ar' => 'مكرر',
        'scope' => 'project',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('code');
});

it('hides template creation from a user without workflow.manage', function (): void {
    Sanctum::actingAs(User::factory()->inDepartment('sales')->create());

    $this->postJson('/api/v1/admin/workflow-templates', [
        'code' => 'nope',
        'name_en' => 'Nope',
        'name_ar' => 'لا',
        'scope' => 'project',
    ])->assertNotFound();
});
