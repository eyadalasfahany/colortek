<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Exceptions\TaskNotReadyToComplete;
use App\Models\Task;

final class TaskValidator
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  array<int|string, mixed>  $attachmentIds
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
        $normalizedAttachments = $this->normalizeAttachmentIds($attachmentIds, $requiredAttachments);

        foreach ($requiredAttachments as $type) {
            $typeIds = $normalizedAttachments[$type] ?? [];
            if ($typeIds === []) {
                throw TaskNotReadyToComplete::missingAttachment((string) $type);
            }
        }
    }

    /**
     * Accept attachment_ids as a flat list or keyed by attachment type.
     *
     * @param  array<int|string, mixed>  $attachmentIds
     * @param  list<string>  $requiredTypes
     * @return array<string, list<int>>
     */
    private function normalizeAttachmentIds(array $attachmentIds, array $requiredTypes): array
    {
        if ($attachmentIds === []) {
            return [];
        }

        if (! $this->isFlatAttachmentList($attachmentIds)) {
            /** @var array<string, list<int>> $normalized */
            $normalized = [];

            foreach ($attachmentIds as $type => $ids) {
                if (is_array($ids)) {
                    $normalized[(string) $type] = array_map(intval(...), $ids);
                }
            }

            return $normalized;
        }

        if (count($requiredTypes) === 1) {
            return [$requiredTypes[0] => array_map(intval(...), $attachmentIds)];
        }

        return ['general' => array_map(intval(...), $attachmentIds)];
    }

    /** @param array<int|string, mixed> $attachmentIds */
    private function isFlatAttachmentList(array $attachmentIds): bool
    {
        if ($attachmentIds === []) {
            return true;
        }

        return array_keys($attachmentIds) === range(0, count($attachmentIds) - 1);
    }
}
