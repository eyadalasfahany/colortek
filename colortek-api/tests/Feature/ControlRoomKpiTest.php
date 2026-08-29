<?php

declare(strict_types=1);

use App\Models\CrewLog;
use App\Models\CrewLogMember;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Sample;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

/**
 * `specs/09-screens/01-control-room.md` "KPI row": three of the seven tiles
 * (awaiting approval, working now, on site today) were hardcoded to 0, and
 * "sites not ready" used the project's cached flag instead of the latest visit.
 */
beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
    $manager = User::factory()->create();
    $manager->assignRole('management');
    Sanctum::actingAs($manager);
});

function manageApprovalTask(Project $project): Task
{
    // ReferenceSeeder does not seed workflow templates; reuse sample_request if
    // a prior test in the run already created it, otherwise make it fresh.
    $template = WorkflowTemplate::query()->firstOrCreate(
        ['code' => 'sample_request', 'version' => 1],
        WorkflowTemplate::factory()->make(['code' => 'sample_request'])->toArray(),
    );
    $definition = WorkflowTaskDefinition::query()->firstOrCreate(
        ['template_id' => $template->id, 'code' => 'manager_approve_sample'],
        WorkflowTaskDefinition::factory()->make()->toArray(),
    );
    $sample = Sample::factory()->create(['project_id' => $project->id]);

    return Task::factory()->create([
        'project_id' => $project->id,
        'task_definition_id' => $definition->id,
        'subject_type' => $sample->getMorphClass(),
        'subject_id' => $sample->id,
        'status' => 'ready',
    ]);
}

it('counts open manager_approve_sample tasks as awaiting approval', function (): void {
    $project = Project::factory()->create(['status' => 'active']);
    manageApprovalTask($project);
    Task::factory()->create(['project_id' => $project->id, 'status' => 'ready']);

    $response = $this->getJson('/api/v1/dashboard/control-room')->assertOk();
    $kpi = collect($response->json('data.kpis'))->firstWhere('key', 'awaiting_approval');

    expect($kpi['count'])->toBe(1);
});

it('bases sites-not-ready on the latest visit, not the stale flag', function (): void {
    $project = Project::factory()->create(['status' => 'active', 'site_ready' => true]);
    SiteVisit::factory()->create([
        'project_id' => $project->id,
        'visit_number' => 1,
        'readiness' => 'ready',
        'visited_on' => now()->subDays(5)->toDateString(),
    ]);
    SiteVisit::factory()->create([
        'project_id' => $project->id,
        'visit_number' => 2,
        'readiness' => 'not_ready',
        'visited_on' => now()->toDateString(),
    ]);

    $response = $this->getJson('/api/v1/dashboard/control-room')->assertOk();
    $kpi = collect($response->json('data.kpis'))->firstWhere('key', 'sites_not_ready');

    expect($kpi['count'])->toBe(1);
});

it('counts a running timer on a workshop task as working now', function (): void {
    $workshop = Department::query()->where('code', 'workshop')->firstOrFail();
    $project = Project::factory()->create(['status' => 'active']);
    $task = Task::factory()->create(['project_id' => $project->id, 'department_id' => $workshop->id]);
    TimeEntry::factory()->create(['task_id' => $task->id, 'ended_at' => null]);

    // A closed timer on a non-workshop department must not count.
    $sales = Department::query()->where('code', 'sales')->firstOrFail();
    $otherTask = Task::factory()->create(['project_id' => $project->id, 'department_id' => $sales->id]);
    TimeEntry::factory()->create(['task_id' => $otherTask->id, 'ended_at' => now()]);

    $response = $this->getJson('/api/v1/dashboard/control-room')->assertOk();
    $kpi = collect($response->json('data.kpis'))->firstWhere('key', 'workshop_timers');

    expect($kpi['count'])->toBe(1);
});

it('counts employees from submitted crew logs filed today as on site', function (): void {
    $project = Project::factory()->create(['status' => 'active']);
    $otherProject = Project::factory()->create(['status' => 'active']);
    $log = CrewLog::query()->create([
        'project_id' => $project->id,
        'log_date' => now()->toDateString(),
        'supervisor_user_id' => User::factory()->create()->id,
        'work_done' => 'Painted the lobby walls.',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
    CrewLogMember::query()->create([
        'crew_log_id' => $log->id,
        'employee_id' => Employee::factory()->create()->id,
        'hours' => 8,
    ]);
    CrewLogMember::query()->create([
        'crew_log_id' => $log->id,
        'employee_id' => Employee::factory()->create()->id,
        'hours' => 8,
    ]);

    // A draft log (not yet submitted) must not count.
    $draft = CrewLog::query()->create([
        'project_id' => $otherProject->id,
        'log_date' => now()->toDateString(),
        'supervisor_user_id' => User::factory()->create()->id,
        'work_done' => 'Still in progress.',
        'status' => 'draft',
    ]);
    CrewLogMember::query()->create([
        'crew_log_id' => $draft->id,
        'employee_id' => Employee::factory()->create()->id,
        'hours' => 8,
    ]);

    $response = $this->getJson('/api/v1/dashboard/control-room')->assertOk();
    $kpi = collect($response->json('data.kpis'))->firstWhere('key', 'on_site_today');

    expect($kpi['count'])->toBe(2);
});

it('lists waiting_approval tasks in needs_attention, not an empty stub', function (): void {
    $project = Project::factory()->create(['status' => 'active']);
    manageApprovalTask($project);

    $response = $this->getJson('/api/v1/dashboard/control-room')->assertOk();

    expect($response->json('data.needs_attention.waiting_approval'))->toHaveCount(1);
});
