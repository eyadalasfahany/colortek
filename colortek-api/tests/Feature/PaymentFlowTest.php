<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Attachment;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Payments\PaymentService;
use App\Services\Tasks\TaskService;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $this->seed(ReferenceSeeder::class);
    Storage::fake('local');
});

/**
 * @return array{project: Project, payment: Payment, salesTask: Task, sales: User}
 */
function startPaymentFlow(int $installmentNumber = 1): array
{
    $project = Project::factory()->create(['stage' => 'lead']);
    $sales = User::factory()->inDepartment('sales')->create();

    $result = app(PaymentService::class)->startForProject($project, $installmentNumber, $sales);

    app(TaskService::class)->claim($result['task'], $sales);
    app(TaskService::class)->start($result['task']->fresh(), $sales);

    return [
        'project' => $project->fresh(['quotation', 'client']),
        'payment' => $result['payment'],
        'salesTask' => $result['task']->fresh(['definition', 'department']),
        'sales' => $sales,
    ];
}

function uploadPaymentProof(User $user): int
{
    Sanctum::actingAs($user);

    $response = test()->postJson('/api/v1/attachments', [
        'file' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
        'type' => 'payment_proof',
    ])->assertCreated();

    return (int) $response->json('data.id');
}

/** @return array<string, mixed> */
function salesConfirmFields(int $installment = 1, bool $quotationLocked = true): array
{
    return [
        'installment_number' => $installment,
        'amount' => 50000,
        'method' => 'bank_transfer',
        'paid_at' => '2026-08-20',
        'quotation_locked' => $quotationLocked,
        'notes' => 'First installment',
    ];
}

it('seeds the payment_cycle workflow template', function (): void {
    expect(
        WorkflowTemplate::query()
            ->where('code', 'payment_cycle')
            ->where('is_active', true)
            ->exists(),
    )->toBeTrue();
});

it('scenario 1: sales cannot complete without a proof file', function (): void {
    ['salesTask' => $task, 'sales' => $sales] = startPaymentFlow();

    Sanctum::actingAs($sales);

    $this->postJson("/api/v1/tasks/{$task->id}/complete", [
        'fields' => salesConfirmFields(),
        'attachment_ids' => [],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'task.missing_required_attachment');
});

it('scenario 2: sales cannot complete with quotation_locked false', function (): void {
    ['salesTask' => $task, 'sales' => $sales] = startPaymentFlow();
    $proofId = uploadPaymentProof($sales);

    Sanctum::actingAs($sales);

    $this->postJson("/api/v1/tasks/{$task->id}/complete", [
        'fields' => salesConfirmFields(quotationLocked: false),
        'attachment_ids' => [$proofId],
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'task.missing_required_field');
});

it('scenario 3: reception task appears carrying payment details without re-entry', function (): void {
    ['salesTask' => $task, 'sales' => $sales, 'project' => $project, 'payment' => $payment] = startPaymentFlow();
    $proofId = uploadPaymentProof($sales);

    Sanctum::actingAs($sales);

    $completeResponse = $this->postJson("/api/v1/tasks/{$task->id}/complete", [
        'fields' => salesConfirmFields(),
        'attachment_ids' => ['payment_proof' => [$proofId]],
    ])->assertOk();

    $receptionTaskId = $completeResponse->json('meta.created_tasks.0.id');
    expect($receptionTaskId)->not->toBeNull();

    $reception = User::factory()->inDepartment('reception')->create();
    Sanctum::actingAs($reception);

    $showResponse = $this->getJson("/api/v1/tasks/{$receptionTaskId}")
        ->assertOk();

    expect($showResponse->json('data.subject.amount'))->toBe('50000.00')
        ->and($showResponse->json('data.subject.method'))->toBe('bank_transfer')
        ->and($showResponse->json('data.subject.salesperson.name'))->toBe($project->salesUser?->name)
        ->and($showResponse->json('data.subject.attachments.0.id'))->toBe($proofId)
        ->and($showResponse->json('data.form_schema.fields'))->not->toBeEmpty();

    expect($payment->fresh()->status)->toBe(PaymentStatus::Confirmed)
        ->and($project->fresh()->stage->value)->toBe('payment');
});

it('scenario 4: reception accepted attaches the payment to today\'s journal', function (): void {
    ['salesTask' => $salesTask, 'sales' => $sales, 'payment' => $payment] = startPaymentFlow();
    $proofId = uploadPaymentProof($sales);

    Sanctum::actingAs($sales);
    $this->postJson("/api/v1/tasks/{$salesTask->id}/complete", [
        'fields' => salesConfirmFields(),
        'attachment_ids' => [$proofId],
    ])->assertOk();

    $receptionTask = Task::query()
        ->whereHas('definition', fn ($q) => $q->where('code', 'reception_review_payment'))
        ->where('status', TaskStatus::Ready)
        ->sole();

    $reception = User::factory()->inDepartment('reception')->create();
    app(TaskService::class)->claim($receptionTask, $reception);
    app(TaskService::class)->start($receptionTask->fresh(), $reception);

    Sanctum::actingAs($reception);
    $this->postJson("/api/v1/tasks/{$receptionTask->id}/complete", [
        'fields' => ['review_result' => 'accepted'],
    ])->assertOk();

    $journal = Journal::query()->whereDate('journal_date', today())->sole();

    expect($journal->payments)->toHaveCount(1)
        ->and($payment->fresh()->status)->toBe(PaymentStatus::Reviewed)
        ->and($payment->fresh()->journal_id)->toBe($journal->id);
});

it('scenario 5: reception query creates a sales clarify task and skips the journal', function (): void {
    ['salesTask' => $salesTask, 'sales' => $sales] = startPaymentFlow();
    $proofId = uploadPaymentProof($sales);

    Sanctum::actingAs($sales);
    $this->postJson("/api/v1/tasks/{$salesTask->id}/complete", [
        'fields' => salesConfirmFields(),
        'attachment_ids' => [$proofId],
    ])->assertOk();

    $receptionTask = Task::query()
        ->whereHas('definition', fn ($q) => $q->where('code', 'reception_review_payment'))
        ->where('status', TaskStatus::Ready)
        ->sole();

    $reception = User::factory()->inDepartment('reception')->create();
    app(TaskService::class)->claim($receptionTask, $reception);
    app(TaskService::class)->start($receptionTask->fresh(), $reception);

    Sanctum::actingAs($reception);
    $response = $this->postJson("/api/v1/tasks/{$receptionTask->id}/complete", [
        'fields' => [
            'review_result' => 'query',
            'review_note' => 'Amount does not match proof',
        ],
    ])->assertOk();

    expect($response->json('meta.created_tasks.0.title'))->not->toBeNull()
        ->and(Journal::query()->count())->toBe(0)
        ->and(Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'sales_clarify_payment'))->exists())->toBeTrue();
});

