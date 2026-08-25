<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Task;

final class TaskValidator
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $attachmentIds
     */
    public function assertReadyToComplete(Task $task, array $fields, array $attachmentIds): void
    {
        $task->loadMissing('definition');

        $definition = $task->definition;
        if ($definition === null) {
            return;
        }

        $requiredFields = $definition->required_fields ?? [];
        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $fields) || $fields[$field] === null || $fields[$field] === '') {
                throw TaskNotReadyToComplete::missingField((string) $field);
            }
        }

        $requiredAttachments = $definition->required_attachment_types ?? [];
        foreach ($requiredAttachments as $type) {
            $typeIds = $attachmentIds[$type] ?? [];
            if ($typeIds === []) {
                throw TaskNotReadyToComplete::missingAttachment((string) $type);
            }
        }
    }
}
