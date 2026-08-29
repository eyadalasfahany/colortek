<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ActivitySeverity;
use App\Enums\FormulaStatus;
use App\Enums\HolidayType;
use App\Enums\JournalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\SampleStatus;
use App\Enums\SiteReadiness;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TimeEntrySource;
use App\Models\ActivityEvent;
use App\Models\BlockerCategory;
use App\Models\Client;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Formula;
use App\Models\Holiday;
use App\Models\Journal;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\Sample;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Models\TaskStatusEvent;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkflowInstance;
use App\Models\WorkflowTaskDefinition;
use App\Models\WorkflowTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Demo/development dataset: clients, quotations, projects across every stage,
 * tasks across every status, plus payments, samples, site visits and time entries.
 *
 * Depends on ReferenceSeeder (departments, roles, permissions) and the workflow
 * seeders (templates + task definitions) having run first.
 *
 * Re-runnable: wipes the transactional tables it owns before regenerating.
 * Reference data (roles, departments, workflow templates) is never touched.
 */
final class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PASSWORD = 'password';

    /** Transactional tables this seeder owns, ordered child -> parent. */
    private const OWNED_TABLES = [
        'time_entries',
        'task_status_events',
        'task_field_values',
        'task_comments',
        'task_dependencies',
        'activity_events',
        'tasks',
        'workflow_transition_log',
        'workflow_instances',
        'sample_approvals',
        'formulas',
        'samples',
        'site_measurement_deductions',
        'site_measurements',
        'site_visit_answers',
        'site_visits',
        'corrective_actions',
        'journal_payment',
        'payments',
        'journals',
        'attachments',
        'projects',
        'quotations',
        'clients',
        'holidays',
        'employees',
    ];

    /** @var array<string, Department> */
    private array $departments = [];

    /** @var array<string, User> */
    private array $users = [];

    /** @var Collection<int, BlockerCategory> */
    private Collection $blockers;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command->error('DemoSeeder refuses to run in production.');

            return;
        }

        if (Department::query()->count() === 0 || WorkflowTemplate::query()->count() === 0) {
            $this->command->error('Run ReferenceSeeder and the workflow seeders first.');

            return;
        }

        // Deterministic dataset across re-runs.
        fake()->seed(20260827);

        $this->truncateOwnedTables();

        $this->departments = Department::query()->get()->keyBy('code')->all();
        $this->blockers = BlockerCategory::query()->get();

        $this->seedUsers();
        $this->seedEmployees();
        $this->seedHolidays();

        $clients = $this->seedClients();
        $projects = $this->seedProjects($clients);

        foreach ($projects as $project) {
            $this->seedProjectWorkflow($project);
        }

        $this->seedFinancials($projects);
        $this->seedSamples($projects);
        $this->seedSiteVisits($projects);

        $this->command->info(sprintf(
            'Demo data: %d clients, %d projects, %d tasks, %d payments, %d samples, %d site visits.',
            Client::query()->count(),
            Project::query()->count(),
            Task::query()->count(),
            Payment::query()->count(),
            Sample::query()->count(),
            SiteVisit::query()->count(),
        ));
    }

    private function truncateOwnedTables(): void
    {
        DB::transaction(function (): void {
            foreach (self::OWNED_TABLES as $table) {
                DB::table($table)->delete();
            }

            // Demo users are recreated below; keep any real/manually-made accounts.
            $demoEmails = array_map(
                fn (string $code): string => "{$code}@colortek.test",
                array_keys(self::departmentSeedMap()),
            );

            User::query()->whereIn('email', $demoEmails)->delete();
        });
    }

    /**
     * Department code => [display name, role].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private static function departmentSeedMap(): array
    {
        return [
            'sales' => ['Sara Sales', 'sales'],
            'reception' => ['Rana Reception', 'reception'],
            'accounting' => ['Adel Accounting', 'accounting'],
            'workshop' => ['Waleed Workshop', 'workshop_supervisor'],
            'tinting' => ['Tarek Tinting', 'tinting'],
            'site' => ['Sameh Site', 'site_engineer'],
            'management' => ['Mona Management', 'management'],
        ];
    }

    private function seedUsers(): void
    {
        foreach (self::departmentSeedMap() as $code => [$name, $role]) {
            $department = $this->departments[$code] ?? null;

            if (! $department instanceof Department) {
                continue;
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => "{$code}@colortek.test",
                'password' => self::PASSWORD,
                'phone' => fake()->numerify('+20 1## ### ####'),
                'locale' => 'en',
                'primary_department_id' => $department->id,
                'active' => true,
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            $user->departments()->syncWithoutDetaching([$department->id]);
            $user->syncRoles([$role]);

            $this->users[$code] = $user;
        }
    }

    private function seedEmployees(): void
    {
        $counter = 1;

        foreach ($this->departments as $code => $department) {
            $linkedUser = $this->users[$code] ?? null;

            if ($linkedUser instanceof User) {
                Employee::query()->create([
                    'code' => sprintf('EMP%03d', $counter++),
                    'name' => $linkedUser->name,
                    'department_id' => $department->id,
                    'user_id' => $linkedUser->id,
                    'active' => true,
                ]);
            }

            // A couple of unlinked floor staff per department.
            foreach (range(1, 2) as $ignored) {
                Employee::query()->create([
                    'code' => sprintf('EMP%03d', $counter++),
                    'name' => fake()->name(),
                    'department_id' => $department->id,
                    'active' => true,
                ]);
            }
        }
    }

    private function seedHolidays(): void
    {
        $creator = $this->users['management'] ?? null;

        $holidays = [
            ['2026-01-01', 'New Year', HolidayType::Public, true],
            ['2026-04-25', 'Sinai Liberation Day', HolidayType::Public, true],
            ['2026-07-23', 'Revolution Day', HolidayType::Public, true],
            ['2026-09-10', 'Company Anniversary', HolidayType::Company, true],
            ['2026-12-24', 'Year-end Shutdown', HolidayType::Company, false],
        ];

        foreach ($holidays as [$date, $name, $type, $recurring]) {
            Holiday::query()->create([
                'date' => $date,
                'name' => $name,
                'type' => $type,
                'is_recurring' => $recurring,
                'created_by_user_id' => $creator?->id,
            ]);
        }
    }

    /** @return Collection<int, Client> */
    private function seedClients(): Collection
    {
        $names = [
            'Nile Towers Development',
            'Cairo Festival Interiors',
            'Alexandria Marina Resorts',
            'New Capital Business Park',
            'Red Sea Hospitality Group',
            'Giza Residence Contracting',
        ];

        return Collection::make($names)->map(fn (string $name): Client => Client::query()->create([
            'name' => $name,
            'contact_person' => fake()->name(),
            'phone' => fake()->numerify('+20 1## ### ####'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'notes' => fake()->optional(0.4)->sentence(),
        ]));
    }

    /**
     * 12 projects spread across every stage, with a realistic status mix.
     *
     * @param  Collection<int, Client>  $clients
     * @return Collection<int, Project>
     */
    private function seedProjects(Collection $clients): Collection
    {
        // [stage, status, site_ready]
        $blueprint = [
            [ProjectStage::Lead, ProjectStatus::Active, false],
            [ProjectStage::Quotation, ProjectStatus::Active, false],
            [ProjectStage::Payment, ProjectStatus::Active, false],
            [ProjectStage::Sample, ProjectStatus::Active, true],
            [ProjectStage::Sample, ProjectStatus::OnHold, true],
            [ProjectStage::Site, ProjectStatus::Active, true],
            [ProjectStage::Site, ProjectStatus::Active, false],
            [ProjectStage::Production, ProjectStatus::Active, true],
            [ProjectStage::Execution, ProjectStatus::Active, true],
            [ProjectStage::Execution, ProjectStatus::OnHold, true],
            [ProjectStage::Delivery, ProjectStatus::Active, true],
            [ProjectStage::Completed, ProjectStatus::Completed, true],
            [ProjectStage::Quotation, ProjectStatus::Cancelled, false],
        ];

        $sales = $this->users['sales'] ?? null;
        $projects = Collection::make();

        foreach ($blueprint as $index => [$stage, $status, $siteReady]) {
            $client = $clients[$index % $clients->count()];
            $number = sprintf('SO%04d', 1001 + $index);

            $quotation = Quotation::query()->create([
                'number' => $number,
                'client_id' => $client->id,
                'total_value' => fake()->randomFloat(2, 45_000, 850_000),
                'currency' => 'EGP',
                'status' => $this->quotationStatusForStage($stage, $status),
                'locked_at' => $stage === ProjectStage::Lead ? null : now()->subDays(30 - $index),
                'locked_by_user_id' => $stage === ProjectStage::Lead ? null : $sales?->id,
            ]);

            $projects->push(Project::query()->create([
                'reference' => $number,
                'name' => $client->name.' — '.fake()->randomElement([
                    'Lobby Finishes', 'Tower A Facade', 'Villa Interiors', 'Showroom Fit-out',
                    'Penthouse Walls', 'Reception Hall', 'Pool Deck Coating',
                ]),
                'client_id' => $client->id,
                'quotation_id' => $quotation->id,
                'stage' => $stage,
                // `status` is a plain string column (no cast on the model); the app
                // writes and compares it as ->value, so follow that convention.
                'status' => $status->value,
                'sales_user_id' => $sales?->id,
                'site_ready' => $siteReady,
                'block_all_when_site_not_ready' => ! $siteReady && $stage === ProjectStage::Site,
            ]));
        }

        return $projects;
    }

    private function quotationStatusForStage(ProjectStage $stage, ProjectStatus $status): QuotationStatus
    {
        if ($status === ProjectStatus::Cancelled) {
            return QuotationStatus::Cancelled;
        }

        return match ($stage) {
            ProjectStage::Lead => QuotationStatus::Draft,
            ProjectStage::Quotation => QuotationStatus::Sent,
            ProjectStage::Payment => QuotationStatus::Accepted,
            default => QuotationStatus::Locked,
        };
    }

    /**
     * One workflow instance per project past the lead stage, with tasks spread
     * across statuses. Each open task uses a distinct task definition so the
     * `tasks_one_open_per_definition` unique index holds.
     */
    private function seedProjectWorkflow(Project $project): void
    {
        if ($project->stage === ProjectStage::Lead || $project->status === ProjectStatus::Cancelled->value) {
            return;
        }

        $template = WorkflowTemplate::query()
            ->where('code', $this->templateCodeForStage($project->stage))
            ->with('definitions')
            ->first();

        if (! $template instanceof WorkflowTemplate) {
            return;
        }

        $definitions = $template->definitions->sortBy('id')->values();

        if ($definitions->isEmpty()) {
            return;
        }

        $isCompleted = $project->stage === ProjectStage::Completed;

        $instance = WorkflowInstance::query()->create([
            'template_id' => $template->id,
            'subject_type' => $project->getMorphClass(),
            'subject_id' => $project->id,
            'project_id' => $project->id,
            'status' => $isCompleted ? 'completed' : 'running',
            'started_at' => now()->subDays(20),
            'completed_at' => $isCompleted ? now()->subDays(2) : null,
        ]);

        // Everything before the cursor is completed; the cursor task is live.
        $cursor = $isCompleted
            ? $definitions->count()
            : max(1, (int) floor($definitions->count() / 2));

        foreach ($definitions as $position => $definition) {
            $status = $position < $cursor
                ? TaskStatus::Completed
                : $this->openStatusFor($project, $position - $cursor);

            $this->createTask($project, $instance, $definition, $status, $position);
        }
    }

    private function templateCodeForStage(ProjectStage $stage): string
    {
        return match ($stage) {
            ProjectStage::Payment => 'payment_cycle',
            ProjectStage::Site => 'site_visit',
            default => 'sample_request',
        };
    }

    /**
     * Status for the live portion of a workflow: the cursor task is active,
     * later ones are still queued.
     */
    private function openStatusFor(Project $project, int $offset): TaskStatus
    {
        if ($offset > 0) {
            return TaskStatus::Pending;
        }

        if ($project->status === ProjectStatus::OnHold->value) {
            return TaskStatus::Blocked;
        }

        if (! $project->site_ready && $project->block_all_when_site_not_ready) {
            return TaskStatus::Waiting;
        }

        return match ($project->stage) {
            // Keep at least one blocker on an *active* project: the control room
            // dashboard only counts tasks belonging to projects with status=active.
            ProjectStage::Production => TaskStatus::Blocked,
            ProjectStage::Execution => TaskStatus::InProgress,
            ProjectStage::Delivery => TaskStatus::Claimed,
            default => TaskStatus::Ready,
        };
    }

    private function createTask(
        Project $project,
        WorkflowInstance $instance,
        WorkflowTaskDefinition $definition,
        TaskStatus $status,
        int $position,
    ): Task {
        $departmentId = $definition->department_id ?? $this->departments['sales']->id;
        $assignee = $this->userForDepartment($departmentId);
        $createdAt = now()->subDays(20 - $position);

        $attributes = [
            'reference' => sprintf('%s-T%03d', $project->reference, $position + 1),
            'instance_id' => $instance->id,
            'task_definition_id' => $definition->id,
            'project_id' => $project->id,
            'subject_type' => $project->getMorphClass(),
            'subject_id' => $project->id,
            'title' => $definition->title_en,
            'instructions' => $definition->instructions_en,
            'department_id' => $departmentId,
            'status' => $status,
            'priority' => $definition->priority ?? TaskPriority::Normal,
            'ready_at' => $status === TaskStatus::Pending ? null : $createdAt,
            'due_at' => $definition->sla_minutes
                ? $createdAt->copy()->addMinutes((int) $definition->sla_minutes)
                : $createdAt->copy()->addDays(3),
        ];

        $attributes += match ($status) {
            TaskStatus::Completed => [
                'claimed_by_user_id' => $assignee?->id,
                'claimed_at' => $createdAt,
                'started_at' => $createdAt->copy()->addMinutes(15),
                'completed_at' => $createdAt->copy()->addHours(4),
                'completed_by_user_id' => $assignee?->id,
                'active_seconds' => fake()->numberBetween(1_200, 14_400),
            ],
            TaskStatus::InProgress => [
                'claimed_by_user_id' => $assignee?->id,
                'claimed_at' => $createdAt,
                'started_at' => $createdAt->copy()->addMinutes(10),
                'active_seconds' => fake()->numberBetween(600, 7_200),
            ],
            TaskStatus::Claimed => [
                'claimed_by_user_id' => $assignee?->id,
                'claimed_at' => $createdAt,
            ],
            TaskStatus::Blocked => [
                'claimed_by_user_id' => $assignee?->id,
                'claimed_at' => $createdAt,
                'started_at' => $createdAt->copy()->addMinutes(10),
                'blocked_at' => $createdAt->copy()->addHours(2),
                'blocked_by_user_id' => $assignee?->id,
                'blocker_category_id' => $this->blockers->random()->id,
                'blocker_reason' => fake()->sentence(),
                'blocker_expected_resolution' => now()->addDays(3)->toDateString(),
                'blocked_seconds' => fake()->numberBetween(3_600, 86_400),
            ],
            default => [],
        };

        // A couple of genuinely overdue open tasks so the SLA views have data.
        if ($status === TaskStatus::Ready && $position % 3 === 0) {
            $attributes['due_at'] = now()->subHours(6);
            $attributes['is_overdue'] = true;
        }

        $task = Task::query()->create($attributes);

        $this->seedTaskHistory($task, $status, $assignee, $createdAt);

        return $task;
    }

    private function seedTaskHistory(Task $task, TaskStatus $status, ?User $assignee, Carbon $createdAt): void
    {
        $trail = match ($status) {
            TaskStatus::Completed => [TaskStatus::Ready, TaskStatus::Claimed, TaskStatus::InProgress, TaskStatus::Completed],
            TaskStatus::InProgress => [TaskStatus::Ready, TaskStatus::Claimed, TaskStatus::InProgress],
            TaskStatus::Blocked => [TaskStatus::Ready, TaskStatus::Claimed, TaskStatus::InProgress, TaskStatus::Blocked],
            TaskStatus::Claimed => [TaskStatus::Ready, TaskStatus::Claimed],
            default => [],
        };

        $previous = TaskStatus::Pending;

        foreach ($trail as $index => $to) {
            TaskStatusEvent::query()->create([
                'task_id' => $task->id,
                'from_status' => $previous->value,
                'to_status' => $to->value,
                'user_id' => $assignee?->id,
                'created_at' => $createdAt->copy()->addMinutes(10 * $index),
            ]);

            $previous = $to;
        }

        if (in_array($status, [TaskStatus::Completed, TaskStatus::InProgress], true) && $assignee instanceof User) {
            $seconds = (int) ($task->active_seconds ?: 3_600);

            TimeEntry::query()->create([
                'task_id' => $task->id,
                'user_id' => $assignee->id,
                'started_at' => $createdAt->copy()->addMinutes(15),
                'ended_at' => $status === TaskStatus::Completed
                    ? $createdAt->copy()->addMinutes(15)->addSeconds($seconds)
                    : null,
                'seconds' => $status === TaskStatus::Completed ? $seconds : 0,
                'source' => TimeEntrySource::Timer,
                'needs_review' => false,
            ]);
        }

        ActivityEvent::query()->create([
            'project_id' => $task->project_id,
            'subject_type' => $task->getMorphClass(),
            'subject_id' => $task->id,
            'type' => 'task.'.$status->value,
            'severity' => match ($status) {
                TaskStatus::Blocked => ActivitySeverity::Blocker,
                TaskStatus::Completed => ActivitySeverity::Success,
                default => ActivitySeverity::Info,
            },
            'actor_user_id' => $assignee?->id,
            'department_id' => $task->department_id,
            'message_en' => sprintf('Task "%s" is %s.', $task->localizedTitle('en'), $status->value),
            'message_ar' => sprintf('المهمة "%s" حالتها %s.', $task->localizedTitle('en'), $status->value),
            'created_at' => $createdAt,
        ]);
    }

    private function userForDepartment(int $departmentId): ?User
    {
        foreach ($this->departments as $code => $department) {
            if ($department->id === $departmentId) {
                return $this->users[$code] ?? null;
            }
        }

        return null;
    }

    /**
     * Payments for every project that reached the payment stage, plus one
     * accounted journal batching the reviewed ones.
     *
     * @param  Collection<int, Project>  $projects
     */
    private function seedFinancials(Collection $projects): void
    {
        $sales = $this->users['sales'] ?? null;
        $accounting = $this->users['accounting'] ?? null;

        $paying = $projects->filter(fn (Project $p): bool => $p->status !== ProjectStatus::Cancelled->value
            && ! in_array($p->stage, [ProjectStage::Lead, ProjectStage::Quotation], true));

        $journal = Journal::query()->create([
            'journal_date' => now()->subDays(3)->toDateString(),
            'status' => JournalStatus::Accounted,
            'prepared_by_user_id' => $accounting?->id,
            'submitted_at' => now()->subDays(3),
            'accounted_by_user_id' => $accounting?->id,
            'accounted_at' => now()->subDays(2),
            'total_amount' => 0,
        ]);

        $journalTotal = '0';

        foreach ($paying as $project) {
            $installments = $project->stage === ProjectStage::Completed ? 3 : 2;

            foreach (range(1, $installments) as $installment) {
                // Earlier installments are fully settled; the last one is still moving.
                $isLast = $installment === $installments && $project->stage !== ProjectStage::Completed;
                $status = $isLast
                    ? fake()->randomElement([PaymentStatus::PendingConfirmation, PaymentStatus::Confirmed, PaymentStatus::Reviewed])
                    : PaymentStatus::Accounted;

                $paidAt = now()->subDays(30 - ($installment * 5));
                $amount = fake()->randomFloat(2, 15_000, 120_000);

                $payment = Payment::query()->create([
                    'project_id' => $project->id,
                    'quotation_id' => $project->quotation_id,
                    'installment_number' => $installment,
                    'amount' => $amount,
                    'currency' => 'EGP',
                    'method' => fake()->randomElement(PaymentMethod::cases()),
                    'paid_at' => $paidAt->toDateString(),
                    'confirmed_by_user_id' => $status === PaymentStatus::PendingConfirmation ? null : $sales?->id,
                    'confirmed_at' => $status === PaymentStatus::PendingConfirmation ? null : $paidAt->copy()->addHours(2),
                    'reviewed_by_user_id' => in_array($status, [PaymentStatus::Reviewed, PaymentStatus::Accounted], true)
                        ? $accounting?->id
                        : null,
                    'reviewed_at' => in_array($status, [PaymentStatus::Reviewed, PaymentStatus::Accounted], true)
                        ? $paidAt->copy()->addDay()
                        : null,
                    'journal_id' => $status === PaymentStatus::Accounted ? $journal->id : null,
                    'status' => $status,
                    'notes' => fake()->optional(0.3)->sentence(),
                ]);

                if ($status === PaymentStatus::Accounted) {
                    $journal->payments()->attach($payment->id, ['amount_snapshot' => $amount]);
                    $journalTotal = bcadd($journalTotal, (string) $amount, 2);
                }
            }
        }

        $journal->update(['total_amount' => $journalTotal]);
    }

    /**
     * Samples for projects at or past the sample stage, with an approved
     * formula on the ones that got client sign-off.
     *
     * @param  Collection<int, Project>  $projects
     */
    private function seedSamples(Collection $projects): void
    {
        $sales = $this->users['sales'] ?? null;
        $workshop = $this->users['workshop'] ?? null;

        $pastSample = [
            ProjectStage::Sample, ProjectStage::Site, ProjectStage::Production,
            ProjectStage::Execution, ProjectStage::Delivery, ProjectStage::Completed,
        ];

        $eligible = $projects->filter(fn (Project $p): bool => in_array($p->stage, $pastSample, true));

        $statuses = [
            SampleStatus::PendingManagerApproval,
            SampleStatus::InWorkshop,
            SampleStatus::AwaitingFormulaRegistration,
            SampleStatus::ReadyForClientApproval,
            SampleStatus::Approved,
            SampleStatus::RejectedByClient,
        ];

        foreach ($eligible->values() as $index => $project) {
            $status = $project->stage === ProjectStage::Completed
                ? SampleStatus::Approved
                : $statuses[$index % count($statuses)];

            $sample = Sample::query()->create([
                'reference' => sprintf('%s-S%02d', $project->reference, 1),
                'client_id' => $project->client_id,
                'project_id' => $project->id,
                'attempt_number' => 1,
                'requested_by_user_id' => $sales?->id,
                'requested_at' => now()->subDays(18),
                'needed_by' => now()->addDays(7)->toDateString(),
                'color' => fake()->randomElement(['Warm Sand', 'Desert Beige', 'Nile Grey', 'Ivory Mist', 'Terracotta']),
                'texture' => fake()->randomElement(['Fine', 'Medium', 'Coarse', 'Smooth']),
                'client_reference' => fake()->bothify('REF-##??'),
                'size' => fake()->randomElement(['A4 panel', '30x30 cm', '50x50 cm']),
                'finish_requirement' => fake()->randomElement(['Matte', 'Satin', 'Gloss']),
                'notes' => fake()->optional(0.5)->sentence(),
                'status' => $status,
                'is_presale' => false,
            ]);

            if (in_array($status, [SampleStatus::ReadyForClientApproval, SampleStatus::Approved], true)) {
                $formula = Formula::query()->create([
                    'reference' => $sample->reference.'-F1',
                    'sample_id' => $sample->id,
                    'version' => 1,
                    'body' => sprintf(
                        'Base %s / Pigment A %d%% / Pigment B %d%% / Binder %d%%',
                        fake()->randomElement(['White', 'Neutral', 'Deep']),
                        fake()->numberBetween(2, 12),
                        fake()->numberBetween(1, 8),
                        fake()->numberBetween(15, 35),
                    ),
                    'author_user_id' => $workshop?->id,
                    'authored_at' => now()->subDays(12)->toDateString(),
                    'registered_by_user_id' => $workshop?->id,
                    'registered_at' => now()->subDays(11),
                    'status' => $status === SampleStatus::Approved
                        ? FormulaStatus::Approved
                        : FormulaStatus::Registered,
                ]);

                if ($status === SampleStatus::Approved) {
                    $sample->update(['approved_formula_id' => $formula->id]);
                }
            }
        }
    }

    /**
     * Site visits for projects at or past the site stage.
     *
     * @param  Collection<int, Project>  $projects
     */
    private function seedSiteVisits(Collection $projects): void
    {
        $engineer = $this->users['site'] ?? null;

        $pastSite = [
            ProjectStage::Site, ProjectStage::Production,
            ProjectStage::Execution, ProjectStage::Delivery, ProjectStage::Completed,
        ];

        $eligible = $projects->filter(fn (Project $p): bool => in_array($p->stage, $pastSite, true));

        foreach ($eligible as $project) {
            $readiness = match (true) {
                ! $project->site_ready => SiteReadiness::NotReady,
                $project->stage === ProjectStage::Site => SiteReadiness::Pending,
                default => SiteReadiness::Ready,
            };

            $submitted = $readiness !== SiteReadiness::Pending;

            SiteVisit::query()->create([
                'reference' => sprintf('%s-V%02d', $project->reference, 1),
                'project_id' => $project->id,
                'visit_number' => 1,
                'engineer_user_id' => $engineer?->id,
                'project_name_on_form' => $project->name,
                'address_on_form' => fake()->streetAddress().', '.fake()->city(),
                'quotation_number_on_form' => $project->reference,
                'visited_on' => now()->subDays(9)->toDateString(),
                'readiness' => $readiness,
                'general_notes' => fake()->optional(0.6)->sentence(),
                'client_signatory_name' => $submitted ? fake()->name() : null,
                'engineer_signed_at' => $submitted ? now()->subDays(9) : null,
                'client_signed_at' => $submitted ? now()->subDays(9) : null,
                'submitted_at' => $submitted ? now()->subDays(9) : null,
            ]);
        }
    }
}
