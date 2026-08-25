<?php

declare(strict_types=1);

namespace App\Services\Workflow;

use App\Models\Setting;
use App\Models\Task;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class ConditionEvaluator
{
    /** @param array<string, mixed>|null $condition */
    public function passes(?array $condition, Task $task): bool
    {
        if ($condition === null || $condition === []) {
            return true;
        }

        if (isset($condition['all']) && is_array($condition['all'])) {
            foreach ($condition['all'] as $child) {
                if (! $this->passes(is_array($child) ? $child : null, $task)) {
                    return false;
                }
            }

            return true;
        }

        if (isset($condition['any']) && is_array($condition['any'])) {
            foreach ($condition['any'] as $child) {
                if ($this->passes(is_array($child) ? $child : null, $task)) {
                    return true;
                }
            }

            return false;
        }

        if (isset($condition['none']) && is_array($condition['none'])) {
            foreach ($condition['none'] as $child) {
                if ($this->passes(is_array($child) ? $child : null, $task)) {
                    return false;
                }
            }

            return true;
        }

        return $this->evaluateLeaf($condition, $task);
    }

    /** @param array<string, mixed> $condition */
    private function evaluateLeaf(array $condition, Task $task): bool
    {
        $field = (string) ($condition['field'] ?? '');
        $operator = (string) ($condition['operator'] ?? 'equals');
        $expected = $condition['value'] ?? null;

        $actual = $this->resolveField($field, $task);
        $resolved = $actual['resolved'];

        if (! $resolved) {
            Log::info('Workflow condition field unresolvable; treating as empty.', [
                'field' => $field,
                'task_id' => $task->id,
                'reason' => $actual['reason'],
            ]);
        }

        $value = $actual['value'];

        return match ($operator) {
            'equals' => $value == $expected,
            'not_equals' => $value != $expected,
            'in' => is_array($expected) && in_array($value, $expected, false),
            'not_in' => is_array($expected) && ! in_array($value, $expected, false),
            'gt' => is_numeric($value) && is_numeric($expected) && $value > $expected,
            'gte' => is_numeric($value) && is_numeric($expected) && $value >= $expected,
            'lt' => is_numeric($value) && is_numeric($expected) && $value < $expected,
            'lte' => is_numeric($value) && is_numeric($expected) && $value <= $expected,
            'is_empty' => $value === null || $value === '' || $value === [],
            'is_not_empty' => ! ($value === null || $value === '' || $value === []),
            default => false,
        };
    }

    /**
     * @return array{value: mixed, resolved: bool, reason: string|null}
     */
    private function resolveField(string $field, Task $task): array
    {
        if ($field === '') {
            return ['value' => null, 'resolved' => false, 'reason' => 'missing field name'];
        }

        if (str_starts_with($field, 'setting.')) {
            $key = substr($field, strlen('setting.'));
            $value = Setting::get($key);

            return ['value' => $value, 'resolved' => true, 'reason' => null];
        }

        $task->loadMissing(['fieldValues', 'project', 'subject']);

        $fieldValue = $task->fieldValues->firstWhere('key', $field);
        if ($fieldValue !== null) {
            return ['value' => $fieldValue->value, 'resolved' => true, 'reason' => null];
        }

        if (str_contains($field, '.')) {
            $subject = $task->subject ?? $task->project;
            if ($subject !== null) {
                $value = data_get($subject, $field);
                if ($value !== null) {
                    return ['value' => $value, 'resolved' => true, 'reason' => null];
                }

                $shortField = Arr::last(explode('.', $field));
                $fieldValue = $task->fieldValues->firstWhere('key', $shortField);
                if ($fieldValue !== null) {
                    return ['value' => $fieldValue->value, 'resolved' => true, 'reason' => null];
                }
            }
        }

        return ['value' => null, 'resolved' => false, 'reason' => 'field not found'];
    }
}
