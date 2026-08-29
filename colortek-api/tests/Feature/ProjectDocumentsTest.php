<?php

declare(strict_types=1);

use App\Models\Attachment;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Sample;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
});

function projectViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole('management');
    Sanctum::actingAs($user);

    return $user;
}

it('returns an empty page for a project with no files', function (): void {
    projectViewer();
    $project = Project::factory()->create();

    $this->getJson("/api/v1/projects/{$project->id}/documents")
        ->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']]);
});

it('gathers attachments from tasks, samples, payments and site visits', function (): void {
    // Attachments are polymorphic and hang off the record they document, so the
    // project view has to collect them from every child rather than follow one key.
    projectViewer();
    $project = Project::factory()->create();

    $task = Task::factory()->create(['project_id' => $project->id]);
    $sample = Sample::factory()->create(['project_id' => $project->id]);
    $payment = Payment::factory()->create(['project_id' => $project->id]);
    $visit = SiteVisit::factory()->create(['project_id' => $project->id]);

    foreach ([$task, $sample, $payment, $visit] as $subject) {
        Attachment::factory()->create([
            'attachable_type' => $subject->getMorphClass(),
            'attachable_id' => $subject->id,
        ]);
    }

    $this->getJson("/api/v1/projects/{$project->id}/documents")
        ->assertOk()
        ->assertJsonCount(4, 'data');
});

it('does not leak files from another project', function (): void {
    projectViewer();
    $mine = Project::factory()->create();
    $theirs = Project::factory()->create();

    $myTask = Task::factory()->create(['project_id' => $mine->id]);
    $theirTask = Task::factory()->create(['project_id' => $theirs->id]);

    Attachment::factory()->create([
        'attachable_type' => $myTask->getMorphClass(),
        'attachable_id' => $myTask->id,
        'original_name' => 'mine.pdf',
    ]);
    Attachment::factory()->create([
        'attachable_type' => $theirTask->getMorphClass(),
        'attachable_id' => $theirTask->id,
        'original_name' => 'theirs.pdf',
    ]);

    $this->getJson("/api/v1/projects/{$mine->id}/documents")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.original_name', 'mine.pdf');
});

it('says where each file came from', function (): void {
    projectViewer();
    $project = Project::factory()->create();
    $sample = Sample::factory()->create(['project_id' => $project->id]);

    Attachment::factory()->create([
        'attachable_type' => $sample->getMorphClass(),
        'attachable_id' => $sample->id,
    ]);

    $this->getJson("/api/v1/projects/{$project->id}/documents")
        ->assertOk()
        ->assertJsonPath('data.0.source_type', 'Sample')
        ->assertJsonPath('data.0.source_id', $sample->id);
});

it('filters documents by type', function (): void {
    projectViewer();
    $project = Project::factory()->create();
    $task = Task::factory()->create(['project_id' => $project->id]);

    Attachment::factory()->create([
        'attachable_type' => $task->getMorphClass(),
        'attachable_id' => $task->id,
        'type' => 'payment_proof',
    ]);
    Attachment::factory()->create([
        'attachable_type' => $task->getMorphClass(),
        'attachable_id' => $task->id,
        'type' => 'site_photo',
    ]);

    $this->getJson("/api/v1/projects/{$project->id}/documents?type=payment_proof")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.type', 'payment_proof');
});

it('returns a friendly 404 for a project that does not exist', function (): void {
    projectViewer();

    $this->getJson('/api/v1/projects/999999/documents')->assertNotFound();
});
