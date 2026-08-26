<?php

declare(strict_types=1);

use App\Models\CrewLog;
use App\Models\CrewLogMember;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(ReferenceSeeder::class));

it('forbids people-hours without time.view_all', function (): void {
    $sales = User::factory()->inDepartment('sales')->create();
    Sanctum::actingAs($sales);

    $this->getJson('/api/v1/people-hours?from=2026-08-01&to=2026-08-26')
        ->assertForbidden();
});

it('returns workshop timers and site crew logs as separate sources', function (): void {
    $manager = User::factory()->inDepartment('management')->create();
    $workshopDept = Department::query()->where('code', 'workshop')->firstOrFail();
    $siteDept = Department::query()->where('code', 'site')->firstOrFail();

    $project = Project::factory()->create(['sales_user_id' => $manager->id, 'reference' => 'SO9577']);
    $workshopEmployee = Employee::factory()->create([
        'department_id' => $workshopDept->id,
        'name' => 'Workshop Worker',
    ]);
    $siteEmployee = Employee::factory()->create([
        'department_id' => $siteDept->id,
        'name' => 'Site Worker',
    ]);

    $task = Task::factory()->create([
        'project_id' => $project->id,
        'department_id' => $workshopDept->id,
    ]);

    TimeEntry::factory()->create([
        'task_id' => $task->id,
        'user_id' => $manager->id,
        'employee_id' => $workshopEmployee->id,
        'started_at' => '2026-08-10 09:00:00',
        'ended_at' => '2026-08-10 10:00:00',
        'seconds' => 3600,
    ]);

    $log = CrewLog::query()->create([
        'project_id' => $project->id,
        'log_date' => '2026-08-10',
        'supervisor_user_id' => $manager->id,
        'work_done' => 'Painted walls',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
    CrewLogMember::query()->create([
        'crew_log_id' => $log->id,
        'employee_id' => $siteEmployee->id,
        'hours' => 8,
    ]);

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/v1/people-hours?from=2026-08-01&to=2026-08-26')
        ->assertOk()
        ->assertJsonPath('data.from', '2026-08-01')
        ->assertJsonPath('data.to', '2026-08-26')
        ->assertJsonPath('data.workshop.source', 'timers')
        ->assertJsonPath('data.site.source', 'crew_logs');

    $workshop = $response->json('data.workshop');
    $site = $response->json('data.site');

    expect($workshop['by_project'])->toHaveCount(1)
        ->and($workshop['by_project'][0]['project_id'])->toBe($project->id)
        ->and($workshop['by_project'][0]['seconds'])->toBe(3600)
        ->and((float) $workshop['by_project'][0]['hours'])->toEqual(1.0)
        ->and($workshop['by_department'][0]['code'])->toBe('workshop')
        ->and($workshop['by_employee'][0]['employee_id'])->toBe($workshopEmployee->id)
        ->and($workshop['by_employee'][0]['seconds'])->toBe(3600)
        ->and($site['by_project'])->toHaveCount(1)
        ->and((float) $site['by_project'][0]['hours'])->toEqual(8.0)
        ->and($site['by_department'][0]['code'])->toBe('site')
        ->and($site['by_employee'][0]['employee_id'])->toBe($siteEmployee->id)
        ->and($site['by_employee'][0]['name'])->toBe('Site Worker')
        ->and((float) $site['by_employee'][0]['hours'])->toEqual(8.0)
        ->and($site['by_employee'][0])->not->toHaveKey('seconds');
});

it('filters people-hours by date range', function (): void {
    $manager = User::factory()->inDepartment('management')->create();
    $workshopDept = Department::query()->where('code', 'workshop')->firstOrFail();
    $siteDept = Department::query()->where('code', 'site')->firstOrFail();
    $project = Project::factory()->create(['sales_user_id' => $manager->id]);
    $workshopEmployee = Employee::factory()->create(['department_id' => $workshopDept->id]);
    $siteEmployee = Employee::factory()->create(['department_id' => $siteDept->id]);
    $task = Task::factory()->create([
        'project_id' => $project->id,
        'department_id' => $workshopDept->id,
    ]);

    TimeEntry::factory()->create([
        'task_id' => $task->id,
        'user_id' => $manager->id,
        'employee_id' => $workshopEmployee->id,
        'started_at' => '2026-08-05 09:00:00',
        'ended_at' => '2026-08-05 10:00:00',
        'seconds' => 3600,
    ]);
    TimeEntry::factory()->create([
        'task_id' => $task->id,
        'user_id' => $manager->id,
        'employee_id' => $workshopEmployee->id,
        'started_at' => '2026-07-20 09:00:00',
        'ended_at' => '2026-07-20 10:00:00',
        'seconds' => 7200,
    ]);

    $inRange = CrewLog::query()->create([
        'project_id' => $project->id,
        'log_date' => '2026-08-05',
        'supervisor_user_id' => $manager->id,
        'work_done' => 'In range',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
    CrewLogMember::query()->create([
        'crew_log_id' => $inRange->id,
        'employee_id' => $siteEmployee->id,
        'hours' => 4,
    ]);

    $outOfRange = CrewLog::query()->create([
        'project_id' => $project->id,
        'log_date' => '2026-07-15',
        'supervisor_user_id' => $manager->id,
        'work_done' => 'Out of range',
        'status' => 'submitted',
        'submitted_at' => now(),
    ]);
    CrewLogMember::query()->create([
        'crew_log_id' => $outOfRange->id,
        'employee_id' => $siteEmployee->id,
        'hours' => 8,
    ]);

    Sanctum::actingAs($manager);

    $response = $this->getJson('/api/v1/people-hours?from=2026-08-01&to=2026-08-31')
        ->assertOk();

    expect($response->json('data.workshop.by_employee.0.seconds'))->toBe(3600)
        ->and((float) $response->json('data.site.by_employee.0.hours'))->toEqual(4.0);
});
