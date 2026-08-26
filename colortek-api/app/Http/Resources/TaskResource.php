<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Sample;
use App\Models\Task;
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
            'comments' => TaskCommentResource::collection($this->whenLoaded('comments')),
            'time_entries' => TimeEntryResource::collection($this->whenLoaded('timeEntries')),
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

        if ($subject instanceof Sample) {
            $subject->loadMissing(['client', 'project', 'parentSample.approvals', 'attachments']);

            $parentRejection = $subject->parentSample?->approvals
                ->first(fn ($approval) => $approval->type->value === 'client'
                    && $approval->decision?->value === 'rejected');

            return [
                'type' => 'sample',
                'id' => $subject->id,
                'reference' => $subject->reference,
                'color' => $subject->color,
                'texture' => $subject->texture,
                'client_reference' => $subject->client_reference,
                'size' => $subject->size,
                'finish_requirement' => $subject->finish_requirement,
                'status' => $subject->status->value,
                'is_presale' => $subject->is_presale,
                'attempt_number' => $subject->attempt_number,
                'modification_reason' => $subject->modification_reason,
                'parent_reference' => $subject->parentSample?->reference,
                'parent_rejection_reason' => $parentRejection?->comments,
                'client' => [
                    'id' => $subject->client->id,
                    'name' => $subject->client->name,
                ],
                'project' => $subject->project ? [
                    'id' => $subject->project->id,
                    'reference' => $subject->project->reference,
                    'name' => $subject->project->name,
                ] : null,
                'attachments' => AttachmentResource::collection($subject->attachments ?? collect())->resolve(),
            ];
        }

        return null;
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
