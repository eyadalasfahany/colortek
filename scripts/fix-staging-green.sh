#!/usr/bin/env bash
set -euo pipefail
cd /workspace
API=colortek-api
git checkout HEAD -- colortek-api/routes/api.php 2>/dev/null || true

python3 <<'PY'
from pathlib import Path
p = Path("colortek-api/tests/Feature/SampleRequestFlowTest.php")
text = p.read_text()
blocks = [
    """function sampleSalesFields(Project $project): array
{
    return [
        'client_id' => $project->client_id,
        'project_id' => $project->id,
        'color' => 'warm sand',
        'texture' => 'fine',
        'size' => 'A4',
    ];
}

function completeSampleTask(Task $task, User $user, array $fields = [], array $attachmentIds = []): void
{
    app(TaskService::class)->claim($task, $user);
    app(TaskService::class)->start($task->fresh(), $user);
    app(TaskService::class)->complete($task->fresh(), $user, $fields, $attachmentIds);
}

function sampleTaskFor(Sample $sample, string $code, TaskStatus $status = TaskStatus::Ready): Task
{
    return Task::query()
        ->where('subject_id', $sample->id)
        ->whereHas('definition', fn ($q) => $q->where('code', $code))
        ->where('status', $status)
        ->firstOrFail();
}

function uploadAttachment(User $user, string $type): int
{
    Sanctum::actingAs($user);
    $response = test()->postJson('/api/v1/attachments', [
        'file' => UploadedFile::fake()->create("{$type}.pdf", 100, 'application/pdf'),
        'type' => $type,
    ])->assertCreated();

    return (int) $response->json('data.id');
}

""",
    """function advanceSampleToWorkshop(Sample $sample, User $sales): Sample
{
    $sample = $sample->fresh();
    completeSampleTask(sampleTaskFor($sample, 'sales_create_sample_request'), $sales, sampleSalesFields($sample->project));

    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask(sampleTaskFor($sample->fresh(), 'reception_review_sample_request'), $reception, ['review_result' => 'forward']);

    $manager = User::factory()->inDepartment('management')->create();
    completeSampleTask(sampleTaskFor($sample->fresh(), 'manager_approve_sample'), $manager, ['decision' => 'approved']);

    return $sample->fresh();
}

function advanceSampleToClientDecision(Project $project, User $sales): Sample
{
    $result = app(SampleService::class)->start(sampleSalesFields($project), $sales);
    $sample = advanceSampleToWorkshop($result['sample'], $sales);

    $employee = Employee::factory()->inDepartment('tinting')->create();
    $tinting = User::factory()->inDepartment('tinting')->create();
    completeSampleTask(sampleTaskFor($sample, 'tinting_author_formula'), $tinting, [
        'body' => 'Tint mix 1:2',
        'author_employee_id' => $employee->id,
        'authored_at' => '2026-08-20',
    ]);

    $workshop = User::factory()->inDepartment('workshop')->create();
    $photoId = Attachment::factory()->samplePhoto()->create(['uploaded_by_user_id' => $workshop->id])->id;
    completeSampleTask(sampleTaskFor($sample->fresh(), 'workshop_make_sample'), $workshop, [
        'ready_for_registration' => true,
    ], ['sample_photo' => [$photoId]]);

    $reception = User::factory()->inDepartment('reception')->create();
    completeSampleTask(sampleTaskFor($sample->fresh(), 'reception_register_formula'), $reception, [
        'confirm_matches_sheet' => true,
    ]);

    return $sample->fresh();
}
""",
]
for block in blocks:
    text = text.replace(block, "")
text = text.replace("use Illuminate\\Http\\UploadedFile;\n", "")
p.write_text(text)
PY

python3 <<'PY'
from pathlib import Path
p = Path("colortek-api/app/Models/User.php")
text = p.read_text()
if "isSuperAdmin" not in text:
    text = text.replace(
        "    public function isSupervisorOf(Department $department): bool\n    {\n        return $this->departments()\n            ->wherePivot('department_id', $department->id)\n            ->wherePivot('is_supervisor', true)\n            ->exists();\n    }\n}",
        "    public function isSupervisorOf(Department $department): bool\n    {\n        return $this->departments()\n            ->wherePivot('department_id', $department->id)\n            ->wherePivot('is_supervisor', true)\n            ->exists();\n    }\n\n    public function isSuperAdmin(): bool\n    {\n        return $this->hasRole('super_admin');\n    }\n}",
    )
    p.write_text(text)
PY

for f in ProjectController NotificationController DashboardController SearchController; do
  file="$API/app/Http/Controllers/Api/V1/${f}.php"
  sed -i 's/public function \([a-zA-Z]*\)(\$r/public function \1(Request $request/g' "$file"
  sed -i 's/\$r->/\$request->/g' "$file"
  grep -q 'use Illuminate\\Http\\Request;' "$file" || sed -i '/use App\\Http\\Controllers\\Controller;/a use Illuminate\\Http\\Request;' "$file"
done

cd "$API" && vendor/bin/pint && composer dump-autoload -o
