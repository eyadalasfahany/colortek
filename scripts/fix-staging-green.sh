#!/usr/bin/env bash
set -euo pipefail
cd /workspace
git checkout -f cursor/staging-4e6f
git reset --hard origin/cursor/staging-4e6f
git checkout HEAD -- colortek-api/routes/api.php

API=colortek-api

# Remove duplicate helpers from SampleRequestFlowTest (shared in Support/SampleFlowHelpers.php)
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

# User::isSuperAdmin for admin tests
if ! grep -q isSuperAdmin "$API/app/Models/User.php"; then
  sed -i '/public function isSupervisorOf/,/^    }$/{
    /^    }$/a\
\
    public function isSuperAdmin(): bool\
    {\
        return $this->hasRole('\''super_admin'\'');\
    }
  }' "$API/app/Models/User.php"
fi

# Request type hints on phase5 controllers
for f in ProjectController NotificationController DashboardController SearchController; do
  sed -i 's/public function \([a-zA-Z]*\)(\$r/public function \1(Request $request/g' "$API/app/Http/Controllers/Api/V1/${f}.php"
  sed -i 's/\$r->/\$request->/g' "$API/app/Http/Controllers/Api/V1/${f}.php"
  if ! grep -q 'use Illuminate\\Http\\Request;' "$API/app/Http/Controllers/Api/V1/${f}.php"; then
    sed -i '/use App\\Http\\Controllers\\Controller;/a use Illuminate\\Http\\Request;' "$API/app/Http/Controllers/Api/V1/${f}.php"
  fi
done

cd "$API"
vendor/bin/pint
composer dump-autoload -o

echo "Fix script complete on branch: $(git -C /workspace branch --show-current)"
