<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\AttachmentType;
use App\Enums\CorrectiveActionStatus;
use App\Enums\FormulaStatus;
use App\Enums\JournalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStage;
use App\Enums\ProjectStatus;
use App\Enums\QuotationStatus;
use App\Enums\ResponsibleParty;
use App\Enums\SampleApprovalDecision;
use App\Enums\SampleApprovalType;
use App\Enums\SampleStatus;
use App\Enums\SiteReadiness;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TimeEntrySource;
use App\Http\Controllers\Controller;
use App\Models\BlockerCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EnumController extends Controller
{
    public function show(Request $request, string $name): JsonResponse
    {
        $data = match ($name) {
            'task_status' => $this->taskStatuses(),
            'task_priority' => $this->taskPriorities(),
            'blocker_category' => $this->blockerCategories(),
            'payment_method' => $this->labeledEnumCases(PaymentMethod::cases()),
            'payment_status' => $this->labeledEnumCases(PaymentStatus::cases()),
            'journal_status' => $this->labeledEnumCases(JournalStatus::cases()),
            'project_stage' => $this->labeledEnumCases(ProjectStage::cases()),
            'project_status' => $this->labeledEnumCases(ProjectStatus::cases()),
            'quotation_status' => $this->labeledEnumCases(QuotationStatus::cases()),
            'sample_status' => $this->labeledEnumCases(SampleStatus::cases()),
            'formula_status' => $this->labeledEnumCases(FormulaStatus::cases()),
            'approval_type' => $this->labeledEnumCases(SampleApprovalType::cases()),
            'approval_decision' => $this->labeledEnumCases(SampleApprovalDecision::cases()),
            'attachment_type' => $this->labeledEnumCases(AttachmentType::cases()),
            'time_entry_source' => $this->labeledEnumCases(TimeEntrySource::cases()),
            'site_readiness' => $this->labeledEnumCases(SiteReadiness::cases()),
            'corrective_action_status' => $this->labeledEnumCases(CorrectiveActionStatus::cases()),
            'responsible_party' => $this->labeledEnumCases(ResponsibleParty::cases()),
            default => null,
        };

        if ($data === null) {
            abort(404);
        }

        return response()->json(['data' => $data]);
    }

    /** @return list<array{value: string, label: string}> */
    private function taskStatuses(): array
    {
        $items = [];

        foreach (TaskStatus::cases() as $status) {
            $items[] = ['value' => $status->value, 'label' => $status->label()];
        }

        return $items;
    }

    /** @return list<array{value: string, label: string}> */
    private function taskPriorities(): array
    {
        $items = [];

        foreach (TaskPriority::cases() as $priority) {
            $items[] = ['value' => $priority->value, 'label' => $priority->label()];
        }

        return $items;
    }

    /** @return list<array{value: string, label: string}> */
    private function blockerCategories(): array
    {
        return BlockerCategory::query()
            ->where('active', true)
            ->orderBy('code')
            ->get()
            ->map(fn (BlockerCategory $category): array => [
                'value' => $category->code,
                'label' => $category->getTranslation('name', app()->getLocale()),
            ])
            ->all();
    }

    /**
     * @param  array<int, CorrectiveActionStatus|FormulaStatus|JournalStatus|PaymentMethod|PaymentStatus|ProjectStage|ProjectStatus|QuotationStatus|ResponsibleParty|SampleApprovalDecision|SampleApprovalType|SampleStatus|SiteReadiness|AttachmentType|TimeEntrySource>  $cases
     * @return list<array{value: string, label: string}>
     */
    private function labeledEnumCases(array $cases): array
    {
        $items = [];

        foreach ($cases as $case) {
            $items[] = ['value' => $case->value, 'label' => $case->label()];
        }

        return $items;
    }
}
