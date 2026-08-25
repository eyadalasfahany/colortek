<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\HolidayType;
use App\Enums\JournalStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ProjectStage;
use App\Enums\QuotationStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
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
            'quotation_status' => $this->labeledEnumCases(QuotationStatus::cases()),
            'holiday_type' => $this->holidayTypes(),
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

    /** @return list<array{value: string, label: string}> */
    private function holidayTypes(): array
    {
        return [
            ['value' => HolidayType::Public->value, 'label' => 'Public holiday'],
            ['value' => HolidayType::Company->value, 'label' => 'Company holiday'],
        ];
    }

    /**
     * @param  array<int, PaymentMethod|PaymentStatus|JournalStatus|ProjectStage|QuotationStatus>  $cases
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
