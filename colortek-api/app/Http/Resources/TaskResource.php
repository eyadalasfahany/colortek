<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CorrectiveActionStatus;
use App\Enums\PaymentMethod;
use App\Enums\SiteReadiness;
use App\Enums\TaskStatus;
use App\Models\CorrectiveAction;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\SiteVisit;
use App\Models\Task;
use App\Services\Site\SiteVisitService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ValueError;

/** @mixin Task */
class TaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $locale = $request->getPreferredLanguage(['en', 'ar']) ?? app()->getLocale();
        $definition = $this->relationLoaded('definition') ? $this->definition : null;

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'title' => $this->localizedTitle($locale),
            'instructions' => is_array($this->instructions)
                ? ($this->instructions[$locale] ?? $this->instructions['en'] ?? null)
                : $this->instructions,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'due_at' => $this->due_at?->toIso8601String(),
            'claimed_at' => $this->claimed_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_overdue' => $this->is_overdue,
            'department' => DepartmentResource::make($this->whenLoaded('department')),
            'claimant' => UserResource::make($this->whenLoaded('claimant')),
            'project_id' => $this->project_id,
            'task_code' => $definition?->code,
            'project' => $this->when(
                $this->relationLoaded('project') && $this->project !== null,
                fn (): array => [
                    'id' => $this->project->id,
                    'reference' => $this->project->reference,
                    'name' => $this->project->name,
                    'client_name' => $this->project->relationLoaded('client') ? $this->project->client?->name : null,
                ],
            ),
            'site_block' => $this->buildSiteBlockContext(),
            'form_schema' => $definition !== null
                ? $this->normalizeFormSchema($definition->form_schema, $locale)
                : null,
            'required_fields' => $definition !== null ? ($definition->required_fields ?? []) : [],
            'required_attachment_types' => $definition !== null ? ($definition->required_attachment_types ?? []) : [],
            'previous_outputs' => $this->when(
                $this->relationLoaded('instance'),
                fn (): array => $this->buildPreviousOutputs(),
            ),
            'subject' => $this->when(
                $this->relationLoaded('subject'),
                fn (): ?array => $this->buildSubjectContext($locale),
            ),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buildPreviousOutputs(): array
    {
        if (! $this->instance) {
            return [];
        }

        $previousTasks = $this->instance->tasks()
            ->with(['definition', 'fieldValues'])
            ->where('id', '!=', $this->id)
            ->whereNotNull('completed_at')
            ->orderBy('completed_at')
            ->get();

        return $previousTasks->map(function (Task $task): array {
            $fields = [];
            foreach ($task->fieldValues as $fieldValue) {
                $fields[$fieldValue->key] = $fieldValue->value;
            }

            return [
                'task_code' => $task->definition?->code,
                'task_title' => $task->localizedTitle(),
                'completed_at' => $task->completed_at?->toIso8601String(),
                'fields' => $fields,
            ];
        })->all();
    }

    /** @return array<string, mixed>|null */
    private function buildSubjectContext(string $locale): ?array
    {
        $subject = $this->subject;
        if ($subject instanceof Payment) {
            $subject->loadMissing(['project.client', 'project.salesUser', 'quotation', 'attachments']);

            return [
                'type' => 'payment',
                'id' => $subject->id,
                'installment_number' => $subject->installment_number,
                'amount' => $subject->amount,
                'currency' => $subject->currency,
                'method' => $subject->method->value,
                'paid_at' => $subject->paid_at->toDateString(),
                'status' => $subject->status->value,
                'notes' => $subject->notes,
                'project' => $subject->project ? [
                    'id' => $subject->project->id,
                    'reference' => $subject->project->reference,
                    'name' => $subject->project->name,
                ] : null,
                'client' => $subject->project?->client ? [
                    'id' => $subject->project->client->id,
                    'name' => $subject->project->client->name,
                ] : null,
                'salesperson' => $subject->project?->salesUser ? [
                    'id' => $subject->project->salesUser->id,
                    'name' => $subject->project->salesUser->name,
                ] : null,
                'quotation' => $subject->quotation ? [
                    'number' => $subject->quotation->number,
                    'total_value' => $subject->quotation->total_value,
                    'currency' => $subject->quotation->currency,
                ] : null,
                'attachments' => collect($subject->attachments ?? [])->map(fn ($attachment): array => [
                    'id' => $attachment->id,
                    'type' => $attachment->type,
                    'filename' => $attachment->original_name,
                ])->values()->all(),
            ];
        }

        if ($subject instanceof SiteVisit) {
            $subject->loadMissing(['answers.checklistItem', 'measurements', 'correctiveActions']);

            return [
                'type' => 'site_visit',
                'id' => $subject->id,
                'reference' => $subject->reference,
                'visit_number' => $subject->visit_number,
                'visited_on' => $subject->visited_on?->toDateString(),
                'readiness' => $subject->readiness->value,
                'is_submitted' => $subject->isSubmitted(),
                'project_name_on_form' => $subject->project_name_on_form,
                'address_on_form' => $subject->address_on_form,
                'quotation_number_on_form' => $subject->quotation_number_on_form,
                'client_reference_note' => $subject->client_reference_note,
                'client_signatory_name' => $subject->client_signatory_name,
                'general_notes' => $subject->general_notes,
                'measurement_count' => $subject->measurements->count(),
                'has_critical_failures' => app(SiteVisitService::class)->hasCriticalFailures($subject),
                'open_corrective_count' => $subject->correctiveActions
                    ->where('status', CorrectiveActionStatus::Open)
                    ->count(),
                'conduct_task_id' => $subject->task_id,
            ];
        }

        if ($subject instanceof CorrectiveAction) {
            $subject->loadMissing(['checklistItem', 'siteVisit']);

            return [
                'type' => 'corrective_action',
                'id' => $subject->id,
                'description' => $subject->description,
                'responsible_party' => $subject->responsible_party->value,
                'status' => $subject->status->value,
                'visit_reference' => $subject->siteVisit?->reference,
                'checklist_label' => $subject->checklistItem?->label_en,
            ];
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function buildSiteBlockContext(): ?array
    {
        if ($this->status !== TaskStatus::Pending) {
            return null;
        }

        $this->loadMissing(['project', 'definition']);
        $project = $this->project;
        if ($project === null || $project->site_ready) {
            return null;
        }

        $blockAll = $project->block_all_when_site_not_ready || (bool) Setting::get('block_all_when_site_not_ready', false);
        $blocksWhenNotReady = $this->definition?->blocks_when_site_not_ready ?? false;
        if (! $blockAll && ! $blocksWhenNotReady) {
            return null;
        }

        $visit = SiteVisit::query()
            ->where('project_id', $project->id)
            ->where('readiness', SiteReadiness::NotReady)
            ->orderByDesc('visit_number')
            ->with(['answers.checklistItem', 'correctiveActions'])
            ->first();

        if ($visit === null) {
            return null;
        }

        $failedItems = $visit->answers
            ->filter(fn ($answer): bool => $answer->passed === false && $answer->checklistItem?->is_readiness_critical === true)
            ->map(fn ($answer): string => (string) ($answer->checklistItem?->label_en ?? $answer->checklistItem?->code ?? ''))
            ->filter(fn (string $label): bool => $label !== '')
            ->values()
            ->all();

        return [
            'visit_reference' => $visit->reference,
            'visited_on' => $visit->visited_on?->toDateString(),
            'summary' => $visit->general_notes,
            'failed_items' => $failedItems,
            'open_corrective_count' => $visit->correctiveActions
                ->where('status', CorrectiveActionStatus::Open)
                ->count(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function normalizeFormSchema(?array $schema, string $locale): ?array
    {
        if ($schema === null || ! isset($schema['fields']) || ! is_array($schema['fields'])) {
            return $schema;
        }

        return [
            'fields' => array_map(
                fn (array $field): array => $this->normalizeFormField($field, $locale),
                $schema['fields'],
            ),
        ];
    }

    /** @param array<string, mixed> $field @return array<string, mixed> */
    private function normalizeFormField(array $field, string $locale): array
    {
        $name = (string) ($field['name'] ?? $field['key'] ?? '');
        $labelKey = $locale === 'ar' ? 'label_ar' : 'label_en';
        $label = (string) ($field['label'] ?? $field[$labelKey] ?? $field['label_en'] ?? $name);

        $normalized = [
            'name' => $name,
            'type' => (string) ($field['type'] ?? 'text'),
            'label' => $label,
        ];

        if (array_key_exists('required', $field)) {
            $normalized['required'] = (bool) $field['required'];
        }

        if (isset($field['options']) && is_array($field['options'])) {
            $normalized['options'] = $this->normalizeFieldOptions($field['options']);
        }

        return $normalized;
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @return list<array{value: string, label: string}>
     */
    private function normalizeFieldOptions(array $options): array
    {
        $items = [];

        foreach ($options as $option) {
            if (is_array($option) && isset($option['value'])) {
                $items[] = [
                    'value' => (string) $option['value'],
                    'label' => (string) ($option['label'] ?? $option['value']),
                ];

                continue;
            }

            if (is_string($option)) {
                $items[] = [
                    'value' => $option,
                    'label' => $this->labelForOptionValue($option),
                ];
            }
        }

        return $items;
    }

    private function labelForOptionValue(string $value): string
    {
        try {
            return PaymentMethod::from($value)->label();
        } catch (ValueError) {
            return ucfirst(str_replace('_', ' ', $value));
        }
    }
}
