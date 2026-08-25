<?php

declare(strict_types=1);

namespace App\Services\Site;

use App\Enums\ChecklistAnswerType;
use App\Enums\SiteReadiness;
use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Setting;
use App\Models\SiteChecklistItem;
use App\Models\SiteVisit;
use App\Models\SiteVisitAnswer;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkflowTemplate;
use App\Repositories\SiteVisitRepository;
use App\Services\Audit\AuditLogger;
use App\Services\Workflow\WorkflowEngine;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SiteVisitService
{
    public function __construct(
        private SiteVisitRepository $repository,
        private WorkflowEngine $workflowEngine,
        private AuditLogger $auditLogger,
    ) {}

    /** @return LengthAwarePaginator<int, SiteVisit> */
    public function paginate(int $perPage = 15): LengthAwarePaginator
    {
        return $this->repository->paginate(
            $this->repository->baseQuery()->with(['project', 'engineer']),
            $perPage,
        );
    }

    /** @param list<string> $relations */
    public function findOrFail(int $id, array $relations = []): SiteVisit
    {
        /** @var SiteVisit $visit */
        $visit = $this->repository->findOneOrFail($id, $relations);

        return $visit;
    }

    /** @return list<SiteVisit> */
    public function forProject(int $projectId): array
    {
        return SiteVisit::query()
            ->where('project_id', $projectId)
            ->with(['engineer'])
            ->orderBy('visit_number')
            ->get()
            ->all();
    }

    /** @return array{visit: SiteVisit, task: Task} */
    public function createForProject(Project $project, User $engineer): array
    {
        $template = WorkflowTemplate::query()
            ->where('code', 'site_visit')
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->first();

        if ($template === null) {
            throw new ModelNotFoundException(__('Site visit workflow template not found'));
        }

        return DB::transaction(function () use ($project, $engineer, $template): array {
            $visitNumber = (int) SiteVisit::query()->where('project_id', $project->id)->max('visit_number') + 1;
            $project->loadMissing(['quotation', 'client']);

            $visit = $this->repository->create([
                'reference' => sprintf('%s-SV%d', $project->reference, $visitNumber),
                'project_id' => $project->id,
                'visit_number' => $visitNumber,
                'engineer_user_id' => $engineer->id,
                'project_name_on_form' => $project->name,
                'address_on_form' => $project->client?->address,
                'quotation_number_on_form' => $project->quotation?->number,
                'visited_on' => CarbonImmutable::today()->toDateString(),
                'readiness' => SiteReadiness::Pending,
            ]);

            $instance = $this->workflowEngine->start($template, $visit);
            $task = $instance->tasks()->whereHas('definition', fn ($q) => $q->where('code', 'site_conduct_visit'))->firstOrFail();
            $visit->update(['task_id' => $task->id]);

            return [
                'visit' => $visit->fresh(['project', 'engineer', 'answers.checklistItem', 'measurements.deductions', 'attachments']),
                'task' => $task->load(['department', 'definition']),
            ];
        });
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(SiteVisit $visit, array $data, User $user, bool $allowSubmittedEdit = false): SiteVisit
    {
        if ($visit->isSubmitted() && ! $allowSubmittedEdit) {
            throw ValidationException::withMessages(['submitted_at' => [__('This visit has already been submitted.')]]);
        }

        if ($visit->isSubmitted() && $allowSubmittedEdit) {
            $this->auditLogger->log($visit, 'updated', $user, reason: 'site.measurements_edit');
        }

        $visit->update(array_filter([
            'visited_on' => $data['visited_on'] ?? null,
            'engineer_user_id' => $data['engineer_user_id'] ?? null,
            'project_name_on_form' => $data['project_name_on_form'] ?? null,
            'address_on_form' => $data['address_on_form'] ?? null,
            'quotation_number_on_form' => $data['quotation_number_on_form'] ?? null,
            'client_reference_note' => $data['client_reference_note'] ?? null,
            'client_signatory_name' => $data['client_signatory_name'] ?? null,
            'general_notes' => $data['general_notes'] ?? null,
        ], fn ($v) => $v !== null));

        if (isset($data['answers']) && is_array($data['answers'])) {
            $this->saveAnswers($visit, $data['answers']);
        }

        return $visit->fresh(['answers.checklistItem', 'measurements.deductions', 'attachments', 'engineer', 'project']);
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @return array{visit: SiteVisit, humidity_warning: bool|null}
     */
    public function submit(SiteVisit $visit, array $answers, User $user, ?int $signedAttachmentId = null): array
    {
        if ($visit->isSubmitted()) {
            throw ValidationException::withMessages(['submitted_at' => [__('This visit has already been submitted.')]]);
        }

        if ($signedAttachmentId !== null) {
            Attachment::query()->where('id', $signedAttachmentId)->update([
                'attachable_type' => $visit->getMorphClass(),
                'attachable_id' => $visit->id,
                'type' => 'site_report_signed',
            ]);
        }

        if ($visit->attachments()->where('type', 'site_report_signed')->doesntExist()) {
            throw ValidationException::withMessages(['site_report_signed' => [__('The signed site report scan is required.')]]);
        }

        $this->saveAnswers($visit, $answers);
        $visit->update(['submitted_at' => now()]);

        return [
            'visit' => $visit->fresh(['answers.checklistItem', 'measurements.deductions', 'attachments', 'engineer', 'project']),
            'humidity_warning' => $this->humidityWarning($visit->fresh(['answers.checklistItem'])),
        ];
    }

    /** @return array{visit: SiteVisit, task: Task} */
    public function createReinspectionVisit(SiteVisit $parentVisit, User $engineer): array
    {
        $parentVisit->loadMissing(['measurements.deductions', 'project']);

        return DB::transaction(function () use ($parentVisit, $engineer): array {
            $visitNumber = $parentVisit->visit_number + 1;
            $visit = $this->repository->create([
                'reference' => sprintf('%s-SV%d', $parentVisit->project->reference, $visitNumber),
                'project_id' => $parentVisit->project_id,
                'visit_number' => $visitNumber,
                'parent_visit_id' => $parentVisit->id,
                'engineer_user_id' => $engineer->id,
                'project_name_on_form' => $parentVisit->project_name_on_form,
                'address_on_form' => $parentVisit->address_on_form,
                'quotation_number_on_form' => $parentVisit->quotation_number_on_form,
                'client_reference_note' => $parentVisit->client_reference_note,
                'visited_on' => CarbonImmutable::today()->toDateString(),
                'readiness' => SiteReadiness::Pending,
            ]);

            $currentGroupId = null;
            foreach ($parentVisit->measurements as $index => $measurement) {
                $elementName = $measurement->element_name;
                $copy = $visit->measurements()->create([
                    'page_number' => $measurement->page_number,
                    'line_number' => $measurement->line_number,
                    'element_name' => $elementName,
                    'element_group_id' => $elementName ? null : $currentGroupId,
                    'height_m' => $measurement->height_m,
                    'length_m' => $measurement->length_m,
                    'width_m' => $measurement->width_m,
                    'thickness_m' => $measurement->thickness_m,
                    'diameter_m' => $measurement->diameter_m,
                    'other_note' => $measurement->other_note,
                    'area_sqm' => null,
                    'verified' => $measurement->verified,
                    'sort_order' => $measurement->sort_order ?? $index,
                ]);

                if ($elementName) {
                    $currentGroupId = $copy->id;
                    $copy->update(['element_group_id' => $currentGroupId]);
                }

                foreach ($measurement->deductions as $deduction) {
                    $copy->deductions()->create($deduction->only(['kind', 'label', 'count', 'length_m', 'width_m', 'sign', 'sort_order']));
                }
            }

            $template = WorkflowTemplate::query()->where('code', 'site_visit')->where('is_active', true)->whereNotNull('published_at')->firstOrFail();
            $instance = $this->workflowEngine->start($template, $visit);
            $task = $instance->tasks()->whereHas('definition', fn ($q) => $q->where('code', 'site_conduct_visit'))->firstOrFail();
            $visit->update(['task_id' => $task->id]);

            return [
                'visit' => $visit->fresh(['measurements.deductions', 'project', 'engineer']),
                'task' => $task->load(['department', 'definition']),
            ];
        });
    }

    public function assertSubmittedForTaskCompletion(SiteVisit $visit): void
    {
        if (! $visit->isSubmitted()) {
            throw new TaskNotReadyToComplete(__('The site visit must be submitted before this task can be completed.'), 'site.visit_not_submitted');
        }
    }

    public function hasCriticalFailures(SiteVisit $visit): bool
    {
        $visit->loadMissing(['answers.checklistItem']);
        foreach ($visit->answers as $answer) {
            if ($answer->checklistItem?->is_readiness_critical && $answer->passed === false) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string, mixed>> $answers */
    private function saveAnswers(SiteVisit $visit, array $answers): void
    {
        $items = SiteChecklistItem::query()->where('active', true)->get()->keyBy('code');
        foreach ($answers as $answerData) {
            $code = (string) ($answerData['code'] ?? '');
            $item = $items->get($code);
            if ($item === null) {
                continue;
            }
            $value = $answerData['value'] ?? null;
            SiteVisitAnswer::updateOrCreate(
                ['site_visit_id' => $visit->id, 'checklist_item_id' => $item->id],
                [
                    'answer_value' => ['value' => $value],
                    'passed' => $this->resolvePassed($item, $value),
                    'note' => $answerData['note'] ?? '',
                ],
            );
        }
    }

    private function resolvePassed(SiteChecklistItem $item, mixed $value): ?bool
    {
        if ($item->answer_type !== ChecklistAnswerType::YesNo) {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        return match (strtolower((string) $value)) {
            'yes', 'true', '1', 'نعم' => true,
            'no', 'false', '0', 'لا' => false,
            default => null,
        };
    }

    private function humidityWarning(SiteVisit $visit): ?bool
    {
        $max = Setting::get('humidity_max');
        if ($max === null) {
            return null;
        }
        $item = SiteChecklistItem::query()->where('code', 'humidity')->first();
        if ($item === null) {
            return null;
        }
        $answer = $visit->answers->firstWhere('checklist_item_id', $item->id);
        $raw = $answer?->answer_value['value'] ?? null;

        return is_numeric($raw) ? ((float) $raw > (float) $max) : null;
    }
}
