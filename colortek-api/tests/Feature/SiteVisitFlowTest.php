<?php

declare(strict_types=1);
use App\Enums\SiteReadiness;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Setting;
use App\Models\SiteMeasurement;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Site\SiteVisitService;
use App\Services\Tasks\TaskService;
use App\Services\Workflow\WorkflowEngine;
use Database\Seeders\ReferenceSeeder;
use Database\Seeders\SiteChecklistSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
});
function siteEngineer(): User
{
    return User::factory()->inDepartment('site')->create();
}
function startSiteVisitFlow(): array
{
    $project = Project::factory()->create(['site_ready' => true]);
    $engineer = siteEngineer();
    $result = app(SiteVisitService::class)->createForProject($project, $engineer);
    app(TaskService::class)->claim($result['task'], $engineer);
    app(TaskService::class)->start($result['task']->fresh(), $engineer);

    return ['project' => $project->fresh(), 'visit' => $result['visit'], 'task' => $result['task']->fresh(['definition']), 'engineer' => $engineer];
}
function allChecklistAnswers(bool $criticalPassed = true, float $humidity = 40): array
{
    return [
        ['code' => 'humidity', 'value' => $humidity, 'note' => 'n1'],
        ['code' => 'site_clear_of_other_workers', 'value' => $criticalPassed, 'note' => 'n2'],
        ['code' => 'site_clear_of_obstructions', 'value' => $criticalPassed, 'note' => 'n3'],
        ['code' => 'utilities_available', 'value' => $criticalPassed, 'note' => 'n4'],
        ['code' => 'overall_readiness', 'value' => 'ok', 'note' => 'n5'],
    ];
}
function uploadSignedScan(User $user): int
{
    Sanctum::actingAs($user);

    return (int) test()->postJson('/api/v1/attachments', ['file' => UploadedFile::fake()->create('signed.pdf', 100, 'application/pdf'), 'type' => 'site_report_signed'])->assertCreated()->json('data.id');
}
it('scenario 1: all five condition items and notes persist', function (): void {
    ['visit' => $visit, 'engineer' => $engineer] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/submit", ['answers' => allChecklistAnswers(), 'signed_attachment_id' => uploadSignedScan($engineer)])->assertOk();
    $response = $this->getJson("/api/v1/site-visits/{$visit->id}");
    expect($response->json('data.answers'))->toHaveCount(5);
});
it('scenario 2: critical no forces not_ready', function (): void {
    ['visit' => $visit, 'task' => $task, 'engineer' => $engineer, 'project' => $project] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/submit", ['answers' => allChecklistAnswers(false), 'signed_attachment_id' => uploadSignedScan($engineer)])->assertOk();
    app(TaskService::class)->complete($task->fresh(['definition']), $engineer, [], []);
    $readiness = Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'site_set_readiness'))->firstOrFail();
    app(TaskService::class)->claim($readiness, $engineer);
    app(TaskService::class)->start($readiness->fresh(), $engineer);
    app(TaskService::class)->complete($readiness->fresh(['definition']), $engineer, ['readiness' => 'ready', 'summary' => ''], []);
    expect($visit->fresh()->readiness)->toBe(SiteReadiness::NotReady)->and($project->fresh()->site_ready)->toBeFalse();
});
it('scenario 3: humidity warning only', function (): void {
    Setting::query()->where('key', 'humidity_max')->update(['value' => 50]);
    ['visit' => $visit, 'engineer' => $engineer] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $r = $this->postJson("/api/v1/site-visits/{$visit->id}/submit", ['answers' => allChecklistAnswers(humidity: 90), 'signed_attachment_id' => uploadSignedScan($engineer)])->assertOk();
    expect($r->json('meta.humidity_warning'))->toBeTrue();
});
it('scenario 4: addition sign stored', function (): void {
    ['visit' => $visit, 'engineer' => $engineer] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/measurements", ['rows' => [['element_name' => 'Wall', 'deductions' => [['sign' => 'add', 'count' => 1, 'length_m' => 1, 'width_m' => 1]]]]])->assertOk();
    expect(SiteMeasurement::first()?->deductions()->first()?->sign->value)->toBe('add');
});
it('scenario 5: count prefix stored', function (): void {
    ['visit' => $visit, 'engineer' => $engineer] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/measurements", ['rows' => [['element_name' => 'W', 'deductions' => [['count' => 3, 'length_m' => 0.69, 'width_m' => 0.68, 'sign' => 'subtract']]]]])->assertOk();
    expect(SiteMeasurement::first()?->deductions()->first()?->count)->toBe(3);
});
it('scenario 6: continuation rollup', function (): void {
    ['visit' => $visit, 'engineer' => $engineer] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/measurements", ['rows' => [['element_name' => 'A', 'sort_order' => 0], ['element_name' => '', 'sort_order' => 1]]])->assertOk();
    $rows = SiteMeasurement::query()->orderBy('sort_order')->get();
    expect($rows[1]->element_group_id)->toBe($rows[0]->element_group_id);
});
it('scenario 7: forty rows two pages', function (): void {
    ['visit' => $visit, 'engineer' => $engineer] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $rows = [];
    for ($i = 0; $i < 40; $i++) {
        $rows[] = ['page_number' => $i < 23 ? 1 : 2, 'line_number' => ($i % 23) + 1, 'element_name' => $i === 0 ? 'X' : '', 'sort_order' => $i];
    }
    $this->postJson("/api/v1/site-visits/{$visit->id}/measurements", ['rows' => $rows])->assertOk();
    $response = $this->getJson("/api/v1/site-visits/{$visit->id}");
    expect($response->json('data.measurements'))->toHaveCount(40);
});
it('scenario 8: submit without scan rejected', function (): void {
    ['visit' => $visit, 'engineer' => $engineer] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/submit", ['answers' => allChecklistAnswers()])->assertUnprocessable();
});
it('scenario 9: site held workshop ready', function (): void {
    [$instance, $siteDef, $workDef, $project] = seedSiteHoldWorkflow(true, false);
    $siteTask = $instance->tasks()->where('task_definition_id', $siteDef->id)->sole();
    $workTask = $instance->tasks()->where('task_definition_id', $workDef->id)->sole();
    $engineer = siteEngineer();
    $result = app(SiteVisitService::class)->createForProject($project, $engineer);
    app(TaskService::class)->claim($result['task'], $engineer);
    app(TaskService::class)->start($result['task']->fresh(), $engineer);
    $visit = $result['visit'];
    $task = $result['task']->fresh(['definition']);
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/submit", ['answers' => allChecklistAnswers(false), 'signed_attachment_id' => uploadSignedScan($engineer)]);
    app(TaskService::class)->complete($task->fresh(['definition']), $engineer, [], []);
    $readiness = Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'site_set_readiness'))->firstOrFail();
    app(TaskService::class)->claim($readiness, $engineer);
    app(TaskService::class)->start($readiness->fresh(), $engineer);
    app(TaskService::class)->complete($readiness->fresh(['definition']), $engineer, ['readiness' => 'not_ready', 'summary' => 'x'], []);
    expect($siteTask->fresh()->status)->toBe(TaskStatus::Pending)->and($workTask->fresh()->status)->toBe(TaskStatus::Ready);
});
it('scenario 10: block all holds workshop', function (): void {
    [$instance, , $workDef, $project] = seedSiteHoldWorkflow(true, true);
    $workTask = $instance->tasks()->where('task_definition_id', $workDef->id)->sole();
    $engineer = siteEngineer();
    $result = app(SiteVisitService::class)->createForProject($project, $engineer);
    app(TaskService::class)->claim($result['task'], $engineer);
    app(TaskService::class)->start($result['task']->fresh(), $engineer);
    $visit = $result['visit'];
    $task = $result['task']->fresh(['definition']);
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/submit", ['answers' => allChecklistAnswers(false), 'signed_attachment_id' => uploadSignedScan($engineer)]);
    app(TaskService::class)->complete($task->fresh(['definition']), $engineer, [], []);
    $readiness = Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'site_set_readiness'))->firstOrFail();
    app(TaskService::class)->claim($readiness, $engineer);
    app(TaskService::class)->start($readiness->fresh(), $engineer);
    app(TaskService::class)->complete($readiness->fresh(['definition']), $engineer, ['readiness' => 'not_ready', 'summary' => 'x'], []);
    expect($workTask->fresh()->status)->toBe(TaskStatus::Pending);
});
it('scenario 11: override releases one task', function (): void {
    [$instance, $siteDef, , $project] = seedSiteHoldWorkflow(true, false);
    $siteTask = $instance->tasks()->where('task_definition_id', $siteDef->id)->sole();
    $siteTask->update(['project_id' => $project->id, 'status' => TaskStatus::Pending]);
    $manager = User::factory()->create();
    $manager->assignRole('management');
    Sanctum::actingAs($manager);
    $this->postJson("/api/v1/tasks/{$siteTask->id}/override-site-block", ['reason' => 'approved'])->assertOk();
    expect($siteTask->fresh()->status)->toBe(TaskStatus::Ready)->and(AuditLog::query()->where('event', 'override')->exists())->toBeTrue();
});
it('scenario 12: ready releases held tasks', function (): void {
    [$instance, $siteDef, , $project] = seedSiteHoldWorkflow(false, false);
    $siteTask = $instance->tasks()->where('task_definition_id', $siteDef->id)->sole();
    $siteTask->update(['status' => TaskStatus::Pending]);
    $project->update(['site_ready' => true]);
    app(WorkflowEngine::class)->releaseSiteHeldTasks($project->fresh());
    expect($siteTask->fresh()->status)->toBe(TaskStatus::Ready);
});
it('scenario 13: client corrective in sales queue', function (): void {
    ['visit' => $visit, 'task' => $task, 'engineer' => $engineer] = startSiteVisitFlow();
    Sanctum::actingAs($engineer);
    $this->postJson("/api/v1/site-visits/{$visit->id}/submit", ['answers' => allChecklistAnswers(false), 'signed_attachment_id' => uploadSignedScan($engineer)]);
    app(TaskService::class)->complete($task->fresh(['definition']), $engineer, [], []);
    $readiness = Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'site_set_readiness'))->firstOrFail();
    app(TaskService::class)->claim($readiness, $engineer);
    app(TaskService::class)->start($readiness->fresh(), $engineer);
    app(TaskService::class)->complete($readiness->fresh(['definition']), $engineer, ['readiness' => 'not_ready', 'summary' => 'x'], []);
    expect(Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'corrective_action_task'))->firstOrFail()->department->code)->toBe('sales');
});
it('scenario 14: Arabic checklist labels from API', function (): void {
    $this->seed(SiteChecklistSeeder::class);
    Sanctum::actingAs(siteEngineer());
    $response = $this->getJson('/api/v1/site-checklist-items');
    $labels = collect($response->json('data'))->pluck('label_ar');
    expect($labels)->toContain('نسبة الرطوبة بالموقع');
    expect(WorkflowTemplate::query()->where('code', 'site_visit')->where('is_active', true)->exists())->toBeTrue();
});
