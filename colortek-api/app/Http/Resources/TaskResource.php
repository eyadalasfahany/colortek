<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Payment;
use App\Models\Sample;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'form_schema' => $definition?->form_schema,
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
                'attachments' => AttachmentResource::collection($subject->attachments ?? collect())->resolve(),
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
}