it('scenario 6: three payments reviewed on the same day share one journal', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();
    $reception = User::factory()->inDepartment('reception')->create();

    foreach ([1, 2, 3] as $installment) {
        $result = app(PaymentService::class)->startForProject($project, $installment, $sales);
        $salesTask = $result['task'];

        app(TaskService::class)->claim($salesTask, $sales);
        app(TaskService::class)->start($salesTask->fresh(), $sales);

        $proof = Attachment::factory()->paymentProof()->create(['uploaded_by_user_id' => $sales->id]);

        app(TaskService::class)->complete($salesTask->fresh(), $sales, salesConfirmFields($installment), [
            'payment_proof' => [$proof->id],
        ]);

        $reviewTask = Task::query()
            ->where('subject_id', $result['payment']->id)
            ->whereHas('definition', fn ($q) => $q->where('code', 'reception_review_payment'))
            ->where('status', TaskStatus::Ready)
            ->sole();

        app(TaskService::class)->claim($reviewTask, $reception);
        app(TaskService::class)->start($reviewTask->fresh(), $reception);
        app(TaskService::class)->complete($reviewTask->fresh(), $reception, ['review_result' => 'accepted'], []);
    }

    expect(Journal::query()->whereDate('journal_date', today())->count())->toBe(1)
        ->and(Journal::query()->first()?->payments)->toHaveCount(3);
});

it('starts a payment via HTTP and exposes form_schema on the sales task', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();

    Sanctum::actingAs($sales);

    $response = $this->postJson("/api/v1/projects/{$project->id}/payments", [
        'installment_number' => 1,
    ])->assertCreated();

    expect($response->json('meta.task.form_schema.fields'))->not->toBeEmpty()
        ->and($response->json('meta.task.required_attachment_types'))->toContain('payment_proof');
});

it('accepts flat attachment_ids on task complete', function (): void {
    ['salesTask' => $task, 'sales' => $sales] = startPaymentFlow();
    $proofId = uploadPaymentProof($sales);

    Sanctum::actingAs($sales);

    $this->postJson("/api/v1/tasks/{$task->id}/complete", [
        'fields' => salesConfirmFields(),
        'attachment_ids' => [$proofId],
    ])->assertOk();
});

it('streams an uploaded attachment', function (): void {
    $sales = User::factory()->inDepartment('sales')->create();
    $proofId = uploadPaymentProof($sales);

    Sanctum::actingAs($sales);

    $this->get("/api/v1/attachments/{$proofId}")
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
