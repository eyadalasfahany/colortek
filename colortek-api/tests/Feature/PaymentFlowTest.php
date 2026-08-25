<?php

declare(strict_types=1);

use App\Enums\JournalStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Jobs\OpenDailyJournal;
use App\Models\Attachment;
use App\Models\AuditLog;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Services\Payments\JournalService;
use App\Services\Payments\JournalWorkflowService;
use App\Services\Payments\PaymentService;
use App\Services\Tasks\TaskService;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
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

function reviewPaymentAccepted(Payment $payment, User $sales, User $reception): Journal
{
    $salesTask = Task::query()
        ->where('subject_id', $payment->id)
        ->whereHas('definition', fn ($q) => $q->where('code', 'sales_confirm_payment'))
        ->firstOrFail();

    $proof = Attachment::factory()->paymentProof()->create(['uploaded_by_user_id' => $sales->id]);

    app(TaskService::class)->complete($salesTask->fresh(), $sales, salesConfirmFields((int) $payment->installment_number), [
        'payment_proof' => [$proof->id],
    ]);

    $receptionTask = Task::query()
        ->where('subject_id', $payment->id)
        ->whereHas('definition', fn ($q) => $q->where('code', 'reception_review_payment'))
        ->where('status', TaskStatus::Ready)
        ->sole();

    app(TaskService::class)->claim($receptionTask, $reception);
    app(TaskService::class)->start($receptionTask->fresh(), $reception);
    app(TaskService::class)->complete($receptionTask->fresh(), $reception, ['review_result' => 'accepted'], []);

    return Journal::query()->whereDate('journal_date', today())->sole();
}

function submitDailyJournal(Journal $journal, User $reception): Task
{
    $journalTask = app(JournalWorkflowService::class)->ensureDailyJournalTask(
        CarbonImmutable::parse($journal->journal_date->toDateString()),
    );

    app(TaskService::class)->claim($journalTask, $reception);
    app(TaskService::class)->start($journalTask->fresh(), $reception);
    app(TaskService::class)->complete($journalTask->fresh(), $reception, [], []);

    return Task::query()
        ->whereHas('definition', fn ($q) => $q->where('code', 'accounting_process_journal'))
        ->where('subject_id', $journal->id)
        ->sole();
}

it('scenario 7: a submitted journal is read-only', function (): void {
    ['payment' => $payment, 'sales' => $sales] = startPaymentFlow();
    $reception = User::factory()->inDepartment('reception')->create();

    $journal = reviewPaymentAccepted($payment, $sales, $reception);
    submitDailyJournal($journal, $reception);

    expect($journal->fresh()->status)->toBe(JournalStatus::Submitted);

    expect(fn () => app(JournalService::class)->attachPayment($journal->fresh(), $payment->fresh()))
        ->toThrow(TaskNotReadyToComplete::class);
});

it('scenario 8: changing a payment amount after journal submission does not change the journal total', function (): void {
    ['payment' => $payment, 'sales' => $sales] = startPaymentFlow();
    $reception = User::factory()->inDepartment('reception')->create();

    $journal = reviewPaymentAccepted($payment, $sales, $reception);
    submitDailyJournal($journal, $reception);

    $totalBefore = $journal->fresh()->total_amount;
    $payment->update(['amount' => 99999]);

    $snapshot = DB::table('journal_payment')
        ->where('journal_id', $journal->id)
        ->where('payment_id', $payment->id)
        ->value('amount_snapshot');

    expect($journal->fresh()->total_amount)->toBe($totalBefore)
        ->and((string) $snapshot)->not->toBe('99999.00');
});

it('scenario 9: accounting query reopens the journal, creates a reception task, and writes an audit row', function (): void {
    ['payment' => $payment, 'sales' => $sales] = startPaymentFlow();
    $reception = User::factory()->inDepartment('reception')->create();
    $accounting = User::factory()->inDepartment('accounting')->create();

    $journal = reviewPaymentAccepted($payment, $sales, $reception);
    $accountingTask = submitDailyJournal($journal, $reception);

    app(TaskService::class)->claim($accountingTask, $accounting);
    app(TaskService::class)->start($accountingTask->fresh(), $accounting);
    app(TaskService::class)->complete($accountingTask->fresh(), $accounting, [
        'accounting_result' => 'query',
        'note' => 'Totals do not match',
    ], []);

    expect($journal->fresh()->status)->toBe(JournalStatus::Open)
        ->and(Task::query()->whereHas('definition', fn ($q) => $q->where('code', 'reception_fix_journal'))->exists())->toBeTrue()
        ->and(AuditLog::query()
            ->where('auditable_type', $journal->getMorphClass())
            ->where('auditable_id', $journal->id)
            ->where('event', 'reopened')
            ->exists())->toBeTrue();
});

it('scenario 10: installment 2 runs a second independent instance while installment 1 is still open', function (): void {
    $project = Project::factory()->create();
    $sales = User::factory()->inDepartment('sales')->create();

    $first = app(PaymentService::class)->startForProject($project, 1, $sales);
    $second = app(PaymentService::class)->startForProject($project, 2, $sales);

    expect($first['payment']->id)->not->toBe($second['payment']->id)
        ->and($first['task']->instance_id)->not->toBe($second['task']->instance_id)
        ->and($first['task']->status)->toBe(TaskStatus::Ready)
        ->and($second['task']->status)->toBe(TaskStatus::Ready);
});

it('scenario 11: a day with no payments does not leave a stuck open task', function (): void {
    $yesterday = CarbonImmutable::yesterday();

    app(JournalWorkflowService::class)->ensureDailyJournalTask($yesterday);

    $journal = Journal::query()->whereDate('journal_date', $yesterday)->sole();
    $openTask = Task::query()
        ->where('subject_id', $journal->id)
        ->whereHas('definition', fn ($q) => $q->where('code', 'reception_daily_journal'))
        ->whereNot('status', TaskStatus::Completed)
        ->sole();

    expect($openTask->status)->toBe(TaskStatus::Ready);

    app(JournalWorkflowService::class)->autoCloseEmptyJournalForDate($yesterday);

    expect($journal->fresh()->status)->toBe(JournalStatus::Submitted)
        ->and($journal->fresh()->total_amount)->toBe('0.00')
        ->and($openTask->fresh()->status)->toBe(TaskStatus::Completed);
});

it('open daily journal job creates todays journal task and closes empty yesterday', function (): void {
    $yesterday = CarbonImmutable::yesterday();
    app(JournalWorkflowService::class)->ensureDailyJournalTask($yesterday);

    (new OpenDailyJournal)->handle(app(JournalWorkflowService::class));

    expect(Journal::query()->whereDate('journal_date', today())->exists())->toBeTrue()
        ->and(Task::query()
            ->whereHas('definition', fn ($q) => $q->where('code', 'reception_daily_journal'))
            ->whereDate('created_at', today())
            ->exists())->toBeTrue()
        ->and(Journal::query()->whereDate('journal_date', $yesterday)->first()?->status)
        ->toBe(JournalStatus::Submitted);
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
