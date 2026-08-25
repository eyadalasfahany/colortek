#!/bin/bash
set -euo pipefail
cd /workspace
git checkout -f cursor/phase4-site-visit-4e6f

python3 << 'PYEOF'
from pathlib import Path

p = Path('colortek-api/database/seeders/ReferenceSeeder.php')
text = p.read_text()
text = text.replace(
    """        foreach ($settings as $key => $value) {
            if ($value === null) {
                continue;
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'general'],
            );
        }""",
    """        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => 'general'],
            );
        }"""
)
p.write_text(text)

p = Path('colortek-api/app/Models/Setting.php')
p.write_text(p.read_text().replace("'array'", "'json'"))

p = Path('colortek-api/app/Services/Site/SiteVisitTaskHandler.php')
t = p.read_text()
if '$requested' not in t:
    t = t.replace(
        "$readiness = SiteReadiness::from((string) ($fields['readiness'] ?? SiteReadiness::NotReady->value));",
        "$requested = SiteReadiness::from((string) ($fields['readiness'] ?? SiteReadiness::NotReady->value));\n        $readiness = $requested;",
    ).replace(
        "if ($readiness === SiteReadiness::NotReady && empty($fields['summary']))",
        "if ($requested === SiteReadiness::NotReady && empty($fields['summary']))",
    )
    p.write_text(t)

p = Path('colortek-api/app/Services/Site/CorrectiveActionService.php')
p.write_text(p.read_text().replace("'instance_id' => $readinessTask->instance_id,", "'instance_id' => null,"))

p = Path('colortek-api/routes/api.php')
text = p.read_text()
for rem in [
    "use App\\Http\\Controllers\\Api\\V1\\ActivityController;\n",
    "use App\\Http\\Controllers\\Api\\V1\\StreamController;\n",
    "use App\\Http\\Middleware\\AuthenticateStream;\n",
    "    Route::get('stream', StreamController::class)->middleware(AuthenticateStream::class);\n\n",
    "        Route::get('activity', [ActivityController::class, 'index']);\n",
]:
    text = text.replace(rem, '')
p.write_text(text)

mig = Path('colortek-api/database/migrations/2026_08_26_260002_make_settings_value_nullable.php')
if not mig.exists():
    mig.write_text("""<?php
declare(strict_types=1);
use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('settings', fn (Blueprint $t) => $t->json('value')->nullable()->change()); }
    public function down(): void { Schema::table('settings', fn (Blueprint $t) => $t->json('value')->nullable(false)->change()); }
};
""")

p = Path('colortek-api/tests/Feature/SiteVisitFlowTest.php')
text = p.read_text()
old = """it('scenario 9: site held workshop ready', function (): void {
    [$instance, $siteDef, $workDef] = array_values(array_slice(seedSiteHoldWorkflow(true, false), 0, 3));
    $siteTask = $instance->tasks()->where('task_definition_id', $siteDef->id)->sole();
    $workTask = $instance->tasks()->where('task_definition_id', $workDef->id)->sole();
    ['visit' => $visit, 'task' => $task, 'engineer' => $engineer] = startSiteVisitFlow(); Sanctum::actingAs($engineer);"""
new = """it('scenario 9: site held workshop ready', function (): void {
    [$instance, $siteDef, $workDef, $project] = seedSiteHoldWorkflow(true, false);
    $siteTask = $instance->tasks()->where('task_definition_id', $siteDef->id)->sole();
    $workTask = $instance->tasks()->where('task_definition_id', $workDef->id)->sole();
    $engineer = siteEngineer();
    $result = app(SiteVisitService::class)->createForProject($project, $engineer);
    app(TaskService::class)->claim($result['task'], $engineer);
    app(TaskService::class)->start($result['task']->fresh(), $engineer);
    $visit = $result['visit'];
    $task = $result['task']->fresh(['definition']);
    Sanctum::actingAs($engineer);"""
if old in text:
    text = text.replace(old, new)
old10 = """it('scenario 10: block all holds workshop', function (): void {
    [$instance, , $workDef] = array_values(array_slice(seedSiteHoldWorkflow(true, true), 0, 3));
    $workTask = $instance->tasks()->where('task_definition_id', $workDef->id)->sole();
    ['visit' => $visit, 'task' => $task, 'engineer' => $engineer] = startSiteVisitFlow(); Sanctum::actingAs($engineer);"""
new10 = """it('scenario 10: block all holds workshop', function (): void {
    [$instance, , $workDef, $project] = seedSiteHoldWorkflow(true, true);
    $workTask = $instance->tasks()->where('task_definition_id', $workDef->id)->sole();
    $engineer = siteEngineer();
    $result = app(SiteVisitService::class)->createForProject($project, $engineer);
    app(TaskService::class)->claim($result['task'], $engineer);
    app(TaskService::class)->start($result['task']->fresh(), $engineer);
    $visit = $result['visit'];
    $task = $result['task']->fresh(['definition']);
    Sanctum::actingAs($engineer);"""
if old10 in text:
    text = text.replace(old10, new10)
p.write_text(text)
print('ok')
PYEOF

cd colortek-api && php artisan test --filter=SiteVisitFlowTest 2>&1 | tail -3
