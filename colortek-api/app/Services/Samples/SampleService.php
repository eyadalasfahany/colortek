<?php

declare(strict_types=1);

namespace App\Services\Samples;

use App\Enums\ProjectStage;
use App\Enums\SampleApprovalType;
use App\Enums\SampleStatus;
use App\Enums\TaskStatus;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Client;
use App\Models\Project;
use App\Models\Sample;
use App\Models\SampleApproval;
use App\Models\Setting;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Repositories\SampleRepository;
use App\Services\Tasks\TaskService;
use App\Services\Workflow\WorkflowEngine;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class SampleService
{
    public function __construct(
        private SampleRepository $repository,
        private SampleReferenceGenerator $referenceGenerator,
        private SampleChain $sampleChain,
        private WorkflowEngine $workflowEngine,
    ) {}

    /** @return list<string> */
    public function detailRelations(): array
    {
        return ['client', 'project.quotation', 'parentSample', 'formulas.authorEmployee', 'formulas.registeredBy', 'approvals', 'attachments', 'approvedFormula'];
    }

    /** @return LengthAwarePaginator<int, Sample> */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate($this->repository->baseQuery()->with(['client', 'project'])->latest('id'), $perPage);
    }

    /** @param list<string> $relations */
    public function findOrFail(string|int $identifier, array $relations = []): Sample
    {
        if (is_numeric($identifier)) {
            return $this->repository->findOneOrFail((int) $identifier, $relations);
        }
        $sample = Sample::query()->with($relations)->where('reference', $identifier)->first();
        if ($sample === null) {
            throw new ModelNotFoundException(__('Sample not found'));
        }

        return $sample;
    }

    /** @return list<Sample> */
    public function forProject(int $projectId): array
    {
        return Sample::query()->where('project_id', $projectId)->orderByDesc('id')->with(['client', 'project'])->get()->all();
    }

    /** @param array<string, mixed> $data @return array{sample: Sample, task: Task} */
    public function start(array $data, User $user): array
    {
        if (empty($data['client_id'])) {
            throw new ModelNotFoundException(__('Client is required to create a sample.'));
        }
        if (empty($data['project_id']) && ! $user->can('sample.create_presale')) {
            throw new AccessDeniedHttpException(__('You cannot create a pre-sale sample without permission.'));
        }
        $template = WorkflowTemplate::query()->where('code', 'sample_request')->where('is_active', true)->whereNotNull('published_at')->first();
        if ($template === null) {
            throw new ModelNotFoundException(__('Sample workflow template not found'));
        }

        return DB::transaction(function () use ($data, $user, $template): array {
            $client = Client::query()->findOrFail((int) $data['client_id']);
            $project = isset($data['project_id']) ? Project::query()->find((int) $data['project_id']) : null;
            $defaultSize = Setting::query()->where('key', 'sample.default_size')->value('value') ?? 'A4';
            $sample = $this->repository->create([
                'reference' => $this->referenceGenerator->forSample($project, $client),
                'client_id' => $client->id,
                'project_id' => $project?->id,
                'root_sample_id' => null,
                'attempt_number' => 1,
                'requested_by_user_id' => $user->id,
                'requested_at' => now(),
                'needed_by' => $data['needed_by'] ?? null,
                'color' => $data['color'],
                'texture' => $data['texture'] ?? null,
                'client_reference' => $data['client_reference'] ?? null,
                'size' => $data['size'] ?? $defaultSize,
                'finish_requirement' => $data['finish_requirement'] ?? null,
                'notes' => $data['notes'] ?? null,
                'status' => SampleStatus::Draft,
                'is_presale' => $project === null,
            ]);
            $sample->update(['root_sample_id' => $sample->id]);
            $instance = $this->workflowEngine->start($template, $sample->fresh(['project', 'client']));
            $task = $instance->tasks()->whereHas('definition', fn ($q) => $q->where('code', 'sales_create_sample_request'))->firstOrFail();

            return ['sample' => $sample->fresh(['client', 'project']), 'task' => $task->load(['department', 'definition'])];
        });
    }

    /** @param array<string, mixed> $data */
    public function startModificationRequest(Sample $parent, array $data, User $user): Task
    {
        if ($parent->status !== SampleStatus::RejectedByClient) {
            throw new TaskNotReadyToComplete(__('Modifications are only allowed after client rejection.'), 'sample.modification_not_allowed');
        }
        $existing = Task::query()->where('subject_type', $parent->getMorphClass())->where('subject_id', $parent->id)
            ->whereHas('definition', fn ($q) => $q->where('code', 'sales_create_modification_request'))
            ->whereIn('status', [TaskStatus::Ready, TaskStatus::Claimed, TaskStatus::InProgress])->first();
        if ($existing !== null) {
            return $existing;
        }
        $template = WorkflowTemplate::query()->where('code', 'sample_modification')->where('is_active', true)->whereNotNull('published_at')->firstOrFail();
        $instance = $this->workflowEngine->start($template, $parent);

        return $instance->tasks()->whereHas('definition', fn ($q) => $q->where('code', 'sales_create_modification_request'))->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function requestModification(Sample $parent, array $data, User $user): Sample
    {
        $child = app(SampleTaskHandler::class)->createModificationChild($parent, $user, $data);
        $template = WorkflowTemplate::query()->where('code', 'sample_request')->where('is_active', true)->whereNotNull('published_at')->firstOrFail();
        $this->workflowEngine->startAtDefinition($template, $child, 'reception_review_sample_request');

        return $child->fresh($this->detailRelations());
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $attachmentIds
     */
    public function recordClientDecision(Sample $sample, array $data, User $user, array $attachmentIds): Sample
    {
        $task = Task::query()
            ->where('subject_id', $sample->id)
            ->where('subject_type', $sample->getMorphClass())
            ->whereHas('definition', fn ($q) => $q->where('code', 'sales_get_client_decision'))
            ->whereIn('status', [TaskStatus::Ready, TaskStatus::Claimed, TaskStatus::InProgress, TaskStatus::Waiting])
            ->first();

        if ($task !== null) {
            app(TaskService::class)->claim($task, $user);
            app(TaskService::class)->start($task->fresh(), $user);
            app(TaskService::class)->complete($task->fresh(), $user, $data, ['client_approval_form' => $attachmentIds['client_approval_form'] ?? $attachmentIds]);
        }

        return $sample->fresh($this->detailRelations());
    }

    /** @param array<string, mixed> $data */
    public function createModificationChild(Sample $parent, array $data, User $user): Sample
    {
        app(SampleTaskHandler::class)->createModificationChild($parent, $user, $data);

        return Sample::query()->where('parent_sample_id', $parent->id)->orderByDesc('id')->firstOrFail();
    }

    /** @return array{count: int, attempts: list<array<string, mixed>>} */
    public function chainPayload(Sample $sample): array
    {
        return $this->sampleChain->build($sample);
    }

    public function markApprovalFormGenerated(Sample $sample, User $user): SampleApproval
    {
        $approval = SampleApproval::query()->firstOrCreate(
            ['sample_id' => $sample->id, 'type' => SampleApprovalType::Client, 'decision' => null],
            ['recorded_by_user_id' => $user->id],
        );
        $approval->update(['form_generated_at' => now()]);

        return $approval->fresh();
    }

    public function maybeAdvanceProjectStage(Sample $sample): void
    {
        $project = $sample->project;
        if ($project === null) {
            return;
        }
        $open = Task::query()->where('project_id', $project->id)->whereHas('definition.template', fn ($q) => $q->where('code', 'sample_request'))
            ->whereNotIn('status', [TaskStatus::Completed, TaskStatus::Cancelled])->exists();
        if (! $open && in_array($project->stage, [ProjectStage::Lead, ProjectStage::Quotation, ProjectStage::Payment, ProjectStage::Sample], true)) {
            $project->update(['stage' => ProjectStage::Sample]);
        }
    }
}
